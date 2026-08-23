<?php

namespace App\Services\Insight;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\BudgetActivity;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialAnomalyDetectionService
{
    public function __construct(
        private readonly EntityFinancialSummaryService $summaries,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @return array{items: list<array<string, mixed>>, notes: list<string>, counts: array{total: int, critical: int, warning: int, info: int}}
     */
    public function detect(FinanceEntity $entity, array $summary): array
    {
        $current = $summary['current_report'] ?? [];
        $previous = $summary['previous_report'] ?? [];
        $period = $summary['period'] ?? [];
        $from = $period['from'] ?? null;
        $to = $period['to'] ?? null;
        $items = [];
        $notes = [];

        $this->detectNegativeBalance($items, $current);
        $this->detectNegativeCashFlow($items, $current);
        $this->detectIncomeDrop($items, $entity, $current, $previous);
        $this->detectOverdueReceivable($items, $current);
        $this->detectOverdueDebt($items, $entity);
        $this->detectBudgetOverrun($items, $entity, $current);
        $this->detectMaterialCapitalPrive($items, $entity, $current, $previous);
        $this->detectRepeatedTransactions($items, $entity, $from, $to);

        if ($this->hasExpenseHistory($entity, $from)) {
            $this->detectUnusualExpenses($items, $entity, $from, $to);
            $this->detectCategorySpike($items, $entity, $from, $to);
        } else {
            $notes[] = 'Data historis belum cukup untuk mendeteksi pola pengeluaran tidak biasa.';
        }

        $items = $this->sortItems($items);

        return [
            'items' => $items,
            'notes' => $notes,
            'counts' => [
                'total' => count($items),
                'critical' => count(array_filter($items, fn (array $item) => $item['severity'] === AnomalySeverity::CRITICAL->value)),
                'warning' => count(array_filter($items, fn (array $item) => $item['severity'] === AnomalySeverity::WARNING->value)),
                'info' => count(array_filter($items, fn (array $item) => $item['severity'] === AnomalySeverity::INFO->value)),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $detected
     * @return array{items: list<array<string, mixed>>, notes: list<string>, counts: array<string, int>}
     */
    public function compact(array $detected): array
    {
        return [
            'counts' => $detected['counts'] ?? ['total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0],
            'notes' => $detected['notes'] ?? [],
            'items' => collect($detected['items'] ?? [])->map(fn (array $item) => [
                'type' => $item['type'],
                'severity' => $item['severity'],
                'title' => $item['title'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'deviation_percentage' => $item['deviation_percentage'],
            ])->all(),
        ];
    }

    /**
     * @return array{cash_flow: float, anomaly_count: int, critical_count: int, period_label: string}
     */
    public function dashboardPreview(FinanceEntity $entity): array
    {
        $summary = $this->summaries->forPeriod($entity, ['key' => 'month']);
        $detected = $this->detect($entity, $summary);
        $cashFlow = collect($summary['metrics'])->firstWhere('key', 'cash_flow');

        return [
            'cash_flow' => (float) ($cashFlow['value'] ?? 0),
            'anomaly_count' => $detected['counts']['total'],
            'critical_count' => $detected['counts']['critical'],
            'period_label' => $summary['period']['label'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $report
     */
    private function detectNegativeBalance(array &$items, array $report): void
    {
        $total = (float) ($report['balance_total'] ?? 0);

        if ($total < 0) {
            $items[] = $this->item(
                AnomalyType::NEGATIVE_BALANCE,
                AnomalySeverity::CRITICAL,
                'Saldo Negatif',
                'Total saldo entity saat ini '.$this->money($total).'.',
                $total
            );
        }

        foreach ($report['accounts'] ?? [] as $account) {
            $balance = (float) ($account['balance'] ?? 0);
            if ($balance >= 0) {
                continue;
            }

            if ($total < 0) {
                continue;
            }

            $items[] = $this->item(
                AnomalyType::NEGATIVE_BALANCE,
                AnomalySeverity::WARNING,
                'Saldo Rekening Negatif',
                ($account['name'] ?? 'Rekening').' memiliki saldo '.$this->money($balance).'.',
                $balance
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $report
     */
    private function detectNegativeCashFlow(array &$items, array $report): void
    {
        $net = (float) ($report['cash_flow']['net_cash'] ?? 0);

        if ($net >= 0) {
            return;
        }

        $severity = abs($net) >= (float) config('financial_anomaly.negative_cash_flow.critical_abs')
            ? AnomalySeverity::CRITICAL
            : AnomalySeverity::WARNING;

        $items[] = $this->item(
            AnomalyType::NEGATIVE_CASH_FLOW,
            $severity,
            'Cash Flow Negatif',
            'Pengeluaran melebihi pemasukan '.$this->money($net).'.',
            $net
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     */
    private function detectIncomeDrop(array &$items, FinanceEntity $entity, array $current, array $previous): void
    {
        $nowIncome = $entity->isFamily()
            ? (float) ($current['cash_flow']['income'] ?? 0)
            : (float) ($current['business']['revenue'] ?? 0);
        $prevIncome = $entity->isFamily()
            ? (float) ($previous['cash_flow']['income'] ?? 0)
            : (float) ($previous['business']['revenue'] ?? 0);

        if ($prevIncome <= 0 || $nowIncome >= $prevIncome) {
            return;
        }

        $drop = (($prevIncome - $nowIncome) / $prevIncome);
        $critical = (float) config('financial_anomaly.income_drop.critical_ratio');
        $warning = (float) config('financial_anomaly.income_drop.warning_ratio');

        if ($drop < $warning) {
            return;
        }

        $percent = round($drop * 100, 1);
        $items[] = $this->item(
            AnomalyType::INCOME_DROP,
            $drop >= $critical ? AnomalySeverity::CRITICAL : AnomalySeverity::WARNING,
            'Penurunan Pemasukan Signifikan',
            'Pemasukan periode ini turun '.$this->percent($percent).' dibanding periode sebelumnya.',
            $nowIncome,
            $percent
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $report
     */
    private function detectOverdueReceivable(array &$items, array $report): void
    {
        $overdue = (float) ($report['piutang_overdue'] ?? 0);

        if ($overdue <= 0) {
            return;
        }

        $severity = $overdue >= (float) config('financial_anomaly.overdue.critical_abs')
            ? AnomalySeverity::CRITICAL
            : AnomalySeverity::WARNING;

        $items[] = $this->item(
            AnomalyType::OVERDUE_RECEIVABLE,
            $severity,
            'Piutang Jatuh Tempo',
            $this->money($overdue).' belum diterima.',
            $overdue
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function detectOverdueDebt(array &$items, FinanceEntity $entity): void
    {
        if (! $entity->isFamily()) {
            return;
        }

        $today = now()->startOfDay();
        $total = 0.0;

        foreach ($entity->debts()->where('remaining_balance', '>', 0)->get() as $debt) {
            if (! $this->debtIsOverdue($debt, $today)) {
                continue;
            }

            $total += (float) $debt->remaining_balance;
        }

        if ($total <= 0) {
            return;
        }

        $items[] = $this->item(
            AnomalyType::OVERDUE_DEBT,
            $total >= (float) config('financial_anomaly.overdue.critical_abs')
                ? AnomalySeverity::CRITICAL
                : AnomalySeverity::WARNING,
            'Hutang Jatuh Tempo',
            $this->money($total).' hutang sudah melewati tanggal jatuh tempo periode ini.',
            $total
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $report
     */
    private function detectBudgetOverrun(array &$items, FinanceEntity $entity, array $report): void
    {
        if (! $entity->isBusiness()) {
            return;
        }

        $planned = (float) ($report['business']['budget_planned'] ?? 0);
        $realized = (float) ($report['business']['budget_realized'] ?? 0);

        if ($planned <= 0 || $realized <= $planned) {
            return;
        }

        $over = ($realized - $planned) / $planned;
        $percent = round($over * 100, 1);
        $critical = (float) config('financial_anomaly.budget_overrun.critical_ratio');

        $items[] = $this->item(
            AnomalyType::BUDGET_OVERRUN,
            $over >= $critical ? AnomalySeverity::CRITICAL : AnomalySeverity::WARNING,
            'Budget Overrun',
            'Realisasi '.$this->money($realized).' melebihi anggaran '.$this->money($planned).' ('.$this->percent($percent).').',
            $realized,
            $percent
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     */
    private function detectMaterialCapitalPrive(array &$items, FinanceEntity $entity, array $current, array $previous): void
    {
        $pairs = $entity->isFamily()
            ? [
                ['Modal ke usaha', (float) $current['family']['modal_ke_usaha'], (float) $previous['family']['modal_ke_usaha']],
                ['Prive diterima', (float) $current['family']['penerimaan_prive'], (float) $previous['family']['penerimaan_prive']],
            ]
            : [
                ['Modal masuk', (float) $current['business']['capital_received'], (float) $previous['business']['capital_received']],
                ['Prive keluar', (float) $current['business']['prive'], (float) $previous['business']['prive']],
            ];

        $warning = (float) config('financial_anomaly.capital_prive.warning_ratio');
        $critical = (float) config('financial_anomaly.capital_prive.critical_ratio');
        $min = (float) config('financial_anomaly.capital_prive.min_amount');

        foreach ($pairs as [$label, $now, $prev]) {
            if ($prev <= 0 || $now < $min || $now <= $prev) {
                continue;
            }

            $ratio = ($now - $prev) / $prev;
            if ($ratio < $warning) {
                continue;
            }

            $percent = round($ratio * 100, 1);
            $items[] = $this->item(
                AnomalyType::MATERIAL_CAPITAL_PRIVE,
                $ratio >= $critical ? AnomalySeverity::CRITICAL : AnomalySeverity::WARNING,
                'Perubahan Besar Modal / Prive',
                $label.' '.$this->money($now).' naik '.$this->percent($percent).' dibanding periode sebelumnya.',
                $now,
                $percent
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function detectRepeatedTransactions(array &$items, FinanceEntity $entity, ?string $from, ?string $to): void
    {
        $rows = $this->periodExpenses($entity, $from, $to);
        $tolerance = (float) config('financial_anomaly.repeated_transaction.amount_tolerance');
        $maxDays = (int) config('financial_anomaly.repeated_transaction.max_days_apart');
        $min = (float) config('financial_anomaly.repeated_transaction.min_amount');
        $seen = [];

        foreach ($rows as $index => $left) {
            if ((float) $left['amount'] < $min) {
                continue;
            }

            foreach ($rows as $other => $right) {
                if ($other <= $index) {
                    continue;
                }

                if ($left['category'] !== $right['category']) {
                    continue;
                }

                $a = (float) $left['amount'];
                $b = (float) $right['amount'];
                if ($a <= 0 || abs($a - $b) / max($a, $b) > $tolerance) {
                    continue;
                }

                if (! $this->similarDescription((string) $left['description'], (string) $right['description'])) {
                    continue;
                }

                $days = Carbon::parse($left['date'])->diffInDays(Carbon::parse($right['date']));
                if ($days > $maxDays) {
                    continue;
                }

                $key = implode(':', [min($left['id'], $right['id']), max($left['id'], $right['id'])]);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $items[] = $this->item(
                    AnomalyType::REPEATED_TRANSACTION,
                    AnomalySeverity::WARNING,
                    'Potensi Transaksi Berulang',
                    'Potensi transaksi berulang yang perlu diperiksa: '.$this->money($a).' pada kategori '.$left['category'].'.',
                    $a,
                    null,
                    $left['date']
                );

                break;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function detectUnusualExpenses(array &$items, FinanceEntity $entity, ?string $from, ?string $to): void
    {
        $minAmount = (float) config('financial_anomaly.unusual_expense.min_amount');
        $warning = (float) config('financial_anomaly.unusual_expense.warning_ratio');
        $critical = (float) config('financial_anomaly.unusual_expense.critical_ratio');
        $limit = (int) config('financial_anomaly.unusual_expense.limit');
        $minSamples = (int) config('financial_anomaly.min_history_samples');
        $found = [];

        foreach ($this->periodExpenses($entity, $from, $to) as $row) {
            $amount = (float) $row['amount'];
            if ($amount < $minAmount) {
                continue;
            }

            $history = $this->categoryHistory($entity, $row['category_id'], $from);
            if ($history->count() < $minSamples) {
                continue;
            }

            $avg = (float) $history->avg();
            if ($avg <= 0) {
                continue;
            }

            $ratio = ($amount - $avg) / $avg;
            if ($ratio < $warning) {
                continue;
            }

            $percent = round($ratio * 100, 1);
            $found[] = $this->item(
                AnomalyType::UNUSUAL_EXPENSE,
                $ratio >= $critical ? AnomalySeverity::CRITICAL : AnomalySeverity::WARNING,
                'Pengeluaran Tidak Biasa',
                'Pengeluaran '.$this->money($amount).' pada kategori '.$row['category'].' '.$this->percent($percent).' lebih tinggi dari rata-rata historis.',
                $amount,
                $percent,
                $row['date']
            );
        }

        usort($found, fn (array $a, array $b) => ($b['deviation_percentage'] ?? 0) <=> ($a['deviation_percentage'] ?? 0));

        foreach (array_slice($found, 0, $limit) as $item) {
            $items[] = $item;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function detectCategorySpike(array &$items, FinanceEntity $entity, ?string $from, ?string $to): void
    {
        if ($from === null || $to === null) {
            return;
        }

        $warning = (float) config('financial_anomaly.category_spike.warning_ratio');
        $critical = (float) config('financial_anomaly.category_spike.critical_ratio');
        $min = (float) config('financial_anomaly.category_spike.min_amount');
        $current = $this->categoryTotals($entity, $from, $to);
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo = Carbon::parse($from)->subDay()->toDateString();
        $prevFrom = Carbon::parse($prevTo)->subDays($days - 1)->toDateString();
        $previous = $this->categoryTotals($entity, $prevFrom, $prevTo);

        foreach ($current as $name => $amount) {
            $prev = (float) ($previous[$name] ?? 0);
            if ($prev <= 0 || $amount < $min || $amount <= $prev) {
                continue;
            }

            $ratio = ($amount - $prev) / $prev;
            if ($ratio < $warning) {
                continue;
            }

            $percent = round($ratio * 100, 1);
            $items[] = $this->item(
                AnomalyType::EXPENSE_SPIKE,
                $ratio >= $critical ? AnomalySeverity::CRITICAL : AnomalySeverity::WARNING,
                'Lonjakan Pengeluaran',
                'Pengeluaran kategori '.$name.' '.$this->money($amount).', '.$this->percent($percent).' di atas periode sebelumnya.',
                $amount,
                $percent
            );
        }
    }

    private function hasExpenseHistory(FinanceEntity $entity, ?string $from): bool
    {
        $min = (int) config('financial_anomaly.min_history_samples');

        if ($entity->isFamily()) {
            return $entity->transactions()
                ->when($from, fn ($query) => $query->whereDate('transaction_date', '<', $from))
                ->count() >= $min;
        }

        return BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('activity_date', '<', $from))
            ->count() >= $min;
    }

    /**
     * @return list<array{id: string, amount: float, date: string, category: string, category_id: ?int, description: string}>
     */
    private function periodExpenses(FinanceEntity $entity, ?string $from, ?string $to): array
    {
        if ($entity->isFamily()) {
            return $entity->transactions()
                ->with('category')
                ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
                ->get()
                ->map(fn (Transaction $transaction) => [
                    'id' => 't-'.$transaction->id,
                    'amount' => (float) $transaction->amount,
                    'date' => optional($transaction->transaction_date)?->toDateString() ?: '',
                    'category' => $transaction->category?->name ?: 'Tanpa Kategori',
                    'category_id' => $transaction->category_id,
                    'description' => (string) $transaction->description,
                ])
                ->all();
        }

        return BudgetActivity::query()
            ->with('budget.category')
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('activity_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('activity_date', '<=', $to))
            ->get()
            ->map(fn (BudgetActivity $activity) => [
                'id' => 'b-'.$activity->id,
                'amount' => (float) $activity->amount,
                'date' => optional($activity->activity_date)?->toDateString() ?: '',
                'category' => $activity->budget?->category?->name ?: ($activity->name ?: 'Tanpa Kategori'),
                'category_id' => $activity->budget?->category_id,
                'description' => (string) ($activity->description ?: $activity->name),
            ])
            ->all();
    }

    /**
     * @return Collection<int, float>
     */
    private function categoryHistory(FinanceEntity $entity, mixed $categoryId, ?string $before): Collection
    {
        if ($entity->isFamily()) {
            return $entity->transactions()
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->when($before, fn ($query) => $query->whereDate('transaction_date', '<', $before))
                ->pluck('amount')
                ->map(fn ($amount) => (float) $amount);
        }

        return BudgetActivity::query()
            ->whereHas('budget', function ($query) use ($entity, $categoryId) {
                $query->where('finance_entity_id', $entity->id);
                if ($categoryId) {
                    $query->where('category_id', $categoryId);
                }
            })
            ->when($before, fn ($query) => $query->whereDate('activity_date', '<', $before))
            ->pluck('amount')
            ->map(fn ($amount) => (float) $amount);
    }

    /**
     * @return array<string, float>
     */
    private function categoryTotals(FinanceEntity $entity, ?string $from, ?string $to): array
    {
        $totals = [];

        foreach ($this->periodExpenses($entity, $from, $to) as $row) {
            $name = $row['category'];
            $totals[$name] = ($totals[$name] ?? 0) + (float) $row['amount'];
        }

        return $totals;
    }

    private function debtIsOverdue(Debt $debt, Carbon $today): bool
    {
        if (! $debt->due_day || ! $debt->start_date) {
            return false;
        }

        if ($debt->start_date->gt($today)) {
            return false;
        }

        $dueDay = min((int) $debt->due_day, $today->daysInMonth);
        $dueThisMonth = $today->copy()->day($dueDay);

        if ($today->lt($dueThisMonth)) {
            return false;
        }

        return ! $debt->payments()
            ->whereDate('paid_on', '>=', $today->copy()->startOfMonth()->toDateString())
            ->exists();
    }

    private function similarDescription(string $left, string $right): bool
    {
        $a = $this->normalize($left);
        $b = $this->normalize($right);

        if ($a === '' || $b === '') {
            return $a === $b;
        }

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($value)) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        AnomalyType $type,
        AnomalySeverity $severity,
        string $title,
        string $description,
        float $amount,
        ?float $deviation = null,
        ?string $detectedAt = null,
    ): array {
        return [
            'type' => $type->value,
            'severity' => $severity->value,
            'title' => $title,
            'description' => $description,
            'amount' => $amount,
            'deviation_percentage' => $deviation,
            'detected_at' => $detectedAt ?: now()->toDateString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sortItems(array $items): array
    {
        usort($items, function (array $left, array $right) {
            $rank = AnomalySeverity::from($right['severity'])->rank() <=> AnomalySeverity::from($left['severity'])->rank();

            if ($rank !== 0) {
                return $rank;
            }

            return abs((float) $right['amount']) <=> abs((float) $left['amount']);
        });

        return array_values($items);
    }

    private function money(float $amount): string
    {
        return $this->summaries->formatMoney($amount);
    }

    private function percent(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }
}
