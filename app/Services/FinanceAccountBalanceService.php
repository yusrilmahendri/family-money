<?php

namespace App\Services;

use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\DebtPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\ReceivablePayment;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceAccountBalanceService
{
    /**
     * Derived cash position for one account.
     *
     * opening + income + transfer_in + capital_in + withdrawal_in + distribution_in + receivable_in
     * - transaction - debt_payment - goal_contribution - budget_activity
     * - transfer_out - capital_out - withdrawal_out - distribution_out
     *
     * Budget headers are not outflow. Transfers, capital, prive, profit distribution, and unpaid
     * receivable principal are not income/expense. Only receivable payments are cash inflow.
     * Inactive accounts still count. saldos / SaldoGlobalService are never included.
     */
    public function balance(FinanceAccount $account): float
    {
        return $this->breakdown($account)['balance'];
    }

    /**
     * @return array{
     *     opening_balance: float,
     *     income: float,
     *     transactions: float,
     *     debt_payments: float,
     *     goal_contributions: float,
     *     budget_activities: float,
     *     transfer_in: float,
     *     transfer_out: float,
     *     capital_in: float,
     *     capital_out: float,
     *     withdrawal_in: float,
     *     withdrawal_out: float,
     *     distribution_in: float,
     *     distribution_out: float,
     *     receivable_in: float,
     *     expense_outflow: float,
     *     inflow: float,
     *     outflow: float,
     *     balance: float
     * }
     */
    public function breakdown(FinanceAccount $account): array
    {
        $entityId = (int) $account->finance_entity_id;
        $accountId = (int) $account->id;

        $income = (float) Income::query()
            ->where('finance_account_id', $accountId)
            ->where('finance_entity_id', $entityId)
            ->sum('amount');

        $transactions = (float) Transaction::query()
            ->where('finance_account_id', $accountId)
            ->where('finance_entity_id', $entityId)
            ->sum('amount');

        $debtPayments = (float) DebtPayment::query()
            ->where('finance_account_id', $accountId)
            ->whereHas('debt', fn ($query) => $query->where('finance_entity_id', $entityId))
            ->sum('amount');

        $goalContributions = (float) GoalContribution::query()
            ->where('finance_account_id', $accountId)
            ->whereHas('savingsGoal', fn ($query) => $query->where('finance_entity_id', $entityId))
            ->sum('amount');

        $budgetActivities = (float) BudgetActivity::query()
            ->where('finance_account_id', $accountId)
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entityId))
            ->sum('amount');

        $transferIn = (float) FinanceTransfer::query()
            ->where('finance_entity_id', $entityId)
            ->where('destination_account_id', $accountId)
            ->sum('amount');

        $transferOut = (float) FinanceTransfer::query()
            ->where('finance_entity_id', $entityId)
            ->where('source_account_id', $accountId)
            ->sum('amount');

        $capitalIn = (float) BusinessCapitalContribution::query()
            ->where('business_entity_id', $entityId)
            ->where('destination_account_id', $accountId)
            ->sum('amount');

        $capitalOut = (float) BusinessCapitalContribution::query()
            ->where('source_entity_id', $entityId)
            ->where('source_account_id', $accountId)
            ->sum('amount');

        $withdrawalIn = (float) OwnerWithdrawal::query()
            ->where('family_entity_id', $entityId)
            ->where('destination_account_id', $accountId)
            ->sum('amount');

        $withdrawalOut = (float) OwnerWithdrawal::query()
            ->where('business_entity_id', $entityId)
            ->where('source_account_id', $accountId)
            ->sum('amount');

        $distributionIn = (float) ProfitDistribution::query()
            ->where('family_entity_id', $entityId)
            ->where('destination_account_id', $accountId)
            ->sum('amount');

        $distributionOut = (float) ProfitDistribution::query()
            ->where('business_entity_id', $entityId)
            ->where('source_account_id', $accountId)
            ->sum('amount');

        $receivableIn = (float) ReceivablePayment::query()
            ->where('finance_account_id', $accountId)
            ->whereHas('receivable', fn ($query) => $query->where('finance_entity_id', $entityId))
            ->sum('amount');

        $opening = (float) $account->opening_balance;
        $expenseOutflow = $transactions + $debtPayments + $goalContributions + $budgetActivities;
        $inflow = $income + $transferIn + $capitalIn + $withdrawalIn + $distributionIn + $receivableIn;
        $outflow = $expenseOutflow + $transferOut + $capitalOut + $withdrawalOut + $distributionOut;

        return [
            'opening_balance' => $opening,
            'income' => $income,
            'transactions' => $transactions,
            'debt_payments' => $debtPayments,
            'goal_contributions' => $goalContributions,
            'budget_activities' => $budgetActivities,
            'transfer_in' => $transferIn,
            'transfer_out' => $transferOut,
            'capital_in' => $capitalIn,
            'capital_out' => $capitalOut,
            'withdrawal_in' => $withdrawalIn,
            'withdrawal_out' => $withdrawalOut,
            'distribution_in' => $distributionIn,
            'distribution_out' => $distributionOut,
            'receivable_in' => $receivableIn,
            'expense_outflow' => $expenseOutflow,
            'inflow' => $inflow,
            'outflow' => $outflow,
            'balance' => $opening + $inflow - $outflow,
        ];
    }

    public function balanceForEntity(FinanceEntity $entity): float
    {
        return (float) $this->summary($entity)['total'];
    }

    /**
     * Entity income/expense totals exclude transfers so dashboard P/L stays clean.
     * Entity balance still includes transfers; they cancel across accounts.
     *
     * @return array{inflow: float, outflow: float, opening_balance: float, balance: float, transfer_in: float, transfer_out: float, capital_in: float, capital_out: float, withdrawal_in: float, withdrawal_out: float, distribution_in: float, distribution_out: float, receivable_in: float}
     */
    public function totals(FinanceEntity $entity): array
    {
        $summary = $this->summary($entity);

        return [
            'inflow' => (float) $summary['rows']->sum('income'),
            'outflow' => (float) $summary['rows']->sum('expense_outflow'),
            'opening_balance' => (float) $summary['rows']->sum('opening_balance'),
            'balance' => (float) $summary['total'],
            'transfer_in' => (float) $summary['rows']->sum('transfer_in'),
            'transfer_out' => (float) $summary['rows']->sum('transfer_out'),
            'capital_in' => (float) $summary['rows']->sum('capital_in'),
            'capital_out' => (float) $summary['rows']->sum('capital_out'),
            'withdrawal_in' => (float) $summary['rows']->sum('withdrawal_in'),
            'withdrawal_out' => (float) $summary['rows']->sum('withdrawal_out'),
            'distribution_in' => (float) $summary['rows']->sum('distribution_in'),
            'distribution_out' => (float) $summary['rows']->sum('distribution_out'),
            'receivable_in' => (float) $summary['rows']->sum('receivable_in'),
        ];
    }

    /**
     * @return array{
     *     accounts: Collection<int, FinanceAccount>,
     *     rows: Collection<int, array<string, mixed>>,
     *     total: float
     * }
     */
    public function summary(FinanceEntity $entity): array
    {
        $accounts = $entity->accounts()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $totals = $this->movementTotalsForEntity($entity, $accounts->pluck('id'));

        $rows = $accounts->map(function (FinanceAccount $account) use ($totals) {
            $id = (int) $account->id;
            $opening = (float) $account->opening_balance;
            $income = (float) ($totals['income'][$id] ?? 0);
            $transferIn = (float) ($totals['transfer_in'][$id] ?? 0);
            $transferOut = (float) ($totals['transfer_out'][$id] ?? 0);
            $capitalIn = (float) ($totals['capital_in'][$id] ?? 0);
            $capitalOut = (float) ($totals['capital_out'][$id] ?? 0);
            $withdrawalIn = (float) ($totals['withdrawal_in'][$id] ?? 0);
            $withdrawalOut = (float) ($totals['withdrawal_out'][$id] ?? 0);
            $distributionIn = (float) ($totals['distribution_in'][$id] ?? 0);
            $distributionOut = (float) ($totals['distribution_out'][$id] ?? 0);
            $receivableIn = (float) ($totals['receivable_in'][$id] ?? 0);
            $expenseOutflow = (float) ($totals['transactions'][$id] ?? 0)
                + (float) ($totals['debt_payments'][$id] ?? 0)
                + (float) ($totals['goal_contributions'][$id] ?? 0)
                + (float) ($totals['budget_activities'][$id] ?? 0);
            $inflow = $income + $transferIn + $capitalIn + $withdrawalIn + $distributionIn + $receivableIn;
            $outflow = $expenseOutflow + $transferOut + $capitalOut + $withdrawalOut + $distributionOut;
            $balance = $opening + $inflow - $outflow;

            $account->setAttribute('current_balance', $balance);

            return [
                'account' => $account,
                'opening_balance' => $opening,
                'income' => $income,
                'transfer_in' => $transferIn,
                'transfer_out' => $transferOut,
                'capital_in' => $capitalIn,
                'capital_out' => $capitalOut,
                'withdrawal_in' => $withdrawalIn,
                'withdrawal_out' => $withdrawalOut,
                'distribution_in' => $distributionIn,
                'distribution_out' => $distributionOut,
                'receivable_in' => $receivableIn,
                'expense_outflow' => $expenseOutflow,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'balance' => $balance,
            ];
        });

        return [
            'accounts' => $accounts,
            'rows' => $rows,
            'total' => (float) $rows->sum('balance'),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $accountIds
     * @return array<string, array<int, float>>
     */
    private function movementTotalsForEntity(FinanceEntity $entity, Collection $accountIds): array
    {
        $ids = $accountIds->map(fn ($id) => (int) $id)->filter()->values();

        if ($ids->isEmpty()) {
            return [
                'income' => [],
                'transactions' => [],
                'debt_payments' => [],
                'goal_contributions' => [],
                'budget_activities' => [],
                'transfer_in' => [],
                'transfer_out' => [],
                'capital_in' => [],
                'capital_out' => [],
                'withdrawal_in' => [],
                'withdrawal_out' => [],
                'distribution_in' => [],
                'distribution_out' => [],
                'receivable_in' => [],
            ];
        }

        $entityId = (int) $entity->id;

        return [
            'income' => $this->sumOwned($entityId, $ids, 'incomes'),
            'transactions' => $this->sumOwned($entityId, $ids, 'transactions'),
            'debt_payments' => $this->sumChild($ids, 'debt_payments', 'debts', 'debt_id', $entityId),
            'goal_contributions' => $this->sumChild($ids, 'goal_contributions', 'savings_goals', 'savings_goal_id', $entityId),
            'budget_activities' => $this->sumChild($ids, 'budget_activities', 'budgets', 'budget_id', $entityId),
            'transfer_in' => $this->sumTransfers($entityId, $ids, 'destination_account_id'),
            'transfer_out' => $this->sumTransfers($entityId, $ids, 'source_account_id'),
            'capital_in' => $this->sumCapital($ids, 'destination_account_id', 'business_entity_id', $entityId),
            'capital_out' => $this->sumCapital($ids, 'source_account_id', 'source_entity_id', $entityId),
            'withdrawal_in' => $this->sumWithdrawals($ids, 'destination_account_id', 'family_entity_id', $entityId),
            'withdrawal_out' => $this->sumWithdrawals($ids, 'source_account_id', 'business_entity_id', $entityId),
            'distribution_in' => $this->sumDistributions($ids, 'destination_account_id', 'family_entity_id', $entityId),
            'distribution_out' => $this->sumDistributions($ids, 'source_account_id', 'business_entity_id', $entityId),
            'receivable_in' => $this->sumChild($ids, 'receivable_payments', 'receivables', 'receivable_id', $entityId),
        ];
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sumOwned(int $entityId, Collection $accountIds, string $table): array
    {
        return DB::table($table)
            ->select('finance_account_id', DB::raw('SUM(amount) as total'))
            ->where('finance_entity_id', $entityId)
            ->whereIn('finance_account_id', $accountIds)
            ->groupBy('finance_account_id')
            ->pluck('total', 'finance_account_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sumChild(
        Collection $accountIds,
        string $table,
        string $parent,
        string $fk,
        int $entityId
    ): array {
        return DB::table($table)
            ->select($table.'.finance_account_id', DB::raw('SUM('.$table.'.amount) as total'))
            ->join($parent, $parent.'.id', '=', $table.'.'.$fk)
            ->where($parent.'.finance_entity_id', $entityId)
            ->whereIn($table.'.finance_account_id', $accountIds)
            ->groupBy($table.'.finance_account_id')
            ->pluck('total', 'finance_account_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sumTransfers(int $entityId, Collection $accountIds, string $column): array
    {
        return DB::table('finance_transfers')
            ->select($column, DB::raw('SUM(amount) as total'))
            ->where('finance_entity_id', $entityId)
            ->whereIn($column, $accountIds)
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sumCapital(Collection $accountIds, string $accountColumn, string $entityColumn, int $entityId): array
    {
        return DB::table('business_capital_contributions')
            ->select($accountColumn, DB::raw('SUM(amount) as total'))
            ->where($entityColumn, $entityId)
            ->whereIn($accountColumn, $accountIds)
            ->groupBy($accountColumn)
            ->pluck('total', $accountColumn)
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sumWithdrawals(Collection $accountIds, string $accountColumn, string $entityColumn, int $entityId): array
    {
        return DB::table('owner_withdrawals')
            ->select($accountColumn, DB::raw('SUM(amount) as total'))
            ->where($entityColumn, $entityId)
            ->whereIn($accountColumn, $accountIds)
            ->groupBy($accountColumn)
            ->pluck('total', $accountColumn)
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sumDistributions(Collection $accountIds, string $accountColumn, string $entityColumn, int $entityId): array
    {
        return DB::table('profit_distributions')
            ->select($accountColumn, DB::raw('SUM(amount) as total'))
            ->where($entityColumn, $entityId)
            ->whereIn($accountColumn, $accountIds)
            ->groupBy($accountColumn)
            ->pluck('total', $accountColumn)
            ->map(fn ($value) => (float) $value)
            ->all();
    }
}
