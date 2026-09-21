<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetAvailabilityService
{
    public const OVER_ALLOCATION_MESSAGE = 'Alokasi anggaran melebihi saldo tersedia.';

    public function __construct(
        private readonly FinanceAccountBalanceService $balances,
    ) {}

    /**
     * Derived reservation against operating cash. Does not change cash.
     *
     * cash_balance = FinanceAccountBalanceService operating total (ACTIVE accounts).
     * planned_amount = PlantationOperatingBudget pagu when Management Kebun is active,
     *     otherwise category Budget.amount. Never both.
     * realized_amount = BudgetActivity that already reduced cash, attributed so reserved
     *     does not double-count realization.
     * reserved_remaining = max(planned - realized, 0)
     * available_balance = cash - reserved_remaining
     *
     * @return array{
     *     cash_balance: float,
     *     planned_amount: float,
     *     realized_amount: float,
     *     reserved_remaining: float,
     *     available_balance: float,
     *     uses_operating_pagu: bool
     * }
     */
    public function summary(
        FinanceEntity $entity,
        ?PlantationOperatingBudget $exceptOperating = null,
        ?Budget $exceptCategory = null,
    ): array {
        $cash = $this->balances->balanceForEntity($entity);

        if (! $entity->isBusiness()) {
            return $this->payload($cash, 0.0, 0.0, false);
        }

        $usesOperatingPagu = $this->usesOperatingBudgetPagu($entity);
        [$planned, $realized] = $usesOperatingPagu
            ? $this->operatingPagu($entity, $exceptOperating)
            : $this->categoryPagu($entity, $exceptCategory);

        return $this->payload($cash, $planned, $realized, $usesOperatingPagu);
    }

    public function usesOperatingBudgetPagu(FinanceEntity $entity): bool
    {
        return $entity->hasActivePlantationIntegration();
    }

    /**
     * Serialize budget create/update for one entity, then assert the new amount fits cash.
     */
    public function assertCanAllocate(
        FinanceEntity $entity,
        float $amount,
        ?PlantationOperatingBudget $exceptOperating = null,
        ?Budget $exceptCategory = null,
        string $attribute = 'allocated_amount',
    ): void {
        $summary = $this->summary($entity, $exceptOperating, $exceptCategory);
        $projectedPlanned = $summary['planned_amount'] + $amount;
        $projectedReserved = max($projectedPlanned - $summary['realized_amount'], 0.0);

        if ($projectedReserved - $summary['cash_balance'] > 0.009) {
            throw ValidationException::withMessages([
                $attribute => self::OVER_ALLOCATION_MESSAGE,
            ]);
        }
    }

    public function lockEntity(FinanceEntity $entity): FinanceEntity
    {
        return FinanceEntity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array{
     *     cash_balance: float,
     *     planned_amount: float,
     *     realized_amount: float,
     *     reserved_remaining: float,
     *     available_balance: float,
     *     uses_operating_pagu: bool
     * }
     */
    private function payload(float $cash, float $planned, float $realized, bool $usesOperatingPagu): array
    {
        $reserved = max($planned - $realized, 0.0);

        return [
            'cash_balance' => $cash,
            'planned_amount' => $planned,
            'realized_amount' => $realized,
            'reserved_remaining' => $reserved,
            'available_balance' => $cash - $reserved,
            'uses_operating_pagu' => $usesOperatingPagu,
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function operatingPagu(FinanceEntity $entity, ?PlantationOperatingBudget $exceptOperating): array
    {
        $budgets = PlantationOperatingBudget::query()
            ->where('finance_entity_id', $entity->id)
            ->get(['id', 'allocated_amount', 'period_start', 'period_end']);

        $planned = (float) $budgets
            ->when(
                $exceptOperating instanceof PlantationOperatingBudget,
                fn (Collection $rows) => $rows->reject(
                    fn (PlantationOperatingBudget $row) => (int) $row->id === (int) $exceptOperating->id
                )
            )
            ->sum(fn (PlantationOperatingBudget $row) => (float) $row->allocated_amount);

        return [$planned, $this->realizedInOperatingPeriods($entity, $budgets)];
    }

    /**
     * @param  Collection<int, PlantationOperatingBudget>  $budgets
     */
    private function realizedInOperatingPeriods(FinanceEntity $entity, Collection $budgets): float
    {
        if ($budgets->isEmpty()) {
            return 0.0;
        }

        return (float) BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->where(function ($query) use ($budgets): void {
                foreach ($budgets as $budget) {
                    $query->orWhere(function ($inner) use ($budget): void {
                        $inner->whereDate('activity_date', '>=', $budget->period_start)
                            ->whereDate('activity_date', '<=', $budget->period_end);
                    });
                }
            })
            ->sum('amount');
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function categoryPagu(FinanceEntity $entity, ?Budget $exceptCategory): array
    {
        $plannedQuery = $entity->budgets();

        if ($exceptCategory instanceof Budget) {
            $plannedQuery->whereKeyNot($exceptCategory->id);
        }

        $planned = (float) $plannedQuery->sum('amount');
        $realized = (float) BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->sum('amount');

        return [$planned, $realized];
    }

    public function withEntityLock(FinanceEntity $entity, callable $callback): mixed
    {
        return DB::transaction(function () use ($entity, $callback) {
            return $callback($this->lockEntity($entity));
        });
    }
}
