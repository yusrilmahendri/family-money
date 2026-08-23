<?php

namespace App\Services;

use App\Enums\FinanceEntityType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\OwnerWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerWithdrawalService
{
    public function __construct(
        private readonly FinanceAccountBalanceService $balances,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{source_account_id: int, destination_account_id: int, amount: float, transaction_date: mixed, description?: ?string}  $data
     */
    public function create(FinanceEntity $business, FinanceEntity $family, array $data): OwnerWithdrawal
    {
        return DB::transaction(function () use ($business, $family, $data) {
            $business = FinanceEntity::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $family = FinanceEntity::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();

            if (! $business->isBusiness() || ! $business->is_active) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Sumber prive harus usaha yang aktif.',
                ]);
            }

            if (! $family->isFamily() || ! $family->is_active) {
                throw ValidationException::withMessages([
                    'family_public_id' => 'Tujuan prive harus Family yang aktif.',
                ]);
            }

            if ((int) $business->id === (int) $family->id) {
                throw ValidationException::withMessages([
                    'family_public_id' => 'Sumber dan tujuan prive tidak boleh entity yang sama.',
                ]);
            }

            $sourceAccountId = (int) $data['source_account_id'];
            $destinationAccountId = (int) $data['destination_account_id'];
            $amount = (float) $data['amount'];

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah harus lebih dari 0.',
                ]);
            }

            $sourceAccount = FinanceAccount::query()
                ->where('finance_entity_id', $business->id)
                ->whereKey($sourceAccountId)
                ->lockForUpdate()
                ->first();

            $destinationAccount = FinanceAccount::query()
                ->where('finance_entity_id', $family->id)
                ->whereKey($destinationAccountId)
                ->lockForUpdate()
                ->first();

            if (! $sourceAccount instanceof FinanceAccount || ! $sourceAccount->is_active) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Account sumber harus milik usaha ini dan aktif.',
                ]);
            }

            if (! $destinationAccount instanceof FinanceAccount || ! $destinationAccount->is_active) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'Account tujuan harus milik Family yang dipilih dan aktif.',
                ]);
            }

            $sourceBalance = $this->balances->balance($sourceAccount);

            if ($amount > $sourceBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah prive melebihi saldo sumber.',
                ]);
            }

            $withdrawal = OwnerWithdrawal::query()->create([
                'business_entity_id' => $business->id,
                'source_account_id' => $sourceAccount->id,
                'family_entity_id' => $family->id,
                'destination_account_id' => $destinationAccount->id,
                'amount' => $amount,
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'] ?? null,
            ]);

            $this->audit->recordCreated($withdrawal, $business, extra: [
                'counterpart_entity_id' => $family->id,
                'counterpart_entity_public_id' => $family->public_id,
                'counterpart_entity_type' => $family->type->value,
            ]);

            return $withdrawal;
        });
    }

    /**
     * @return array{
     *     source_not_business: int,
     *     destination_not_family: int,
     *     account_entity_mismatch: int,
     *     invalid_account_relation: int,
     *     non_positive_amount: int,
     *     same_source_and_destination: int,
     *     orphan_withdrawals: int
     * }
     */
    public function audit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');
        $validAccountIds = FinanceAccount::query()->pluck('id');
        $familyIds = FinanceEntity::query()->where('type', FinanceEntityType::FAMILY)->pluck('id');
        $businessIds = FinanceEntity::query()->where('type', FinanceEntityType::BUSINESS)->pluck('id');

        $orphan = OwnerWithdrawal::query()
            ->where(function ($query) use ($validEntityIds): void {
                $query->whereNull('business_entity_id')
                    ->orWhereNull('family_entity_id')
                    ->orWhereNotIn('business_entity_id', $validEntityIds)
                    ->orWhereNotIn('family_entity_id', $validEntityIds);
            })
            ->count();

        $invalidRelation = OwnerWithdrawal::query()
            ->where(function ($query) use ($validAccountIds): void {
                $query->whereNull('source_account_id')
                    ->orWhereNull('destination_account_id')
                    ->orWhereNotIn('source_account_id', $validAccountIds)
                    ->orWhereNotIn('destination_account_id', $validAccountIds);
            })
            ->count();

        return [
            'source_not_business' => OwnerWithdrawal::query()
                ->whereNotIn('business_entity_id', $businessIds)
                ->count(),
            'destination_not_family' => OwnerWithdrawal::query()
                ->whereNotIn('family_entity_id', $familyIds)
                ->count(),
            'account_entity_mismatch' => OwnerWithdrawal::query()
                ->leftJoin('finance_accounts as source_accounts', 'source_accounts.id', '=', 'owner_withdrawals.source_account_id')
                ->leftJoin('finance_accounts as destination_accounts', 'destination_accounts.id', '=', 'owner_withdrawals.destination_account_id')
                ->where(function ($query): void {
                    $query->whereColumn('source_accounts.finance_entity_id', '!=', 'owner_withdrawals.business_entity_id')
                        ->orWhereColumn('destination_accounts.finance_entity_id', '!=', 'owner_withdrawals.family_entity_id');
                })
                ->count(),
            'invalid_account_relation' => $invalidRelation,
            'non_positive_amount' => OwnerWithdrawal::query()->where('amount', '<=', 0)->count(),
            'same_source_and_destination' => OwnerWithdrawal::query()
                ->whereColumn('business_entity_id', 'family_entity_id')
                ->count(),
            'orphan_withdrawals' => $orphan,
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        return array_sum($this->audit()) > 0;
    }
}
