<?php

namespace App\Services;

use App\Enums\FinanceAccountType;
use App\Enums\HarvestFinanceEventType;
use App\Enums\PlantationIntegrationStatus;
use App\Enums\ReceivablePaymentSourceType;
use App\Enums\ReceivableSourceType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HarvestReceivableSyncService
{
    public function __construct(private readonly ReceivableService $receivables) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: string, receivable_public_id: string|null, payment_public_id: string|null, status: string|null}
     */
    public function handle(array $payload): array
    {
        $event = HarvestFinanceEventType::from((string) $payload['event']);
        $integration = $this->resolveIntegration($payload);
        $entity = $integration->financeEntity;

        if (! $entity instanceof FinanceEntity) {
            $entity = FinanceEntity::query()->findOrFail($integration->finance_entity_id);
        }

        if (! $integration->isActive()) {
            throw new InvalidArgumentException('Integrasi Management Kebun tidak aktif.');
        }

        return match ($event) {
            HarvestFinanceEventType::HARVEST_SALE_POSTED => $this->posted($entity, $payload),
            HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED => $this->paymentReceived($entity, $payload),
            HarvestFinanceEventType::HARVEST_SALE_PAYMENT_REVERSED => $this->paymentReversed($payload),
            HarvestFinanceEventType::HARVEST_SALE_CANCELLED => $this->cancelled($payload),
        };
    }

    /**
     * Pull posted harvest sales from Plantation and apply the event contract.
     *
     * @param  list<array<string, mixed>>  $sales
     * @return array{posted: int, payments: int, reversed: int, cancelled: int}
     */
    public function ingestPulledSales(FinanceEntity $entity, array $sales): array
    {
        $counts = ['posted' => 0, 'payments' => 0, 'reversed' => 0, 'cancelled' => 0];
        $integration = $entity->plantationIntegration;

        if (! $integration instanceof PlantationIntegration || ! $integration->isActive()) {
            throw new InvalidArgumentException('Management Kebun harus aktif sebelum piutang panen disinkronkan.');
        }

        foreach ($sales as $sale) {
            if (! is_array($sale)) {
                continue;
            }

            $status = (string) ($sale['status'] ?? '');
            $base = [
                'plantation_entity_public_id' => $integration->plantation_entity_public_id,
                'finance_entity_public_id' => $entity->public_id,
                'sale' => $sale,
            ];

            if ($status === 'POSTED' || $status === 'CANCELLED') {
                $this->handle([...$base, 'event' => HarvestFinanceEventType::HARVEST_SALE_POSTED->value]);
                $counts['posted']++;
            }

            $payments = is_array($sale['payments'] ?? null) ? $sale['payments'] : [];
            foreach ($payments as $payment) {
                if (! is_array($payment)) {
                    continue;
                }

                $this->handle([
                    ...$base,
                    'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
                    'payment' => $payment,
                ]);
                $counts['payments']++;

                if (($payment['status'] ?? '') === 'REVERSED') {
                    $this->handle([
                        ...$base,
                        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_REVERSED->value,
                        'payment' => $payment,
                    ]);
                    $counts['reversed']++;
                }
            }

            if ($status === 'CANCELLED') {
                $this->handle([...$base, 'event' => HarvestFinanceEventType::HARVEST_SALE_CANCELLED->value]);
                $counts['cancelled']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: string, receivable_public_id: string|null, payment_public_id: string|null, status: string|null}
     */
    private function posted(FinanceEntity $entity, array $payload): array
    {
        $receivable = $this->upsertReceivable($entity, $payload['sale'] ?? []);

        return $this->result(HarvestFinanceEventType::HARVEST_SALE_POSTED, $receivable);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: string, receivable_public_id: string|null, payment_public_id: string|null, status: string|null}
     */
    private function paymentReceived(FinanceEntity $entity, array $payload): array
    {
        return DB::transaction(function () use ($entity, $payload) {
            $sale = is_array($payload['sale'] ?? null) ? $payload['sale'] : [];
            $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
            $paymentPublicId = trim((string) ($payment['public_id'] ?? ''));

            if ($paymentPublicId === '') {
                throw new InvalidArgumentException('Identitas pembayaran panen tidak valid.');
            }

            $existing = ReceivablePayment::query()
                ->where('source_type', ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT)
                ->where('source_public_id', $paymentPublicId)
                ->first();

            if ($existing instanceof ReceivablePayment) {
                $receivable = $existing->receivable;

                return $this->result(
                    HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED,
                    $receivable,
                    $existing,
                );
            }

            $receivable = $this->upsertReceivable($entity, $sale);
            $account = $this->resolveAccount($entity, (string) ($payment['payment_method'] ?? 'CASH'));
            $description = trim((string) ($payment['reference_number'] ?? ''));
            if ($description === '') {
                $description = trim((string) ($payment['notes'] ?? ''));
            }
            if ($description === '') {
                $description = 'Pembayaran penjualan panen';
            }

            $recorded = $this->receivables->recordPayment($receivable, [
                'finance_account_id' => $account->id,
                'amount' => (float) $payment['amount'],
                'payment_date' => $payment['payment_date'],
                'description' => $description,
                'source_type' => ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT,
                'source_public_id' => $paymentPublicId,
            ]);

            return $this->result(
                HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED,
                $receivable->fresh(),
                $recorded,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: string, receivable_public_id: string|null, payment_public_id: string|null, status: string|null}
     */
    private function paymentReversed(array $payload): array
    {
        $paymentPublicId = trim((string) (($payload['payment']['public_id'] ?? '')));

        if ($paymentPublicId === '') {
            throw new InvalidArgumentException('Identitas pembayaran panen tidak valid.');
        }

        $existing = ReceivablePayment::query()
            ->where('source_type', ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT)
            ->where('source_public_id', $paymentPublicId)
            ->first();

        if (! $existing instanceof ReceivablePayment) {
            $salePublicId = trim((string) (($payload['sale']['public_id'] ?? '')));
            $receivable = $this->findReceivable($salePublicId);

            return $this->result(HarvestFinanceEventType::HARVEST_SALE_PAYMENT_REVERSED, $receivable);
        }

        $receivable = $existing->receivable;
        $this->receivables->reversePayment($existing);

        return $this->result(
            HarvestFinanceEventType::HARVEST_SALE_PAYMENT_REVERSED,
            $receivable?->fresh(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: string, receivable_public_id: string|null, payment_public_id: string|null, status: string|null}
     */
    private function cancelled(array $payload): array
    {
        $salePublicId = trim((string) (($payload['sale']['public_id'] ?? '')));
        $receivable = $this->findReceivable($salePublicId);

        if (! $receivable instanceof Receivable) {
            return $this->result(HarvestFinanceEventType::HARVEST_SALE_CANCELLED);
        }

        if ($receivable->payments()->exists()) {
            throw new InvalidArgumentException('Piutang penjualan panen yang sudah dibayar tidak dapat dibatalkan.');
        }

        $this->receivables->deleteUnpaid($receivable);

        return $this->result(HarvestFinanceEventType::HARVEST_SALE_CANCELLED);
    }

    /**
     * @param  array<string, mixed>  $sale
     */
    private function upsertReceivable(FinanceEntity $entity, array $sale): Receivable
    {
        $salePublicId = trim((string) ($sale['public_id'] ?? ''));

        if ($salePublicId === '') {
            throw new InvalidArgumentException('Identitas penjualan panen tidak valid.');
        }

        $existing = $this->findReceivable($salePublicId);
        if ($existing instanceof Receivable) {
            if ((int) $existing->finance_entity_id !== (int) $entity->id) {
                throw new InvalidArgumentException('Penjualan panen sudah terhubung ke entity lain.');
            }

            return $existing;
        }

        $principal = (float) ($sale['total_amount'] ?? 0);
        if ($principal <= 0) {
            throw new InvalidArgumentException('Total penjualan panen harus lebih dari 0 untuk membuat piutang.');
        }

        $invoice = trim((string) ($sale['invoice_number'] ?? ''));
        $notes = trim((string) ($sale['description'] ?? ''));
        $description = $invoice !== ''
            ? 'Penjualan panen '.$invoice
            : 'Penjualan panen';
        if ($notes !== '') {
            $description .= ' — '.$notes;
        }

        return $this->receivables->create($entity, [
            'party_name' => (string) ($sale['buyer_name'] ?? 'Pembeli kebun'),
            'description' => $description,
            'principal_amount' => $principal,
            'receivable_date' => $sale['sale_date'] ?? now()->toDateString(),
            'source_type' => ReceivableSourceType::HARVEST_SALE,
            'source_public_id' => $salePublicId,
        ]);
    }

    private function findReceivable(string $salePublicId): ?Receivable
    {
        if ($salePublicId === '') {
            return null;
        }

        return Receivable::query()
            ->where('source_type', ReceivableSourceType::HARVEST_SALE)
            ->where('source_public_id', $salePublicId)
            ->first();
    }

    private function resolveAccount(FinanceEntity $entity, string $paymentMethod): FinanceAccount
    {
        $preferred = strtoupper($paymentMethod) === 'BANK_TRANSFER'
            ? FinanceAccountType::BANK
            : FinanceAccountType::CASH;

        $query = FinanceAccount::query()
            ->where('finance_entity_id', $entity->id)
            ->where('is_active', true);

        $account = (clone $query)
            ->where('type', $preferred)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if (! $account instanceof FinanceAccount) {
            $account = (clone $query)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();
        }

        if (! $account instanceof FinanceAccount) {
            throw new InvalidArgumentException('Entity belum memiliki akun kas/bank aktif untuk menerima pembayaran panen.');
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveIntegration(array $payload): PlantationIntegration
    {
        $plantationPublicId = trim((string) ($payload['plantation_entity_public_id'] ?? ''));
        $financePublicId = trim((string) ($payload['finance_entity_public_id'] ?? ''));

        $integration = PlantationIntegration::query()
            ->where('plantation_entity_public_id', $plantationPublicId)
            ->where('status', PlantationIntegrationStatus::ACTIVE)
            ->first();

        if (! $integration instanceof PlantationIntegration) {
            throw new InvalidArgumentException('Unit kebun tidak terhubung ke Finance.');
        }

        if ($financePublicId !== '') {
            $entity = $integration->financeEntity;
            if ($entity instanceof FinanceEntity && $entity->public_id !== $financePublicId) {
                throw new InvalidArgumentException('Finance entity tidak cocok dengan integrasi kebun.');
            }
        }

        return $integration;
    }

    /**
     * @return array{event: string, receivable_public_id: string|null, payment_public_id: string|null, status: string|null}
     */
    private function result(
        HarvestFinanceEventType $event,
        ?Receivable $receivable = null,
        ?ReceivablePayment $payment = null,
    ): array {
        return [
            'event' => $event->value,
            'receivable_public_id' => $receivable?->public_id,
            'payment_public_id' => $payment?->public_id,
            'status' => $receivable?->status?->value,
        ];
    }
}
