<?php

namespace App\Http\Controllers;

use App\Models\BudgetActivity;
use App\Models\FinanceEntity;
use App\Services\BusinessProfitService;
use App\Services\EntityReportService;
use App\Services\Insight\EntityFinancialInsightService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntityDashboardController extends Controller
{
    public function __construct(
        private readonly EntityReportService $reports,
        private readonly EntityFinancialInsightService $insight,
    ) {}

    public function show(Request $request, FinanceEntity $financeEntity): View
    {
        $dashboard = $this->reports->dashboardMetrics($financeEntity);
        [$from, $to] = app(BusinessProfitService::class)->currentMonthBounds();
        $month = $this->reports->report($financeEntity, $from, $to);
        $chartYear = $this->chartYear($request);
        $monthlyCashFlow = $this->reports->monthlyCashFlow($financeEntity, $chartYear);

        if ($financeEntity->isFamily()) {
            $monthIncome = (float) $month['cash_flow']['income'];
            $monthExpense = (float) $month['cash_flow']['transactions'];
        } else {
            $monthIncome = (float) ($month['business']['revenue'] ?? 0);
            $monthExpense = (float) ($month['business']['operational_expense'] ?? 0);
        }

        return view('entity.dashboard', [
            'title' => $financeEntity->name,
            'entity' => $financeEntity,
            'metrics' => $dashboard['metrics'],
            'totalSaldo' => $dashboard['totalSaldo'],
            'periodLabel' => now()->locale('id')->translatedFormat('F Y'),
            'monthCashflow' => [
                'income' => $monthIncome,
                'expense' => $monthExpense,
                'net' => $monthIncome - $monthExpense,
            ],
            'cashflowSeries' => $this->cashflowSeries($financeEntity, $from, $to),
            'expenseComposition' => $this->expenseComposition($financeEntity, $from, $to),
            'recentActivity' => array_slice($this->reports->movements($financeEntity), 0, 5),
            'chartYear' => $chartYear,
            'chartYears' => $this->chartYears(),
            'monthlyCashFlow' => $monthlyCashFlow,
            'annualCashFlow' => [
                'income' => array_sum(array_column($monthlyCashFlow, 'income')),
                'expense' => array_sum(array_column($monthlyCashFlow, 'expense')),
                'net' => array_sum(array_column($monthlyCashFlow, 'net')),
            ],
            'insightPreview' => $this->insight->dashboardPreview($financeEntity),
            'monthNames' => [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
            ],
        ]);
    }

    private function chartYear(Request $request): int
    {
        $year = (int) $request->query('chart_year', now()->year);

        if ($year < 2000 || $year > 2100) {
            return (int) now()->year;
        }

        return $year;
    }

    /**
     * @return list<int>
     */
    private function chartYears(): array
    {
        $current = (int) now()->year;

        return range(min(2024, $current - 2), max(2026, $current));
    }

    /**
     * @return list<array{label: string, income: float, expense: float}>
     */
    private function cashflowSeries(FinanceEntity $entity, string $from, string $to): array
    {
        $incomes = $entity->incomes()
            ->whereDate('income_date', '>=', $from)
            ->whereDate('income_date', '<=', $to)
            ->get(['income_date', 'amount']);

        if ($entity->isFamily()) {
            $expenses = $entity->transactions()
                ->whereDate('transaction_date', '>=', $from)
                ->whereDate('transaction_date', '<=', $to)
                ->get(['transaction_date', 'amount']);
        } else {
            $expenses = BudgetActivity::query()
                ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
                ->whereDate('activity_date', '>=', $from)
                ->whereDate('activity_date', '<=', $to)
                ->get(['activity_date', 'amount']);
        }

        if ($incomes->isEmpty() && $expenses->isEmpty()) {
            return [];
        }

        $days = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->min(now())->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $days[$key] = [
                'label' => $cursor->format('d M'),
                'income' => 0.0,
                'expense' => 0.0,
            ];
            $cursor->addDay();
        }

        foreach ($incomes as $income) {
            $key = optional($income->income_date)?->toDateString();
            if ($key && isset($days[$key])) {
                $days[$key]['income'] += (float) $income->amount;
            }
        }

        foreach ($expenses as $expense) {
            $date = $expense->transaction_date ?? $expense->activity_date ?? null;
            $key = $date instanceof Carbon ? $date->toDateString() : (is_string($date) ? Carbon::parse($date)->toDateString() : null);
            if ($key && isset($days[$key])) {
                $days[$key]['expense'] += (float) $expense->amount;
            }
        }

        return array_values($days);
    }

    /**
     * @return list<array{name: string, total: float}>
     */
    private function expenseComposition(FinanceEntity $entity, string $from, string $to): array
    {
        if ($entity->isFamily()) {
            $rows = $entity->transactions()
                ->with('category')
                ->whereDate('transaction_date', '>=', $from)
                ->whereDate('transaction_date', '<=', $to)
                ->get();
        } else {
            $rows = BudgetActivity::query()
                ->with('budget.category')
                ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
                ->whereDate('activity_date', '>=', $from)
                ->whereDate('activity_date', '<=', $to)
                ->get();
        }

        return $rows
            ->groupBy(function ($row) use ($entity) {
                if ($entity->isFamily()) {
                    return $row->category?->name ?: 'Lainnya';
                }

                return $row->budget?->category?->name ?: 'Lainnya';
            })
            ->map(fn ($group, $name) => [
                'name' => (string) $name,
                'total' => (float) $group->sum('amount'),
            ])
            ->values()
            ->all();
    }
}
