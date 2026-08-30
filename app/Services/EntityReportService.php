<?php

namespace App\Services;

use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\DebtPayment;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use App\Models\GoalContribution;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\ReceivablePayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntityReportService
{
    public function __construct(
        private readonly FinanceAccountBalanceService $balances,
        private readonly BusinessProfitService $profits,
        private readonly ReceivableService $receivables,
    ) {}

    /**
     * Single source of truth for dashboard, report, export, and AI.
     *
     * Balance and outstanding stocks are current (all-time derived).
     * Operating Total Saldo sums ACTIVE accounts only. Historical movements keep inactive accounts.
     * Flow metrics use the inclusive domain-date range.
     *
     * @return array<string, mixed>
     */
    public function report(FinanceEntity $entity, mixed $from = null, mixed $to = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);
        $totals = $this->balances->totals($entity);
        $summary = $this->balances->summary($entity);
        $flows = $this->periodFlows($entity, $fromDate, $toDate);

        $payload = [
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'entity_public_id' => $entity->public_id,
            'entity_type' => $entity->type->value,
            'from' => $fromDate,
            'to' => $toDate,
            'period_label' => $this->profits->periodLabel($fromDate, $toDate),
            'balance_total' => $totals['balance'],
            'accounts' => $this->accountRows($summary['rows']),
            'cash_flow' => $flows,
            'piutang_outstanding' => $this->receivables->outstandingTotal($entity),
            'piutang_overdue' => $this->receivables->overdueOutstanding($entity),
            'movements' => $this->movements($entity, $fromDate, $toDate),
        ];

        if ($entity->isFamily()) {
            $payload['family'] = $this->familyMetrics($entity, $fromDate, $toDate, $flows);
        } else {
            $payload['business'] = $this->businessMetrics($entity, $fromDate, $toDate);
        }

        return $payload;
    }

    /**
     * Dashboard widgets reuse the same report numbers for the matching period.
     *
     * @return array{totalSaldo: float, metrics: array<string, mixed>}
     */
    public function dashboardMetrics(FinanceEntity $entity): array
    {
        $lifetime = $this->report($entity);
        [$monthFrom, $monthTo] = $this->profits->currentMonthBounds();
        $month = $this->report($entity, $monthFrom, $monthTo);

        if ($entity->isFamily()) {
            return [
                'totalSaldo' => $lifetime['balance_total'],
                'metrics' => [
                    'pemasukan' => $lifetime['family']['pemasukan'],
                    'pengeluaran' => $lifetime['family']['pengeluaran'],
                    'pengeluaran_bulan_ini' => $month['cash_flow']['transactions'],
                    'jumlah_transaksi' => $entity->transactions()->count(),
                    'jumlah_utang' => $entity->debts()->count(),
                    'jumlah_goal' => $entity->savingsGoals()->count(),
                    'modal_ke_usaha' => $lifetime['family']['modal_ke_usaha'],
                    'penerimaan_prive' => $lifetime['family']['penerimaan_prive'],
                    'penerimaan_laba' => $lifetime['family']['penerimaan_laba'],
                    'piutang_outstanding' => $lifetime['piutang_outstanding'],
                    'piutang_jatuh_tempo' => $lifetime['piutang_overdue'],
                    'hutang_outstanding' => $lifetime['family']['hutang_outstanding'],
                    'tabungan' => $lifetime['family']['tabungan'],
                ],
            ];
        }

        return [
            'totalSaldo' => $lifetime['balance_total'],
            'metrics' => [
                'pemasukan_bulan_ini' => $month['business']['revenue'],
                'biaya_bulan_ini' => $month['business']['operational_expense'],
                'laba_bulan_ini' => $month['business']['profit'],
                'total_pemasukan' => $lifetime['business']['revenue'],
                'total_biaya_operasional' => $lifetime['business']['operational_expense'],
                'laba' => $lifetime['business']['profit'],
                'distributed_profit' => $lifetime['business']['profit_distributed'],
                'undistributed_profit' => $lifetime['business']['undistributed_profit'],
                'anggaran_planned' => $lifetime['business']['budget_planned'],
                'anggaran_realized' => $lifetime['business']['budget_realized'],
                'anggaran_remaining' => $lifetime['business']['budget_planned'] - $lifetime['business']['budget_realized'],
                'jumlah_anggaran' => $entity->budgets()->count(),
                'total_modal' => $lifetime['business']['capital_received'],
                'prive' => $lifetime['business']['prive'],
                'piutang_outstanding' => $lifetime['piutang_outstanding'],
                'piutang_jatuh_tempo' => $lifetime['piutang_overdue'],
            ],
        ];
    }

    /**
     * Entity-level monthly cash flow for one calendar year.
     *
     * Income and expense follow periodFlows cash movements, excluding internal
     * transfers, opening balance, unpaid receivable principal, budget headers,
     * and outstanding debt. Net = income − expense.
     *
     * @return list<array{month: int, income: float, expense: float, net: float}>
     */
    public function monthlyCashFlow(FinanceEntity $entity, int $year): array
    {
        $income = $this->addMonthMaps(
            $this->monthSums($entity->incomes(), 'income_date', $year),
            $this->monthSums($entity->capitalContributionsReceived(), 'transaction_date', $year),
            $this->monthSums($entity->ownerWithdrawalsReceived(), 'transaction_date', $year),
            $this->monthSums($entity->profitDistributionsReceived(), 'distribution_date', $year),
            $this->monthSums(
                ReceivablePayment::query()
                    ->where('status', \App\Enums\ReceivablePaymentStatus::ACTIVE)
                    ->whereHas(
                        'receivable',
                        fn ($query) => $query->where('finance_entity_id', $entity->id)
                    ),
                'payment_date',
                $year
            ),
        );

        $expense = $this->addMonthMaps(
            $this->monthSums($entity->transactions()->whereNull('reversed_at'), 'transaction_date', $year),
            $this->monthSums(
                DebtPayment::query()->whereHas(
                    'debt',
                    fn ($query) => $query->where('finance_entity_id', $entity->id)
                ),
                'paid_on',
                $year
            ),
            $this->monthSums(
                GoalContribution::query()->whereHas(
                    'savingsGoal',
                    fn ($query) => $query->where('finance_entity_id', $entity->id)
                ),
                'contributed_on',
                $year
            ),
            $this->monthSums(
                BudgetActivity::query()->whereHas(
                    'budget',
                    fn ($query) => $query->where('finance_entity_id', $entity->id)
                ),
                'activity_date',
                $year
            ),
            $this->monthSums($entity->capitalContributionsGiven(), 'transaction_date', $year),
            $this->monthSums($entity->ownerWithdrawalsGiven(), 'transaction_date', $year),
            $this->monthSums($entity->profitDistributionsGiven(), 'distribution_date', $year),
        );

        $rows = [];

        for ($month = 1; $month <= 12; $month++) {
            $in = $income[$month];
            $out = $expense[$month];
            $rows[] = [
                'month' => $month,
                'income' => $in,
                'expense' => $out,
                'net' => $in - $out,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function movements(FinanceEntity $entity, mixed $from = null, mixed $to = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);
        $rows = collect();

        $entity->incomes()->with('financeAccount')
            ->when($fromDate, fn ($query) => $query->whereDate('income_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('income_date', '<=', $toDate))
            ->orderByDesc('income_date')
            ->get()
            ->each(function ($income) use ($rows): void {
                $rows->push([
                    'date' => optional($income->income_date)?->toDateString(),
                    'type' => 'Pemasukan',
                    'description' => $income->source,
                    'detail_description' => null,
                    'account' => $income->financeAccount?->name,
                    'amount' => (float) $income->amount,
                    'direction' => 'in',
                ]);
            });

        $entity->transactions()->with('financeAccount')
            ->when($fromDate, fn ($query) => $query->whereDate('transaction_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('transaction_date', '<=', $toDate))
            ->orderByDesc('transaction_date')
            ->get()
            ->each(function ($transaction) use ($rows): void {
                $rows->push([
                    'date' => optional($transaction->transaction_date)?->toDateString(),
                    'type' => 'Pengeluaran',
                    'description' => $transaction->description,
                    'detail_description' => $transaction->resolvedDetailDescription(),
                    'account' => $transaction->financeAccount?->name,
                    'amount' => (float) $transaction->amount,
                    'direction' => 'out',
                ]);
            });

        BudgetActivity::query()
            ->with(['financeAccount', 'budget'])
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($fromDate, fn ($query) => $query->whereDate('activity_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('activity_date', '<=', $toDate))
            ->orderByDesc('activity_date')
            ->get()
            ->each(function ($activity) use ($rows): void {
                $rows->push([
                    'date' => optional($activity->activity_date)?->toDateString(),
                    'type' => 'Biaya operasional',
                    'description' => $activity->name,
                    'detail_description' => null,
                    'account' => $activity->financeAccount?->name,
                    'amount' => (float) $activity->amount,
                    'direction' => 'out',
                ]);
            });

        $entity->transfers()->with(['sourceAccount', 'destinationAccount'])
            ->when($fromDate, fn ($query) => $query->whereDate('transaction_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('transaction_date', '<=', $toDate))
            ->orderByDesc('transaction_date')
            ->get()
            ->each(function (FinanceTransfer $transfer) use ($rows): void {
                $rows->push([
                    'date' => optional($transfer->transaction_date)?->toDateString(),
                    'type' => 'Transfer',
                    'description' => ($transfer->sourceAccount?->name ?? '—').' → '.($transfer->destinationAccount?->name ?? '—'),
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $transfer->amount,
                    'direction' => 'internal',
                ]);
            });

        $this->crossEntityMovements($entity, $fromDate, $toDate)->each(fn (array $row) => $rows->push($row));

        ReceivablePayment::query()
            ->with(['financeAccount', 'receivable'])
            ->whereHas('receivable', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($fromDate, fn ($query) => $query->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('payment_date', '<=', $toDate))
            ->orderByDesc('payment_date')
            ->get()
            ->each(function ($payment) use ($rows): void {
                $rows->push([
                    'date' => optional($payment->payment_date)?->toDateString(),
                    'type' => 'Pembayaran piutang',
                    'description' => $payment->receivable?->party_name,
                    'detail_description' => null,
                    'account' => $payment->financeAccount?->name,
                    'amount' => (float) $payment->amount,
                    'direction' => 'in',
                ]);
            });

        return $rows
            ->sortByDesc(fn (array $row) => $row['date'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function normalizeRange(mixed $from, mixed $to): array
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

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function accountRows(Collection $rows): array
    {
        return $rows->map(fn (array $row) => [
            'name' => $row['account']->name,
            'type' => $row['account']->type->value,
            'is_default' => (bool) $row['account']->is_default,
            'is_active' => (bool) $row['account']->is_active,
            'account_number' => $this->maskAccountNumber($row['account']->account_number),
            'opening_balance' => (float) $row['opening_balance'],
            'balance' => (float) $row['balance'],
        ])->values()->all();
    }

    /**
     * @param  array<string, float>  $flows
     * @return array<string, float>
     */
    private function familyMetrics(FinanceEntity $entity, ?string $from, ?string $to, array $flows): array
    {
        $debtOutstanding = (float) $entity->debts()->sum('remaining_balance');
        $tabungan = (float) GoalContribution::query()
            ->whereHas('savingsGoal', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->sum('amount');

        return [
            'pemasukan' => $flows['income'],
            'pengeluaran' => $flows['expense'],
            'hutang_outstanding' => $debtOutstanding,
            'tabungan' => $tabungan,
            'modal_ke_usaha' => $flows['capital_out'],
            'penerimaan_prive' => $flows['withdrawal_in'],
            'penerimaan_laba' => $flows['distribution_in'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessMetrics(FinanceEntity $entity, ?string $from, ?string $to): array
    {
        $profit = $this->profits->summary($entity, $from, $to);
        $planned = (float) $entity->budgets()
            ->when($from, fn ($query) => $query->whereDate('periode', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('periode', '<=', $to))
            ->sum('amount');

        return [
            'revenue' => $profit['revenue'],
            'operational_expense' => $profit['operational_expense'],
            'profit' => $profit['profit'],
            'is_loss' => $profit['is_loss'],
            'budget_planned' => $planned,
            'budget_realized' => $profit['operational_expense'],
            'capital_received' => $profit['capital_in'],
            'prive' => $profit['withdrawal_out'],
            'profit_distributed' => $profit['distributed_profit'],
            'undistributed_profit' => $profit['undistributed_profit'],
            'categories' => $profit['categories'],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function periodFlows(FinanceEntity $entity, ?string $from, ?string $to): array
    {
        $income = $this->sumByDate($entity->incomes(), 'income_date', $from, $to);
        $transactions = $this->sumByDate($entity->transactions()->whereNull('reversed_at'), 'transaction_date', $from, $to);
        $debtPayments = (float) DebtPayment::query()
            ->whereHas('debt', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_on', '<=', $to))
            ->sum('amount');
        $goalContributions = (float) GoalContribution::query()
            ->whereHas('savingsGoal', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('contributed_on', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('contributed_on', '<=', $to))
            ->sum('amount');
        $budgetActivities = (float) BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('activity_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('activity_date', '<=', $to))
            ->sum('amount');

        $transfers = (float) $entity->transfers()
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->sum('amount');
        $transferIn = $transfers;
        $transferOut = $transfers;

        $capitalIn = (float) $entity->capitalContributionsReceived()
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->sum('amount');
        $capitalOut = (float) $entity->capitalContributionsGiven()
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->sum('amount');
        $withdrawalIn = (float) $entity->ownerWithdrawalsReceived()
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->sum('amount');
        $withdrawalOut = (float) $entity->ownerWithdrawalsGiven()
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->sum('amount');
        $distributionIn = (float) $entity->profitDistributionsReceived()
            ->when($from, fn ($query) => $query->whereDate('distribution_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('distribution_date', '<=', $to))
            ->sum('amount');
        $distributionOut = (float) $entity->profitDistributionsGiven()
            ->when($from, fn ($query) => $query->whereDate('distribution_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('distribution_date', '<=', $to))
            ->sum('amount');
        $receivableIn = (float) ReceivablePayment::query()
            ->where('status', \App\Enums\ReceivablePaymentStatus::ACTIVE)
            ->whereHas('receivable', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->sum('amount');

        $expense = $transactions + $debtPayments + $goalContributions + $budgetActivities;
        $cashIn = $income + $transferIn + $capitalIn + $withdrawalIn + $distributionIn + $receivableIn;
        $cashOut = $expense + $transferOut + $capitalOut + $withdrawalOut + $distributionOut;

        return [
            'income' => $income,
            'expense' => $expense,
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
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash' => $cashIn - $cashOut,
        ];
    }

    private function sumByDate(mixed $query, string $column, ?string $from, ?string $to): float
    {
        $query->when($from, fn ($builder) => $builder->whereDate($column, '>=', $from))
            ->when($to, fn ($builder) => $builder->whereDate($column, '<=', $to));

        return (float) $query->sum('amount');
    }

    /**
     * @return array<int, float>
     */
    private function monthSums(mixed $query, string $column, int $year): array
    {
        $monthSql = $this->sqlMonth($column);
        $totals = array_fill(1, 12, 0.0);

        $query
            ->whereYear($column, $year)
            ->selectRaw($monthSql.' as month_num, COALESCE(SUM(amount), 0) as total')
            ->groupByRaw($monthSql)
            ->get()
            ->each(function ($row) use (&$totals): void {
                $month = (int) $row->month_num;
                if ($month >= 1 && $month <= 12) {
                    $totals[$month] = (float) $row->total;
                }
            });

        return $totals;
    }

    /**
     * @param  array<int, float>  ...$maps
     * @return array<int, float>
     */
    private function addMonthMaps(array ...$maps): array
    {
        $totals = array_fill(1, 12, 0.0);

        foreach ($maps as $map) {
            foreach ($map as $month => $amount) {
                $totals[(int) $month] += (float) $amount;
            }
        }

        return $totals;
    }

    private function sqlMonth(string $column): string
    {
        $wrapped = DB::getQueryGrammar()->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', {$wrapped}) AS INTEGER)",
            default => 'MONTH('.$wrapped.')',
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function crossEntityMovements(FinanceEntity $entity, ?string $from, ?string $to): Collection
    {
        $rows = collect();

        $entity->capitalContributionsGiven()->with('businessEntity')
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->get()
            ->each(function (BusinessCapitalContribution $item) use ($rows): void {
                $rows->push([
                    'date' => optional($item->transaction_date)?->toDateString(),
                    'type' => 'Modal ke usaha',
                    'description' => $item->businessEntity?->name,
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $item->amount,
                    'direction' => 'out',
                ]);
            });

        $entity->capitalContributionsReceived()->with('sourceEntity')
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->get()
            ->each(function (BusinessCapitalContribution $item) use ($rows): void {
                $rows->push([
                    'date' => optional($item->transaction_date)?->toDateString(),
                    'type' => 'Modal diterima',
                    'description' => $item->sourceEntity?->name,
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $item->amount,
                    'direction' => 'in',
                ]);
            });

        $entity->ownerWithdrawalsGiven()->with('familyEntity')
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->get()
            ->each(function (OwnerWithdrawal $item) use ($rows): void {
                $rows->push([
                    'date' => optional($item->transaction_date)?->toDateString(),
                    'type' => 'Prive',
                    'description' => $item->familyEntity?->name,
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $item->amount,
                    'direction' => 'out',
                ]);
            });

        $entity->ownerWithdrawalsReceived()->with('businessEntity')
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->get()
            ->each(function (OwnerWithdrawal $item) use ($rows): void {
                $rows->push([
                    'date' => optional($item->transaction_date)?->toDateString(),
                    'type' => 'Penerimaan prive',
                    'description' => $item->businessEntity?->name,
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $item->amount,
                    'direction' => 'in',
                ]);
            });

        $entity->profitDistributionsGiven()->with('familyEntity')
            ->when($from, fn ($query) => $query->whereDate('distribution_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('distribution_date', '<=', $to))
            ->get()
            ->each(function (ProfitDistribution $item) use ($rows): void {
                $rows->push([
                    'date' => optional($item->distribution_date)?->toDateString(),
                    'type' => 'Bagi laba',
                    'description' => $item->familyEntity?->name,
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $item->amount,
                    'direction' => 'out',
                ]);
            });

        $entity->profitDistributionsReceived()->with('businessEntity')
            ->when($from, fn ($query) => $query->whereDate('distribution_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('distribution_date', '<=', $to))
            ->get()
            ->each(function (ProfitDistribution $item) use ($rows): void {
                $rows->push([
                    'date' => optional($item->distribution_date)?->toDateString(),
                    'type' => 'Laba diterima',
                    'description' => $item->businessEntity?->name,
                    'detail_description' => null,
                    'account' => null,
                    'amount' => (float) $item->amount,
                    'direction' => 'in',
                ]);
            });

        return $rows;
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

    private function maskAccountNumber(mixed $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }

        $digits = preg_replace('/\s+/', '', (string) $number) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }
}
