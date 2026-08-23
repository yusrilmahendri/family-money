<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ReceivableStatus;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
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
     * @param  array{party_name: string, description?: ?string, principal_amount: float, receivable_date: mixed, due_date?: mixed}  $data
     */
    public function create(FinanceEntity $entity, array $data): Receivable
    {
        return DB::transaction(function () use ($entity, $data) {
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
     * @param  array{finance_account_id: int, amount: float, payment_date: mixed, description?: ?string}  $data
     */
    public function recordPayment(Receivable $receivable, array $data): ReceivablePayment
    {
        return DB::transaction(function () use ($receivable, $data) {
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
                    'amount' => 'Jumlah pembayaran melebihi sisa piutang.',
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
            ]);

            $receivable->remaining_balance = round($remaining - $amount, 2);
            $receivable->syncStatus();
            $receivable->save();

            $this->audit->record($payment, AuditAction::PAYMENT, $receivable->financeEntity);

            return $payment;
        });
    }

    public function outstandingTotal(FinanceEntity $entity): float
    {
        return (float) $entity->receivables()->sum('remaining_balance');
    }

    public function overdueOutstanding(FinanceEntity $entity): float
    {
        return (float) $entity->receivables()
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

        Receivable::query()->withSum('payments', 'amount')->each(function (Receivable $receivable) use (&$paymentMismatch, &$invalidStatus, &$unmarkedOverdue): void {
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
}
