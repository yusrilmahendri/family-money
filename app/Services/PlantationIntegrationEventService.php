<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\ExternalFinancialRecordType;
use App\Enums\IntegrationEventType;
use App\Enums\PlantationIntegrationStatus;
use App\Enums\ReceivablePaymentSourceType;
use App\Enums\ReceivableSourceType;
use App\Exceptions\IntegrationDependencyNotReadyException;
use App\Exceptions\IntegrationIntegrityConflictException;
use App\Models\ExternalFinancialReference;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\ProcessedIntegrationEvent;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Transaction;
use App\Support\CanonicalJson;
use App\Support\FinanceOwnership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlantationIntegrationEventService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope
     * @return array{ok: bool, already_processed: bool, result_type: ?string, result_public_id: ?string}
     */
    public function ingest(array $envelope): array
    {
        $type = IntegrationEventType::from((string) $envelope['event_type']);
        $version = (int) $envelope['event_version'];

        if ($version !== IntegrationEventType::supportedVersion()) {
            throw new InvalidArgumentException('Versi event tidak didukung.');
        }

        $integration = $this->resolveIntegration($envelope);
        $entity = $integration->financeEntity ?? FinanceEntity::query()->findOrFail($integration->finance_entity_id);
        $hash = CanonicalJson::hash([
            'event_type' => $type->value,
            'event_version' => $version,
            'source_public_id' => $envelope['source_public_id'],
            'payload' => $envelope['payload'] ?? [],
        ]);

        try {
            return DB::transaction(function () use ($envelope, $type, $version, $integration, $entity, $hash) {
                $existing = ProcessedIntegrationEvent::query()
                    ->where('event_id', $envelope['event_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof ProcessedIntegrationEvent) {
                    if ($existing->payload_hash !== $hash) {
                        $this->audit->record(
                            $integration,
                            AuditAction::PLANTATION_EVENT_CONFLICT,
                            $entity,
                            null,
                            ['event_id' => $envelope['event_id'], 'event_type' => $type->value],
                            AuditActorType::SYSTEM,
                        );

                        throw new IntegrationIntegrityConflictException('Event payload tidak cocok dengan event_id yang sudah diproses.');
                    }

                    return [
                        'ok' => true,
                        'already_processed' => true,
                        'result_type' => $existing->result_type,
                        'result_public_id' => $existing->result_public_id,
                    ];
                }

                $result = $this->apply($type, $entity, is_array($envelope['payload'] ?? null) ? $envelope['payload'] : []);

                ProcessedIntegrationEvent::query()->create([
                    'event_id' => $envelope['event_id'],
                    'event_type' => $type->value,
                    'event_version' => $version,
                    'plantation_entity_public_id' => $envelope['plantation_entity_public_id'],
                    'finance_entity_id' => $entity->id,
                    'source_public_id' => $envelope['source_public_id'],
                    'payload_hash' => $hash,
                    'processed_at' => now(),
                    'result_type' => $result['result_type'],
                    'result_public_id' => $result['result_public_id'],
                ]);

                $this->audit->record(
                    $integration,
                    AuditAction::PLANTATION_EVENT_PROCESSED,
                    $entity,
                    null,
                    [
                        'event_type' => $type->value,
                        'source_public_id' => $envelope['source_public_id'],
                        'result_type' => $result['result_type'],
                    ],
                    AuditActorType::SYSTEM,
                );

                return [
                    'ok' => true,
                    'already_processed' => false,
                    'result_type' => $result['result_type'],
                    'result_public_id' => $result['result_public_id'],
                ];
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'processed_integration_events') && $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = ProcessedIntegrationEvent::query()->where('event_id', $envelope['event_id'])->first();
            if ($existing instanceof ProcessedIntegrationEvent && $existing->payload_hash === $hash) {
                return [
                    'ok' => true,
                    'already_processed' => true,
                    'result_type' => $existing->result_type,
                    'result_public_id' => $existing->result_public_id,
                ];
            }

            throw new IntegrationIntegrityConflictException('Event payload tidak cocok dengan event_id yang sudah diproses.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: ?string, result_public_id: ?string}
     */
    private function apply(IntegrationEventType $type, FinanceEntity $entity, array $payload): array
    {
        return match ($type) {
            IntegrationEventType::PLANTATION_PURCHASE_POSTED => $this->purchasePosted($entity, $payload),
            IntegrationEventType::PLANTATION_PURCHASE_CANCELLED => $this->purchaseCancelled($entity, $payload),
            IntegrationEventType::PLANTATION_PAYROLL_PAID => $this->payrollPaid($entity, $payload),
            IntegrationEventType::HARVEST_SALE_POSTED => $this->salePosted($entity, $payload),
            IntegrationEventType::HARVEST_SALE_CANCELLED => $this->saleCancelled($entity, $payload),
            IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED => $this->salePaymentReceived($entity, $payload),
            IntegrationEventType::HARVEST_SALE_PAYMENT_REVERSED => $this->salePaymentReversed($entity, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: string, result_public_id: string}
     */
    private function purchasePosted(FinanceEntity $entity, array $payload): array
    {
        $source = (string) ($payload['purchase_public_id'] ?? '');
        $existing = $this->findReference($entity, IntegrationEventType::PLANTATION_PURCHASE_POSTED, $source);
        if ($existing instanceof Transaction) {
            return $this->transactionResult($existing);
        }

        $account = $this->defaultActiveAccount($entity);
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah pembelian tidak valid.');
        }

        $supplier = is_array($payload['supplier'] ?? null) ? $payload['supplier']['name'] ?? null : null;
        $description = trim('Pembelian kebun'.($supplier ? ' — '.$supplier : '').' — '.$source);

        $transaction = $entity->transactions()->create([
            'finance_account_id' => $account->id,
            'category_id' => null,
            'context' => FinanceOwnership::contextFor($entity),
            'amount' => $amount,
            'transaction_date' => $payload['purchase_date'] ?? now()->toDateString(),
            'description' => $description,
            'detail_description' => $this->purchaseDetailDescription($payload, is_string($supplier) ? $supplier : null),
        ]);
        $this->audit->recordCreated($transaction, $entity, AuditActorType::SYSTEM);
        $this->storeReference($entity, IntegrationEventType::PLANTATION_PURCHASE_POSTED, $source, ExternalFinancialRecordType::TRANSACTION, (int) $transaction->id);

        return $this->transactionResult($transaction);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: string, result_public_id: string}
     */
    private function purchaseCancelled(FinanceEntity $entity, array $payload): array
    {
        $source = (string) ($payload['purchase_public_id'] ?? '');
        $transaction = $this->findReference($entity, IntegrationEventType::PLANTATION_PURCHASE_POSTED, $source);
        if (! $transaction instanceof Transaction) {
            throw new IntegrationDependencyNotReadyException('Pengeluaran pembelian belum tersedia.');
        }

        if ($transaction->reversed_at === null) {
            $old = $this->audit->snapshot($transaction);
            $transaction->reversed_at = now();
            $transaction->reversed_reason = (string) ($payload['cancelled_reason'] ?? 'Pembelian kebun dibatalkan');
            $transaction->save();
            $this->audit->recordUpdated($transaction, $old, $entity, AuditActorType::SYSTEM);
        }

        return $this->transactionResult($transaction);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: string, result_public_id: string}
     */
    private function payrollPaid(FinanceEntity $entity, array $payload): array
    {
        $source = (string) ($payload['payroll_public_id'] ?? '');
        $existing = $this->findReference($entity, IntegrationEventType::PLANTATION_PAYROLL_PAID, $source);
        if ($existing instanceof Transaction) {
            return $this->transactionResult($existing);
        }

        $account = $this->defaultActiveAccount($entity);
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah payroll tidak valid.');
        }

        $description = sprintf(
            'Upah kebun — %s — %s',
            (string) ($payload['worker_name'] ?? 'pekerja'),
            (string) ($payload['work_activity_title'] ?? 'aktivitas'),
        );

        $transaction = $entity->transactions()->create([
            'finance_account_id' => $account->id,
            'category_id' => null,
            'context' => FinanceOwnership::contextFor($entity),
            'amount' => $amount,
            'transaction_date' => isset($payload['paid_at']) ? substr((string) $payload['paid_at'], 0, 10) : now()->toDateString(),
            'description' => $description,
            'detail_description' => $this->payrollDetailDescription($payload),
        ]);
        $this->audit->recordCreated($transaction, $entity, AuditActorType::SYSTEM);
        $this->storeReference($entity, IntegrationEventType::PLANTATION_PAYROLL_PAID, $source, ExternalFinancialRecordType::TRANSACTION, (int) $transaction->id);

        return $this->transactionResult($transaction);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: string, result_public_id: string}
     */
    private function salePosted(FinanceEntity $entity, array $payload): array
    {
        $source = (string) ($payload['sale_public_id'] ?? '');
        $buyer = is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
        $receivable = $this->receivables->create($entity, [
            'party_name' => (string) ($buyer['name'] ?? 'Pembeli kebun'),
            'description' => trim('Penjualan panen '.((string) ($payload['invoice_number'] ?? $source))),
            'principal_amount' => (float) ($payload['total_amount'] ?? 0),
            'receivable_date' => $payload['sale_date'] ?? now()->toDateString(),
            'source_type' => ReceivableSourceType::HARVEST_SALE,
            'source_public_id' => $source,
        ]);

        $this->storeReference($entity, IntegrationEventType::HARVEST_SALE_POSTED, $source, ExternalFinancialRecordType::RECEIVABLE, (int) $receivable->id);

        return ['result_type' => 'receivable', 'result_public_id' => $receivable->public_id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: string, result_public_id: string}
     */
    private function saleCancelled(FinanceEntity $entity, array $payload): array
    {
        $source = (string) ($payload['sale_public_id'] ?? '');
        $receivable = Receivable::query()
            ->where('finance_entity_id', $entity->id)
            ->where('source_type', ReceivableSourceType::HARVEST_SALE)
            ->where('source_public_id', $source)
            ->first();

        if (! $receivable instanceof Receivable) {
            throw new IntegrationDependencyNotReadyException('Piutang penjualan belum tersedia.');
        }

        if ($receivable->activePayments()->exists()) {
            throw new InvalidArgumentException('Piutang penjualan yang sudah dibayar tidak dapat dibatalkan.');
        }

        $this->receivables->cancelUnpaid($receivable, (string) ($payload['cancelled_reason'] ?? 'Penjualan kebun dibatalkan'));

        return ['result_type' => 'receivable', 'result_public_id' => $receivable->public_id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: string, result_public_id: string}
     */
    private function salePaymentReceived(FinanceEntity $entity, array $payload): array
    {
        $salePublicId = (string) ($payload['sale_public_id'] ?? '');
        $paymentPublicId = (string) ($payload['payment_public_id'] ?? '');

        $receivable = Receivable::query()
            ->where('finance_entity_id', $entity->id)
            ->where('source_type', ReceivableSourceType::HARVEST_SALE)
            ->where('source_public_id', $salePublicId)
            ->whereNull('cancelled_at')
            ->first();

        if (! $receivable instanceof Receivable) {
            throw new IntegrationDependencyNotReadyException('Piutang penjualan belum tersedia.');
        }

        $existing = ReceivablePayment::query()
            ->where('source_type', ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT)
            ->where('source_public_id', $paymentPublicId)
            ->first();
        if ($existing instanceof ReceivablePayment) {
            return ['result_type' => 'receivable_payment', 'result_public_id' => $existing->public_id];
        }

        $account = $this->defaultActiveAccount($entity);
        $payment = $this->receivables->recordPayment($receivable, [
            'finance_account_id' => $account->id,
            'amount' => (float) ($payload['amount'] ?? 0),
            'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
            'description' => (string) ($payload['reference_number'] ?? 'Pembayaran penjualan panen'),
            'source_type' => ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT,
            'source_public_id' => $paymentPublicId,
        ]);
        $this->storeReference($entity, IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED, $paymentPublicId, ExternalFinancialRecordType::RECEIVABLE_PAYMENT, (int) $payment->id);

        return ['result_type' => 'receivable_payment', 'result_public_id' => $payment->public_id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result_type: ?string, result_public_id: ?string}
     */
    private function salePaymentReversed(FinanceEntity $entity, array $payload): array
    {
        $paymentPublicId = (string) ($payload['payment_public_id'] ?? '');
        $payment = ReceivablePayment::query()
            ->where('source_type', ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT)
            ->where('source_public_id', $paymentPublicId)
            ->first();

        if (! $payment instanceof ReceivablePayment) {
            throw new IntegrationDependencyNotReadyException('Pembayaran piutang belum tersedia.');
        }

        if ($payment->receivable?->finance_entity_id && (int) $payment->receivable->finance_entity_id !== (int) $entity->id) {
            throw new InvalidArgumentException('Pembayaran piutang tidak milik entity ini.');
        }

        $this->receivables->reversePayment($payment);

        return ['result_type' => 'receivable_payment', 'result_public_id' => $payment->public_id];
    }

    private function defaultActiveAccount(FinanceEntity $entity): FinanceAccount
    {
        $account = $entity->accounts()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (! $account instanceof FinanceAccount) {
            throw new InvalidArgumentException('Entity belum memiliki akun default yang aktif.');
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function resolveIntegration(array $envelope): PlantationIntegration
    {
        $plantationPublicId = (string) $envelope['plantation_entity_public_id'];
        $financePublicId = (string) $envelope['finance_entity_public_id'];

        $entity = FinanceEntity::query()->where('public_id', $financePublicId)->first();
        if (! $entity instanceof FinanceEntity) {
            throw new InvalidArgumentException('Finance entity tidak ditemukan.');
        }

        $integration = PlantationIntegration::query()
            ->where('finance_entity_id', $entity->id)
            ->where('plantation_entity_public_id', $plantationPublicId)
            ->first();

        if (! $integration instanceof PlantationIntegration) {
            throw new InvalidArgumentException('Unit kebun tidak terhubung ke Finance entity ini.');
        }

        if ($integration->status !== PlantationIntegrationStatus::ACTIVE) {
            throw new InvalidArgumentException('Integrasi Management Kebun tidak aktif.');
        }

        return $integration;
    }

    private function findReference(FinanceEntity $entity, IntegrationEventType $type, string $sourcePublicId): Transaction|Receivable|ReceivablePayment|null
    {
        $ref = ExternalFinancialReference::query()
            ->where('finance_entity_id', $entity->id)
            ->where('source_system', 'PLANTATION')
            ->where('event_type', $type->value)
            ->where('source_public_id', $sourcePublicId)
            ->first();

        if (! $ref instanceof ExternalFinancialReference) {
            return null;
        }

        return match ($ref->record_type) {
            ExternalFinancialRecordType::TRANSACTION->value => Transaction::query()->find($ref->record_id),
            ExternalFinancialRecordType::RECEIVABLE->value => Receivable::query()->find($ref->record_id),
            ExternalFinancialRecordType::RECEIVABLE_PAYMENT->value => ReceivablePayment::query()->find($ref->record_id),
            default => null,
        };
    }

    private function storeReference(
        FinanceEntity $entity,
        IntegrationEventType $type,
        string $sourcePublicId,
        ExternalFinancialRecordType $recordType,
        int $recordId,
    ): void {
        ExternalFinancialReference::query()->firstOrCreate([
            'source_system' => 'PLANTATION',
            'event_type' => $type->value,
            'source_public_id' => $sourcePublicId,
        ], [
            'finance_entity_id' => $entity->id,
            'record_type' => $recordType->value,
            'record_id' => $recordId,
        ]);
    }

    /**
     * @return array{result_type: string, result_public_id: string}
     */
    private function transactionResult(Transaction $transaction): array
    {
        return [
            'result_type' => ExternalFinancialRecordType::TRANSACTION->value,
            'result_public_id' => (string) $transaction->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function purchaseDetailDescription(array $payload, ?string $supplier): ?string
    {
        $note = trim((string) ($payload['description'] ?? ''));
        $supplier = is_string($supplier) ? trim($supplier) : '';

        if ($note !== '' && $supplier !== '') {
            return 'Pembelian '.$note.' dari '.$supplier;
        }

        if ($note !== '') {
            return $note;
        }

        if ($supplier !== '') {
            return 'Pembelian dari '.$supplier;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payrollDetailDescription(array $payload): ?string
    {
        $worker = trim((string) ($payload['worker_name'] ?? ''));
        $activity = trim((string) ($payload['work_activity_title'] ?? ''));

        if ($worker !== '' && $activity !== '') {
            return $worker.' — '.$activity;
        }

        if ($worker !== '') {
            return $worker;
        }

        if ($activity !== '') {
            return $activity;
        }

        return null;
    }
}
