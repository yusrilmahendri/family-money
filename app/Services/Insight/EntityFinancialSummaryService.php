<?php

namespace App\Services\Insight;

use App\Models\FinanceEntity;
use App\Services\BusinessProfitService;
use App\Services\EntityReportService;
use App\Support\Rupiah;
use Carbon\Carbon;

class EntityFinancialSummaryService
{
    public const EXPLAIN_PROMPT = 'Jelaskan kondisi keuangan saya berdasarkan ringkasan dan anomali ini, prioritaskan masalah yang paling penting dan berikan tindakan yang dapat dilakukan.';

    /**
     * @var list<string>
     */
    public const PERIOD_KEYS = ['month', 'last_month', 'year', 'custom'];

    public function __construct(
        private readonly EntityReportService $reports,
        private readonly BusinessProfitService $profits,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     from: ?string,
     *     to: ?string,
     *     label: string,
     *     previous_from: ?string,
     *     previous_to: ?string,
     *     previous_label: string
     * }
     */
    public function resolve(string $key = 'month', mixed $from = null, mixed $to = null): array
    {
        $key = in_array($key, self::PERIOD_KEYS, true) ? $key : 'month';
        $now = now();

        if ($key === 'last_month') {
            $month = $now->copy()->subMonthNoOverflow();
            [$fromDate, $toDate] = [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        } elseif ($key === 'year') {
            [$fromDate, $toDate] = [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()];
        } elseif ($key === 'custom') {
            [$fromDate, $toDate] = $this->reports->normalizeRange($from, $to);
            if ($fromDate === null || $toDate === null) {
                [$fromDate, $toDate] = $this->profits->currentMonthBounds();
                $key = 'month';
            }
        } else {
            [$fromDate, $toDate] = $this->profits->currentMonthBounds();
            $key = 'month';
        }

        [$previousFrom, $previousTo] = $this->previousBounds($key, $fromDate, $toDate);

        return [
            'key' => $key,
            'from' => $fromDate,
            'to' => $toDate,
            'label' => $this->humanLabel($key, $fromDate, $toDate),
            'previous_from' => $previousFrom,
            'previous_to' => $previousTo,
            'previous_label' => $this->humanLabel(
                $key === 'custom' ? 'custom' : $key,
                $previousFrom,
                $previousTo,
                previous: true
            ),
        ];
    }

    /**
     * @param  array{key?: string, from?: ?string, to?: ?string}  $filter
     * @return array<string, mixed>
     */
    public function forPeriod(FinanceEntity $entity, array $filter): array
    {
        $period = $this->resolve(
            (string) ($filter['key'] ?? 'month'),
            $filter['from'] ?? null,
            $filter['to'] ?? null,
        );

        $current = $this->reports->report($entity, $period['from'], $period['to']);
        $previous = $this->reports->report($entity, $period['previous_from'], $period['previous_to']);
        [$monthFrom, $monthTo] = $this->profits->currentMonthBounds();
        $month = $this->reports->report($entity, $monthFrom, $monthTo);

        $metrics = $entity->isFamily()
            ? $this->familyMetrics($current, $previous, $month)
            : $this->businessMetrics($current, $previous);

        $highlights = array_values(array_filter(
            $metrics,
            fn (array $metric) => in_array($metric['key'], ['income', 'expense', 'cash_flow'], true)
        ));

        return [
            'entity' => [
                'name' => $entity->name,
                'type' => $entity->type->value,
            ],
            'period' => $period,
            'metrics' => $metrics,
            'highlights' => $highlights,
            'narrative' => $this->narrative($entity, $period['label'], $current),
            'current_report' => $current,
            'previous_report' => $previous,
        ];
    }

    /**
     * Compact payload for the AI provider. No reports, tokens, or account numbers.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function compact(array $summary): array
    {
        return [
            'entity' => $summary['entity'] ?? [],
            'period' => $summary['period']['label'] ?? null,
            'previous_period' => $summary['period']['previous_label'] ?? null,
            'narrative' => $summary['narrative'] ?? '',
            'metrics' => collect($summary['metrics'] ?? [])->map(fn (array $metric) => [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'value' => $metric['value'],
                'previous' => $metric['previous'],
                'change_percent' => $metric['change_percent'],
                'compare_status' => $metric['compare_status'],
            ])->all(),
        ];
    }

    public function formatMoney(float $amount): string
    {
        return Rupiah::format($amount);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function previousBounds(string $key, ?string $from, ?string $to): array
    {
        if ($from === null || $to === null) {
            return [null, null];
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($key === 'year') {
            $year = $start->copy()->subYear();

            return [$year->copy()->startOfYear()->toDateString(), $year->copy()->endOfYear()->toDateString()];
        }

        if ($key === 'month' || $key === 'last_month') {
            $month = $start->copy()->subMonthNoOverflow();

            return [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        }

        $days = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);

        return [$previousStart->toDateString(), $previousEnd->toDateString()];
    }

    private function humanLabel(string $key, ?string $from, ?string $to, bool $previous = false): string
    {
        if ($from === null || $to === null) {
            return 'Semua waktu';
        }

        $start = Carbon::parse($from)->locale('id');

        if ($key === 'year') {
            return ($previous ? 'Tahun ' : 'Tahun ').$start->year;
        }

        if ($key === 'month' || $key === 'last_month') {
            return $start->translatedFormat('F Y');
        }

        $end = Carbon::parse($to)->locale('id');

        return $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $month
     * @return list<array<string, mixed>>
     */
    private function familyMetrics(array $current, array $previous, array $month): array
    {
        return [
            $this->stock('saldo', 'Total saldo', (float) $current['balance_total']),
            $this->flow('income', 'Total pemasukan', (float) $current['cash_flow']['income'], (float) $previous['cash_flow']['income']),
            $this->flow('expense', 'Total pengeluaran', (float) $current['cash_flow']['expense'], (float) $previous['cash_flow']['expense']),
            $this->flow('cash_flow', 'Cash flow bersih', (float) $current['cash_flow']['net_cash'], (float) $previous['cash_flow']['net_cash']),
            $this->stock('month_expense', 'Pengeluaran bulan ini', (float) $month['cash_flow']['expense']),
            $this->stock('debt', 'Hutang outstanding', (float) $current['family']['hutang_outstanding']),
            $this->stock('receivable', 'Piutang outstanding', (float) $current['piutang_outstanding']),
            $this->stock('savings', 'Tabungan', (float) $current['family']['tabungan']),
            $this->flow('capital', 'Modal ke usaha', (float) $current['family']['modal_ke_usaha'], (float) $previous['family']['modal_ke_usaha']),
            $this->flow('prive', 'Prive diterima', (float) $current['family']['penerimaan_prive'], (float) $previous['family']['penerimaan_prive']),
            $this->flow('distribution', 'Pembagian laba diterima', (float) $current['family']['penerimaan_laba'], (float) $previous['family']['penerimaan_laba']),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return list<array<string, mixed>>
     */
    private function businessMetrics(array $current, array $previous): array
    {
        return [
            $this->stock('saldo', 'Total saldo', (float) $current['balance_total']),
            $this->flow('income', 'Revenue / pemasukan', (float) $current['business']['revenue'], (float) $previous['business']['revenue']),
            $this->flow('expense', 'Biaya operasional', (float) $current['business']['operational_expense'], (float) $previous['business']['operational_expense']),
            $this->flow('cash_flow', 'Cash flow bersih', (float) $current['cash_flow']['net_cash'], (float) $previous['cash_flow']['net_cash']),
            $this->flow('profit', 'Laba / rugi', (float) $current['business']['profit'], (float) $previous['business']['profit']),
            $this->stock('receivable', 'Piutang outstanding', (float) $current['piutang_outstanding']),
            $this->flow('capital', 'Modal masuk', (float) $current['business']['capital_received'], (float) $previous['business']['capital_received']),
            $this->flow('prive', 'Prive keluar', (float) $current['business']['prive'], (float) $previous['business']['prive']),
            $this->flow('distribution', 'Pembagian laba', (float) $current['business']['profit_distributed'], (float) $previous['business']['profit_distributed']),
            $this->stock('budget_planned', 'Budget planned', (float) $current['business']['budget_planned']),
            $this->stock('budget_realized', 'Budget realisasi', (float) $current['business']['budget_realized']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flow(string $key, string $label, float $current, float $previous): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'value' => $current,
        ], $this->compare($current, $previous));
    }

    /**
     * @return array<string, mixed>
     */
    private function stock(string $key, string $label, float $value): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'previous' => null,
            'change_percent' => null,
            'direction' => 'none',
            'compare_status' => 'stock',
        ];
    }

    /**
     * @return array{previous: float, change_percent: ?float, direction: string, compare_status: string}
     */
    private function compare(float $current, float $previous): array
    {
        if ($this->isZero($previous)) {
            return [
                'previous' => $previous,
                'change_percent' => null,
                'direction' => 'none',
                'compare_status' => 'no_baseline',
            ];
        }

        $percent = round((($current - $previous) / abs($previous)) * 100, 1);
        $direction = $percent > 0.05 ? 'up' : ($percent < -0.05 ? 'down' : 'flat');

        return [
            'previous' => $previous,
            'change_percent' => $percent,
            'direction' => $direction,
            'compare_status' => 'ok',
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function narrative(FinanceEntity $entity, string $label, array $report): string
    {
        $income = $entity->isFamily()
            ? (float) $report['cash_flow']['income']
            : (float) $report['business']['revenue'];
        $expense = $entity->isFamily()
            ? (float) $report['cash_flow']['expense']
            : (float) $report['business']['operational_expense'];
        $net = (float) $report['cash_flow']['net_cash'];
        $sign = $net < 0 ? 'negatif' : 'positif';

        if ($entity->isBusiness()) {
            $profit = (float) $report['business']['profit'];
            $profitWord = $profit < 0 ? 'rugi' : 'laba';

            return sprintf(
                'Pada %s, revenue sebesar %s dan biaya operasional %s sehingga menghasilkan %s %s. Arus kas bersih periode ini %s %s.',
                $label,
                $this->formatMoney($income),
                $this->formatMoney($expense),
                $profitWord,
                $this->formatMoney($profit),
                $sign,
                $this->formatMoney($net)
            );
        }

        return sprintf(
            'Pada %s, total pemasukan sebesar %s dan pengeluaran %s sehingga menghasilkan arus kas bersih %s %s.',
            $label,
            $this->formatMoney($income),
            $this->formatMoney($expense),
            $sign,
            $this->formatMoney($net)
        );
    }

    private function isZero(float $value): bool
    {
        return abs($value) < 0.00001;
    }
}
