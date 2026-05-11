<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Service\FinancialPlannerService;
use Carbon\Carbon;

class FinancialPlannerController extends Controller
{
    public function __construct(
        protected FinancialPlannerService $plannerService
    ) {}

    public function index()
    {
        $now = Carbon::now();

        $daily = $this->plannerService->dailyExpenseTotals(14);
        $dailyLabels = array_keys($daily);
        $dailyValues = array_values($daily);

        $cashflow = $this->plannerService->monthlyCashflow(12);
        $goalFlow = $this->plannerService->monthlyGoalContributions(12);

        $budgetCap = (float) Budget::query()
            ->whereYear('periode', $now->year)
            ->whereMonth('periode', $now->month)
            ->sum('amount');

        if ($budgetCap <= 0) {
            $budgetCap = (float) (Budget::latest('periode')->value('amount') ?? 0);
        }

        $spentMonth = (float) Transaction::query()
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum('amount');

        $debts = Debt::query()->orderBy('title')->get();
        $goals = SavingsGoal::query()->orderBy('title')->get();

        $todaySpend = (float) Transaction::query()
            ->whereDate('transaction_date', $now->toDateString())
            ->sum('amount');

        return view('financial_planner.index', [
            'title' => 'Financial planner',
            'daily_labels' => $dailyLabels,
            'daily_values' => $dailyValues,
            'cashflow' => $cashflow,
            'goal_flow' => $goalFlow,
            'budget_cap' => $budgetCap,
            'spent_month' => $spentMonth,
            'debts' => $debts,
            'goals' => $goals,
            'today_spend' => $todaySpend,
            'over_budget' => $budgetCap > 0 && $spentMonth > $budgetCap,
        ]);
    }
}
