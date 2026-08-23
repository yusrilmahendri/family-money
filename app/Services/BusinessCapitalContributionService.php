<?php

namespace App\Services;

use App\Enums\FinanceEntityType;
use App\Models\BusinessCapitalContribution;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessCapitalContributionService
{
    public function __construct(
        private readonly FinanceAccountBalanceService $balances,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{source_account_id: int, destination_account_id: int, amount: float, transaction_date: mixed, description?: ?string}  $data
     */
    public function create(FinanceEntity $sourceFamily, FinanceEntity $business, array $data): BusinessCapitalContribution
    {
        return DB::transaction(function () use ($sourceFamily, $business, $data) {
            $sourceFamily = FinanceEntity::query()->whereKey($sourceFamily->id)->lockForUpdate()->firstOrFail();
            $business = FinanceEntity::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();

            if (! $sourceFamily->isFamily() || ! $sourceFamily->is_active) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Sumber modal harus Family yang aktif.',
                ]);
            }

            if (! $business->isBusiness() || ! $business->is_active) {
                throw ValidationException::withMessages([
                    'business_public_id' => 'Tujuan modal harus usaha yang aktif.',
                ]);
            }

            if ((int) $sourceFamily->id === (int) $business->id) {
                throw ValidationException::withMessages([
                    'business_public_id' => 'Sumber dan tujuan modal tidak boleh entity yang sama.',
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
                ->where('finance_entity_id', $sourceFamily->id)
                ->whereKey($sourceAccountId)
                ->lockForUpdate()
                ->first();

            $destinationAccount = FinanceAccount::query()
                ->where('finance_entity_id', $business->id)
                ->whereKey($destinationAccountId)
                ->lockForUpdate()
                ->first();

            if (! $sourceAccount instanceof FinanceAccount || ! $sourceAccount->is_active) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Account sumber harus milik Family ini dan aktif.',
                ]);
            }

            if (! $destinationAccount instanceof FinanceAccount || ! $destinationAccount->is_active) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'Account tujuan harus milik usaha yang dipilih dan aktif.',
                ]);
            }

            $sourceBalance = $this->balances->balance($sourceAccount);

            if ($amount > $sourceBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah modal melebihi saldo sumber.',
                ]);
            }

            $contribution = BusinessCapitalContribution::query()->create([
                'source_entity_id' => $sourceFamily->id,
                'source_account_id' => $sourceAccount->id,
                'business_entity_id' => $business->id,
                'destination_account_id' => $destinationAccount->id,
                'amount' => $amount,
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'] ?? null,
            ]);

            $this->audit->recordCreated($contribution, $sourceFamily, extra: [
                'counterpart_entity_id' => $business->id,
                'counterpart_entity_public_id' => $business->public_id,
                'counterpart_entity_type' => $business->type->value,
            ]);

            return $contribution;
        });
    }

    /**
     * @return array{
     *     source_not_family: int,
     *     destination_not_business: int,
     *     account_entity_mismatch: int,
     *     invalid_account_relation: int,
     *     non_positive_amount: int,
     *     same_source_and_destination: int,
     *     orphan_contributions: int
     * }
     */
    public function audit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');
        $validAccountIds = FinanceAccount::query()->pluck('id');
        $familyIds = FinanceEntity::query()->where('type', FinanceEntityType::FAMILY)->pluck('id');
        $businessIds = FinanceEntity::query()->where('type', FinanceEntityType::BUSINESS)->pluck('id');

        $orphan = BusinessCapitalContribution::query()
            ->where(function ($query) use ($validEntityIds): void {
                $query->whereNull('source_entity_id')
                    ->orWhereNull('business_entity_id')
                    ->orWhereNotIn('source_entity_id', $validEntityIds)
                    ->orWhereNotIn('business_entity_id', $validEntityIds);
            })
            ->count();

        $invalidRelation = BusinessCapitalContribution::query()
            ->where(function ($query) use ($validAccountIds): void {
                $query->whereNull('source_account_id')
                    ->orWhereNull('destination_account_id')
                    ->orWhereNotIn('source_account_id', $validAccountIds)
                    ->orWhereNotIn('destination_account_id', $validAccountIds);
            })
            ->count();

        return [
            'source_not_family' => BusinessCapitalContribution::query()
                ->whereNotIn('source_entity_id', $familyIds)
                ->count(),
            'destination_not_business' => BusinessCapitalContribution::query()
                ->whereNotIn('business_entity_id', $businessIds)
                ->count(),
            'account_entity_mismatch' => BusinessCapitalContribution::query()
                ->leftJoin('finance_accounts as source_accounts', 'source_accounts.id', '=', 'business_capital_contributions.source_account_id')
                ->leftJoin('finance_accounts as destination_accounts', 'destination_accounts.id', '=', 'business_capital_contributions.destination_account_id')
                ->where(function ($query): void {
                    $query->whereColumn('source_accounts.finance_entity_id', '!=', 'business_capital_contributions.source_entity_id')
                        ->orWhereColumn('destination_accounts.finance_entity_id', '!=', 'business_capital_contributions.business_entity_id');
                })
                ->count(),
            'invalid_account_relation' => $invalidRelation,
            'non_positive_amount' => BusinessCapitalContribution::query()->where('amount', '<=', 0)->count(),
            'same_source_and_destination' => BusinessCapitalContribution::query()
                ->whereColumn('source_entity_id', 'business_entity_id')
                ->count(),
            'orphan_contributions' => $orphan,
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        return array_sum($this->audit()) > 0;
    }
}
