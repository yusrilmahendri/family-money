<?php

namespace App\Services;

use App\Enums\FinanceEntityType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\ProfitDistribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfitDistributionService
{
    public function __construct(
        private readonly FinanceAccountBalanceService $balances,
        private readonly BusinessProfitService $profits,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     amount: float,
     *     distribution_date: mixed,
     *     period_start?: mixed,
     *     period_end?: mixed,
     *     description?: ?string
     * }  $data
     */
    public function create(FinanceEntity $business, FinanceEntity $family, array $data): ProfitDistribution
    {
        return DB::transaction(function () use ($business, $family, $data) {
            $business = FinanceEntity::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $family = FinanceEntity::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();

            if (! $business->isBusiness() || ! $business->is_active) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Sumber pembagian laba harus usaha yang aktif.',
                ]);
            }

            if (! $family->isFamily() || ! $family->is_active) {
                throw ValidationException::withMessages([
                    'family_public_id' => 'Tujuan pembagian laba harus Family yang aktif.',
                ]);
            }

            if ((int) $business->id === (int) $family->id) {
                throw ValidationException::withMessages([
                    'family_public_id' => 'Sumber dan tujuan pembagian laba tidak boleh entity yang sama.',
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

            $availability = $this->availability(
                $business,
                $data['period_start'] ?? null,
                $data['period_end'] ?? null
            );

            if ($amount > $availability['available']) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembagian melebihi laba tersedia.',
                ]);
            }

            $sourceBalance = $this->balances->balance($sourceAccount);

            if ($amount > $sourceBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembagian melebihi saldo sumber.',
                ]);
            }

            $distribution = ProfitDistribution::query()->create([
                'business_entity_id' => $business->id,
                'source_account_id' => $sourceAccount->id,
                'family_entity_id' => $family->id,
                'destination_account_id' => $destinationAccount->id,
                'amount' => $amount,
                'distribution_date' => $data['distribution_date'],
                'period_start' => $availability['from'],
                'period_end' => $availability['to'],
                'description' => $data['description'] ?? null,
            ]);

            $this->audit->recordCreated($distribution, $business, extra: [
                'counterpart_entity_id' => $family->id,
                'counterpart_entity_public_id' => $family->public_id,
                'counterpart_entity_type' => $family->type->value,
            ]);

            return $distribution;
        });
    }

    /**
     * Available profit is never derived from account balance.
     *
     * @return array{
     *     profit: float,
     *     distributed: float,
     *     available: float,
     *     period_available: float,
     *     all_time_profit: float,
     *     all_time_distributed: float,
     *     all_time_available: float,
     *     from: ?string,
     *     to: ?string
     * }
     */
    public function availability(FinanceEntity $business, mixed $from = null, mixed $to = null): array
    {
        $period = $this->profits->calculate($business, $from, $to);
        $distributed = $this->distributedForExactPeriod($business, $period['from'], $period['to']);
        $periodAvailable = $period['profit'] - $distributed;

        $lifetime = $this->profits->calculate($business);
        $allDistributed = $this->distributedTotal($business);
        $allTimeAvailable = $lifetime['profit'] - $allDistributed;

        return [
            'profit' => $period['profit'],
            'distributed' => $distributed,
            'available' => min($periodAvailable, $allTimeAvailable),
            'period_available' => $periodAvailable,
            'all_time_profit' => $lifetime['profit'],
            'all_time_distributed' => $allDistributed,
            'all_time_available' => $allTimeAvailable,
            'from' => $period['from'],
            'to' => $period['to'],
        ];
    }

    public function distributedTotal(FinanceEntity $business): float
    {
        return (float) $business->profitDistributionsGiven()->sum('amount');
    }

    public function distributedForExactPeriod(FinanceEntity $business, ?string $from, ?string $to): float
    {
        $query = $business->profitDistributionsGiven();

        if ($from === null && $to === null) {
            $query->whereNull('period_start')->whereNull('period_end');
        } else {
            $query->whereDate('period_start', $from)->whereDate('period_end', $to);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Reporting total: all-time sums every distribution; a bounded period uses exact period tags.
     */
    public function reportedDistributed(FinanceEntity $business, ?string $from, ?string $to): float
    {
        if ($from === null && $to === null) {
            return $this->distributedTotal($business);
        }

        return $this->distributedForExactPeriod($business, $from, $to);
    }

    /**
     * @return array{
     *     source_not_business: int,
     *     destination_not_family: int,
     *     account_entity_mismatch: int,
     *     invalid_account_relation: int,
     *     non_positive_amount: int,
     *     same_source_and_destination: int,
     *     invalid_period: int,
     *     exceeds_period_profit: int,
     *     exceeds_all_time_profit: int,
     *     orphan_distributions: int
     * }
     */
    public function audit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');
        $validAccountIds = FinanceAccount::query()->pluck('id');
        $familyIds = FinanceEntity::query()->where('type', FinanceEntityType::FAMILY)->pluck('id');
        $businessIds = FinanceEntity::query()->where('type', FinanceEntityType::BUSINESS)->pluck('id');

        $orphan = ProfitDistribution::query()
            ->where(function ($query) use ($validEntityIds): void {
                $query->whereNull('business_entity_id')
                    ->orWhereNull('family_entity_id')
                    ->orWhereNotIn('business_entity_id', $validEntityIds)
                    ->orWhereNotIn('family_entity_id', $validEntityIds);
            })
            ->count();

        $invalidRelation = ProfitDistribution::query()
            ->where(function ($query) use ($validAccountIds): void {
                $query->whereNull('source_account_id')
                    ->orWhereNull('destination_account_id')
                    ->orWhereNotIn('source_account_id', $validAccountIds)
                    ->orWhereNotIn('destination_account_id', $validAccountIds);
            })
            ->count();

        $invalidPeriod = ProfitDistribution::query()
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('period_start')->whereNull('period_end');
                })->orWhere(function ($inner): void {
                    $inner->whereNull('period_start')->whereNotNull('period_end');
                })->orWhereColumn('period_start', '>', 'period_end');
            })
            ->count();

        $exceedsPeriod = 0;
        $groups = ProfitDistribution::query()
            ->select('business_entity_id', 'period_start', 'period_end', DB::raw('SUM(amount) as total'))
            ->groupBy('business_entity_id', 'period_start', 'period_end')
            ->get();

        foreach ($groups as $group) {
            $entity = FinanceEntity::query()->find($group->business_entity_id);

            if (! $entity instanceof FinanceEntity || ! $entity->isBusiness()) {
                $exceedsPeriod++;

                continue;
            }

            try {
                $profit = $this->profits->calculate($entity, $group->period_start, $group->period_end)['profit'];
            } catch (ValidationException) {
                continue;
            }

            if ((float) $group->total > $profit) {
                $exceedsPeriod++;
            }
        }

        $exceedsAllTime = 0;
        foreach (FinanceEntity::query()->where('type', FinanceEntityType::BUSINESS)->get() as $business) {
            $profit = $this->profits->calculate($business)['profit'];
            if ($this->distributedTotal($business) > $profit) {
                $exceedsAllTime++;
            }
        }

        return [
            'source_not_business' => ProfitDistribution::query()
                ->whereNotIn('business_entity_id', $businessIds)
                ->count(),
            'destination_not_family' => ProfitDistribution::query()
                ->whereNotIn('family_entity_id', $familyIds)
                ->count(),
            'account_entity_mismatch' => ProfitDistribution::query()
                ->leftJoin('finance_accounts as source_accounts', 'source_accounts.id', '=', 'profit_distributions.source_account_id')
                ->leftJoin('finance_accounts as destination_accounts', 'destination_accounts.id', '=', 'profit_distributions.destination_account_id')
                ->where(function ($query): void {
                    $query->whereColumn('source_accounts.finance_entity_id', '!=', 'profit_distributions.business_entity_id')
                        ->orWhereColumn('destination_accounts.finance_entity_id', '!=', 'profit_distributions.family_entity_id');
                })
                ->count(),
            'invalid_account_relation' => $invalidRelation,
            'non_positive_amount' => ProfitDistribution::query()->where('amount', '<=', 0)->count(),
            'same_source_and_destination' => ProfitDistribution::query()
                ->whereColumn('business_entity_id', 'family_entity_id')
                ->count(),
            'invalid_period' => $invalidPeriod,
            'exceeds_period_profit' => $exceedsPeriod,
            'exceeds_all_time_profit' => $exceedsAllTime,
            'orphan_distributions' => $orphan,
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        return array_sum($this->audit()) > 0;
    }
}
