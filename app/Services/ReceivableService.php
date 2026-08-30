<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ReceivablePaymentSourceType;
use App\Enums\ReceivablePaymentStatus;
use App\Enums\ReceivableSourceType;
use App\Enums\ReceivableStatus;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash-basis receivable: the principal is a claim, not cash and not Income.
 * Only ReceivablePayment increases FinanceAccount balance. BUSINESS profit
 * stays Income − operational expense; payment is not revenue.
 */
class ReceivableService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{party_name: string, description?: ?string, principal_amount: float, receivable_date: mixed, due_date?: mixed, source_type?: ReceivableSourceType|string|null, source_public_id?: ?string}  $data
     */
    public function create(FinanceEntity $entity, array $data): Receivable
    {
        return DB::transaction(function () use ($entity, $data) {
            $sourceType = $this->receivableSource($data['source_type'] ?? null);
            $sourcePublicId = isset($data['source_public_id']) ? trim((string) $data['source_public_id']) : '';

            if ($sourceType === ReceivableSourceType::HARVEST_SALE && $sourcePublicId !== '') {
                $existing = Receivable::query()
                    ->where('finance_entity_id', $entity->id)
                    ->where('source_type', $sourceType)
                    ->where('source_public_id', $sourcePublicId)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Receivable) {
                    return $existing;
                }
            }

            $principal = (float) $data['principal_amount'];

            if ($principal <= 0) {
                throw ValidationException::withMessages([
                    'principal_amount' => 'Jumlah piutang harus lebih dari 0.',
                ]);
            }

            $receivable = new Receivable([
                'party_name' => $data['party_name'],
                'description' => $data['description'] ?? null,
                'principal_amount' => $principal,
                'remaining_balance' => $principal,
                'receivable_date' => $data['receivable_date'],
                'due_date' => $data['due_date'] ?? null,
                'source_type' => $sourceType,
                'source_public_id' => $sourcePublicId !== '' ? $sourcePublicId : null,
            ]);
            $receivable->finance_entity_id = $entity->id;
            $receivable->syncStatus();
            $receivable->save();

            $this->audit->recordCreated($receivable, $entity);

            return $receivable;
        });
    }

    /**
     * @param  array{party_name: string, description?: ?string, principal_amount?: float, receivable_date: mixed, due_date?: mixed}  $data
     */
    public function update(Receivable $receivable, array $data): Receivable
    {
        return DB::transaction(function () use ($receivable, $data) {
            $receivable = Receivable::query()->whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            $old = $this->audit->snapshot($receivable);
            $hasPayments = $receivable->payments()->exists();

            if ($hasPayments && array_key_exists('principal_amount', $data)
                && (float) $data['principal_amount'] !== (float) $receivable->principal_amount) {
                throw ValidationException::withMessages([
                    'principal_amount' => 'Pokok piutang tidak dapat diubah setelah ada pembayaran.',
                ]);
            }

            $receivable->party_name = $data['party_name'];
            $receivable->description = $data['description'] ?? null;
            $receivable->receivable_date = $data['receivable_date'];
            $receivable->due_date = $data['due_date'] ?? null;

            if (! $hasPayments && array_key_exists('principal_amount', $data)) {
                $principal = (float) $data['principal_amount'];

                if ($principal <= 0) {
                    throw ValidationException::withMessages([
                        'principal_amount' => 'Jumlah piutang harus lebih dari 0.',
                    ]);
                }

                $receivable->principal_amount = $principal;
                $receivable->remaining_balance = $principal;
            }

            $receivable->syncStatus();
            $receivable->save();

            $this->audit->recordUpdated($receivable, $old, $receivable->financeEntity);

            return $receivable;
        });
    }

    /**
     * @param  array{finance_account_id: int, amount: float, payment_date: mixed, description?: ?string, source_type?: ReceivablePaymentSourceType|string|null, source_public_id?: ?string}  $data
     */
    public function recordPayment(Receivable $receivable, array $data): ReceivablePayment
    {
        return DB::transaction(function () use ($receivable, $data) {
            $sourceType = $this->paymentSource($data['source_type'] ?? null);
            $sourcePublicId = isset($data['source_public_id']) ? trim((string) $data['source_public_id']) : '';

            if ($sourceType === ReceivablePaymentSourceType::HARVEST_SALE_PAYMENT && $sourcePublicId !== '') {
                $existing = ReceivablePayment::query()
                    ->where('source_type', $sourceType)
                    ->where('source_public_id', $sourcePublicId)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof ReceivablePayment) {
                    return $existing;
                }
            }

            $receivable = Receivable::query()->whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            $amount = (float) $data['amount'];

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran harus lebih dari 0.',
                ]);
            }

            $remaining = (float) $receivable->remaining_balance;

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran melebihi sisa piutang. Sisa piutang hanya '.Rupiah::format($remaining).'.',
                ]);
            }

            $account = FinanceAccount::query()
                ->where('finance_entity_id', $receivable->finance_entity_id)
                ->whereKey((int) $data['finance_account_id'])
                ->lockForUpdate()
                ->first();

            if (! $account instanceof FinanceAccount || ! $account->is_active) {
                throw ValidationException::withMessages([
                    'finance_account_id' => 'Account tujuan harus milik entity ini dan aktif.',
                ]);
            }

            $payment = $receivable->payments()->create([
                'finance_account_id' => $account->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'description' => $data['description'] ?? null,
                'source_type' => $sourceType,
                'source_public_id' => $sourcePublicId !== '' ? $sourcePublicId : null,
            ]);

            $receivable->remaining_balance = round($remaining - $amount, 2);
            $receivable->syncStatus();
            $receivable->save();

            $this->audit->record($payment, AuditAction::PAYMENT, $receivable->financeEntity);

            return $payment;
        });
    }

    public function reversePayment(ReceivablePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $locked = ReceivablePayment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked instanceof ReceivablePayment) {
                return;
            }

            $receivable = Receivable::query()->whereKey($locked->receivable_id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ReceivablePaymentStatus::REVERSED) {
                return;
            }

            $old = $this->audit->snapshot($locked);

            $locked->status = ReceivablePaymentStatus::REVERSED;
            $locked->reversed_at = now();
            $locked->reversed_reason = 'Plantation payment reversed';
            $locked->save();

            $receivable->remaining_balance = round((float) $receivable->remaining_balance + (float) $locked->amount, 2);
            $receivable->syncStatus();
            $receivable->save();

            $this->audit->recordUpdated($locked, $old, $receivable->financeEntity);
        });
    }

    public function cancelUnpaid(Receivable $receivable, string $reason = 'Penjualan kebun dibatalkan'): void
    {
        DB::transaction(function () use ($receivable, $reason) {
            $locked = Receivable::query()->whereKey($receivable->id)->lockForUpdate()->firstOrFail();

            if ($locked->cancelled_at !== null) {
                return;
            }

            if ($locked->activePayments()->exists()) {
                throw ValidationException::withMessages([
                    'receivable' => 'Piutang yang sudah memiliki pembayaran aktif tidak dapat dibatalkan.',
                ]);
            }

            $old = $this->audit->snapshot($locked);
            $locked->cancelled_at = now();
            $locked->cancelled_reason = $reason;
            $locked->save();
            $this->audit->recordUpdated($locked, $old, $locked->financeEntity);
        });
    }

    public function deleteUnpaid(Receivable $receivable): void
    {
        $this->cancelUnpaid($receivable);
    }

    public function outstandingTotal(FinanceEntity $entity): float
    {
        return (float) $entity->receivables()->whereNull('cancelled_at')->sum('remaining_balance');
    }

    public function overdueOutstanding(FinanceEntity $entity): float
    {
        return (float) $entity->receivables()
            ->whereNull('cancelled_at')
            ->where('remaining_balance', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('remaining_balance');
    }

    /**
     * @return array{
     *     negative_remaining: int,
     *     remaining_exceeds_principal: int,
     *     payment_mismatch: int,
     *     account_entity_mismatch: int,
     *     invalid_account_relation: int,
     *     invalid_status: int,
     *     unmarked_overdue: int,
     *     orphan_receivables: int,
     *     orphan_payments: int
     * }
     */
    public function audit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');
        $validAccountIds = FinanceAccount::query()->pluck('id');
        $validReceivableIds = Receivable::query()->pluck('id');

        $paymentMismatch = 0;
        $invalidStatus = 0;
        $unmarkedOverdue = 0;

        Receivable::query()->withSum(['payments as payments_sum_amount' => function ($query): void {
            $query->where('status', ReceivablePaymentStatus::ACTIVE);
        }], 'amount')->each(function (Receivable $receivable) use (&$paymentMismatch, &$invalidStatus, &$unmarkedOverdue): void {
            $paid = (float) ($receivable->payments_sum_amount ?? 0);
            $expected = round((float) $receivable->principal_amount - $paid, 2);

            if (round((float) $receivable->remaining_balance, 2) !== $expected) {
                $paymentMismatch++;
            }

            $computed = $receivable->computedStatus();

            if ($receivable->status !== $computed) {
                $invalidStatus++;
            }

            if ($computed === ReceivableStatus::OVERDUE && $receivable->status !== ReceivableStatus::OVERDUE) {
                $unmarkedOverdue++;
            }
        });

        return [
            'negative_remaining' => Receivable::query()->where('remaining_balance', '<', 0)->count(),
            'remaining_exceeds_principal' => Receivable::query()
                ->whereColumn('remaining_balance', '>', 'principal_amount')
                ->count(),
            'payment_mismatch' => $paymentMismatch,
            'account_entity_mismatch' => ReceivablePayment::query()
                ->join('receivables', 'receivables.id', '=', 'receivable_payments.receivable_id')
                ->join('finance_accounts', 'finance_accounts.id', '=', 'receivable_payments.finance_account_id')
                ->whereColumn('finance_accounts.finance_entity_id', '!=', 'receivables.finance_entity_id')
                ->count(),
            'invalid_account_relation' => ReceivablePayment::query()
                ->where(function ($query) use ($validAccountIds): void {
                    $query->whereNull('finance_account_id')
                        ->orWhereNotIn('finance_account_id', $validAccountIds);
                })
                ->count(),
            'invalid_status' => $invalidStatus,
            'unmarked_overdue' => $unmarkedOverdue,
            'orphan_receivables' => Receivable::query()
                ->where(function ($query) use ($validEntityIds): void {
                    $query->whereNull('finance_entity_id')
                        ->orWhereNotIn('finance_entity_id', $validEntityIds);
                })
                ->count(),
            'orphan_payments' => ReceivablePayment::query()
                ->where(function ($query) use ($validReceivableIds): void {
                    $query->whereNull('receivable_id')
                        ->orWhereNotIn('receivable_id', $validReceivableIds);
                })
                ->count(),
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        return array_sum($this->audit()) > 0;
    }

    private function receivableSource(mixed $value): ?ReceivableSourceType
    {
        if ($value instanceof ReceivableSourceType) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ReceivableSourceType::tryFrom($value);
        }

        return null;
    }

    private function paymentSource(mixed $value): ?ReceivablePaymentSourceType
    {
        if ($value instanceof ReceivablePaymentSourceType) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ReceivablePaymentSourceType::tryFrom($value);
        }

        return null;
    }
}
