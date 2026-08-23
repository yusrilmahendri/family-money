<?php

namespace App\Services;

use App\Models\BudgetActivity;
use App\Models\FinanceEntity;
use App\Models\ProfitDistribution;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BusinessProfitService
{
    /**
     * Operational profit for one BUSINESS entity.
     *
     * revenue = Income (income_date)
     * operational_expense = BudgetActivity (activity_date) owned via budget.finance_entity_id
     * profit = revenue - operational_expense
     *
     * Opening balance, transfers, capital, prive, and budget.amount are excluded.
     *
     * @return array{revenue: float, operational_expense: float, profit: float, from: ?string, to: ?string}
     */
    public function calculate(FinanceEntity $business, mixed $from = null, mixed $to = null): array
    {
        $this->assertBusiness($business);
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);

        $revenue = (float) $this->incomeQuery($business, $fromDate, $toDate)->sum('amount');
        $operationalExpense = (float) $this->expenseQuery($business, $fromDate, $toDate)->sum('amount');

        return [
            'revenue' => $revenue,
            'operational_expense' => $operationalExpense,
            'profit' => $revenue - $operationalExpense,
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    /**
     * @return array{
     *     revenue: float,
     *     operational_expense: float,
     *     profit: float,
     *     from: ?string,
     *     to: ?string,
     *     period_label: string,
     *     is_loss: bool,
     *     categories: Collection<int, array{name: string, revenue: float, operational_expense: float, profit: float}>,
     *     capital_in: float,
     *     withdrawal_out: float,
     *     distributed_profit: float,
     *     undistributed_profit: float
     * }
     */
    public function summary(FinanceEntity $business, mixed $from = null, mixed $to = null): array
    {
        $calculated = $this->calculate($business, $from, $to);
        $fromDate = $calculated['from'];
        $toDate = $calculated['to'];

        $rows = $business->categories()->orderBy('name')->get()->map(function ($category) use ($business, $fromDate, $toDate) {
            $revenue = (float) $this->incomeQuery($business, $fromDate, $toDate)
                ->where('category_id', $category->id)
                ->sum('amount');
            $expense = (float) $this->expenseQuery($business, $fromDate, $toDate)
                ->whereHas('budget', fn ($query) => $query->where('category_id', $category->id))
                ->sum('amount');

            return [
                'name' => $category->name,
                'revenue' => $revenue,
                'operational_expense' => $expense,
                'profit' => $revenue - $expense,
            ];
        })->filter(fn (array $row) => $row['revenue'] > 0 || $row['operational_expense'] > 0)->values();

        $capital = $business->capitalContributionsReceived();
        $withdrawals = $business->ownerWithdrawalsGiven();
        $this->constrainDate($capital, 'transaction_date', $fromDate, $toDate);
        $this->constrainDate($withdrawals, 'transaction_date', $fromDate, $toDate);

        $distributed = $this->distributedProfit($business, $fromDate, $toDate);

        return [
            ...$calculated,
            'period_label' => $this->periodLabel($fromDate, $toDate),
            'is_loss' => $calculated['profit'] < 0,
            'categories' => $rows,
            'capital_in' => (float) $capital->sum('amount'),
            'withdrawal_out' => (float) $withdrawals->sum('amount'),
            'distributed_profit' => $distributed,
            'undistributed_profit' => $calculated['profit'] - $distributed,
        ];
    }

    /**
     * @return array{revenue: float, operational_expense: float, profit: float, from: ?string, to: ?string}
     */
    public function currentMonth(FinanceEntity $business): array
    {
        return $this->calculate($business, now()->startOfMonth(), now()->endOfMonth());
    }

    /**
     * Inclusive current-month bounds as Y-m-d.
     *
     * @return array{0: string, 1: string}
     */
    public function currentMonthBounds(): array
    {
        return [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ];
    }

    public function periodLabel(?string $from, ?string $to): string
    {
        if ($from === null && $to === null) {
            return 'Semua waktu';
        }

        return ($from ?? '…').' – '.($to ?? '…');
    }

    private function assertBusiness(FinanceEntity $entity): void
    {
        if (! $entity->isBusiness()) {
            throw ValidationException::withMessages([
                'finance_entity_id' => 'Laba usaha hanya dihitung untuk BUSINESS.',
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeRange(mixed $from, mixed $to): array
    {
        $fromDate = $this->normalizeDate($from);
        $toDate = $this->normalizeDate($to);

        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            throw ValidationException::withMessages([
                'to' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            ]);
        }

        return [$fromDate, $toDate];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'from' => 'Tanggal periode tidak valid.',
            ]);
        }
    }

    private function incomeQuery(FinanceEntity $business, ?string $from, ?string $to)
    {
        $query = $business->incomes();
        $this->constrainDate($query, 'income_date', $from, $to);

        return $query;
    }

    private function expenseQuery(FinanceEntity $business, ?string $from, ?string $to)
    {
        $query = BudgetActivity::query()
            ->whereHas('budget', fn ($q) => $q->where('finance_entity_id', $business->id));
        $this->constrainDate($query, 'activity_date', $from, $to);

        return $query;
    }

    private function distributedProfit(FinanceEntity $business, ?string $from, ?string $to): float
    {
        $query = ProfitDistribution::query()->where('business_entity_id', $business->id);

        if ($from === null && $to === null) {
            return (float) $query->sum('amount');
        }

        return (float) $query
            ->whereDate('period_start', $from)
            ->whereDate('period_end', $to)
            ->sum('amount');
    }

    private function constrainDate(mixed $query, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate($column, '<=', $to);
        }
    }
}
