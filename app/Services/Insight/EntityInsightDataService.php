<?php

namespace App\Services\Insight;

use App\Models\BudgetActivity;
use App\Models\FinanceEntity;
use App\Models\Transaction;
use App\Services\BusinessProfitService;
use App\Services\EntityReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EntityInsightDataService
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'plain_token',
        'token_hash',
        'private_token',
        'access_token',
        'session',
        'session_id',
        '_token',
        'csrf',
        'csrf_token',
        'account_number_full',
    ];

    /**
     * @var array<string, int>
     */
    private const MONTH_NAMES = [
        'januari' => 1,
        'februari' => 2,
        'maret' => 3,
        'april' => 4,
        'mei' => 5,
        'juni' => 6,
        'juli' => 7,
        'agustus' => 8,
        'september' => 9,
        'oktober' => 10,
        'november' => 11,
        'desember' => 12,
    ];

    public function __construct(
        private readonly EntityReportService $reports,
        private readonly BusinessProfitService $profits,
        private readonly EntityFinancialInsightService $insight,
    ) {}

    /**
     * Entity-scoped AI payload. Never reads another entity or FinanceContext.
     *
     * @return array<string, mixed>
     */
    public function payload(FinanceEntity $entity, mixed $from = null, mixed $to = null): array
    {
        $lifetime = $this->reports->report($entity);
        $period = $from === null && $to === null
            ? $this->resolvePeriod(null)
            : $this->periodFromBounds($from, $to);
        $month = $this->reports->report($entity, $period['from'], $period['to']);

        $data = [
            'entity' => [
                'name' => $entity->name,
                'type' => $entity->type->value,
                'public_id' => $entity->public_id,
            ],
            'period_label' => $lifetime['period_label'],
            'current_month_label' => $month['period_label'],
            'asked_period' => $period['label'],
            'balance_summary' => [
                'total' => $lifetime['balance_total'],
                'accounts' => collect($lifetime['accounts'])->map(fn (array $row) => [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'balance' => $row['balance'],
                    'account_number' => $row['account_number'],
                ])->all(),
            ],
            'income_expense' => [
                'income' => $lifetime['cash_flow']['income'],
                'expense' => $lifetime['cash_flow']['expense'],
                'month_income' => $month['cash_flow']['income'],
                'month_expense' => $month['cash_flow']['expense'],
            ],
            'cash_flow' => $this->compactCashFlow($month['cash_flow']),
            'receivable' => [
                'outstanding' => $lifetime['piutang_outstanding'],
                'overdue' => $lifetime['piutang_overdue'],
            ],
            'recent_activity' => array_slice($lifetime['movements'], 0, 20),
            'category_breakdown' => $this->categoryBreakdown($entity, $period['from'], $period['to']),
        ];

        if ($entity->isFamily()) {
            $data['debt'] = [
                'outstanding' => $lifetime['family']['hutang_outstanding'],
            ];
            $data['savings'] = [
                'total' => $lifetime['family']['tabungan'],
            ];
            $data['family'] = [
                'modal_ke_usaha' => $lifetime['family']['modal_ke_usaha'],
                'penerimaan_prive' => $lifetime['family']['penerimaan_prive'],
                'penerimaan_laba' => $lifetime['family']['penerimaan_laba'],
                'period_modal_ke_usaha' => $month['family']['modal_ke_usaha'],
                'period_penerimaan_prive' => $month['family']['penerimaan_prive'],
                'period_penerimaan_laba' => $month['family']['penerimaan_laba'],
            ];
        } else {
            $data['budget'] = [
                'planned' => $lifetime['business']['budget_planned'],
                'realized' => $lifetime['business']['budget_realized'],
                'period_planned' => $month['business']['budget_planned'],
                'period_realized' => $month['business']['budget_realized'],
            ];
            $data['profit'] = [
                'revenue' => $lifetime['business']['revenue'],
                'operational_expense' => $lifetime['business']['operational_expense'],
                'profit' => $lifetime['business']['profit'],
                'distributed' => $lifetime['business']['profit_distributed'],
                'period_revenue' => $month['business']['revenue'],
                'period_operational_expense' => $month['business']['operational_expense'],
                'period_profit' => $month['business']['profit'],
            ];
            $data['business'] = [
                'capital_received' => $lifetime['business']['capital_received'],
                'prive' => $lifetime['business']['prive'],
                'period_capital_received' => $month['business']['capital_received'],
                'period_prive' => $month['business']['prive'],
                'period_profit_distributed' => $month['business']['profit_distributed'],
            ];
        }

        return $this->sanitize($data);
    }

    /**
     * Compact context sent to the AI provider. No tokens, passwords, or full account numbers.
     *
     * @param  array{key?: string, from?: ?string, to?: ?string}|null  $periodFilter
     * @return array<string, mixed>
     */
    public function chatContext(FinanceEntity $entity, ?string $message = null, ?array $periodFilter = null): array
    {
        $period = $periodFilter ?? $this->resolvePeriod($message);
        $payload = $this->payload($entity, $period['from'] ?? null, $period['to'] ?? null);
        $structured = $this->insight->make($entity, [
            'key' => $period['key'] ?? 'custom',
            'from' => $period['from'] ?? null,
            'to' => $period['to'] ?? null,
        ]);

        $context = [
            'entity' => [
                'name' => $payload['entity']['name'],
                'type' => $payload['entity']['type'],
            ],
            'period' => $period['label'],
            'period_range' => [$period['from'], $period['to']],
            'today' => now()->toDateString(),
            'timezone' => (string) config('app.timezone'),
            'saldo_total' => $payload['balance_summary']['total'],
            'saldo_per_rekening' => $payload['balance_summary']['accounts'],
            'periode_cash_flow' => $payload['cash_flow'],
            'lifetime_income' => $payload['income_expense']['income'],
            'lifetime_expense' => $payload['income_expense']['expense'],
            'period_income' => $payload['income_expense']['month_income'],
            'period_expense' => $payload['income_expense']['month_expense'],
            'piutang' => $payload['receivable'],
            'kategori' => $payload['category_breakdown'],
            'aktivitas_terbaru' => array_slice($payload['recent_activity'], 0, 8),
            'ringkasan' => $structured['ai_context']['ringkasan'],
            'anomali' => $structured['ai_context']['anomali'],
        ];

        if ($entity->isFamily()) {
            $context['family'] = $payload['family'];
            $context['hutang_outstanding'] = $payload['debt']['outstanding'] ?? 0;
            $context['tabungan'] = $payload['savings']['total'] ?? 0;
        } else {
            $context['business'] = $payload['business'];
            $context['profit'] = $payload['profit'];
            $context['anggaran'] = $payload['budget'];
        }

        return $this->sanitize($context);
    }

    /**
     * @return array{from: ?string, to: ?string, label: string, key: string}
     */
    public function resolvePeriod(?string $message): array
    {
        $now = now();
        $text = mb_strtolower(trim((string) $message));

        if ($text !== '' && preg_match('/semua waktu|keseluruhan|dari awal/', $text)) {
            return $this->periodFromBounds(null, null, 'custom');
        }

        if ($text !== '' && preg_match('/tahun ini/', $text)) {
            return $this->periodFromBounds($now->copy()->startOfYear(), $now->copy()->endOfYear(), 'year');
        }

        if ($text !== '' && preg_match('/tahun lalu/', $text)) {
            $year = $now->copy()->subYear();

            return $this->periodFromBounds($year->copy()->startOfYear(), $year->copy()->endOfYear(), 'custom');
        }

        if ($text !== '' && preg_match('/bulan lalu|bulan kemarin/', $text)) {
            $month = $now->copy()->subMonthNoOverflow();

            return $this->periodFromBounds($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), 'last_month');
        }

        if ($text !== '') {
            $named = $this->namedMonthPeriod($text, $now);
            if ($named !== null) {
                return $named;
            }
        }

        [$from, $to] = $this->profits->currentMonthBounds();

        return $this->periodFromBounds($from, $to, 'month');
    }

    /**
     * @return list<string>
     */
    public function suggestedQuestions(FinanceEntity $entity): array
    {
        if ($entity->isFamily()) {
            return [
                'Analisis pengeluaran bulan ini',
                'Bagaimana kondisi saldo saya?',
                'Kategori apa yang paling boros?',
                'Bagaimana kondisi hutang dan piutang?',
            ];
        }

        return [
            'Berapa laba bulan ini?',
            'Bagaimana arus kas usaha?',
            'Biaya operasional terbesar apa?',
            'Bagaimana kondisi modal dan prive?',
        ];
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    public function welcomeChips(FinanceEntity $entity): array
    {
        $payload = $this->payload($entity);

        $chips = [
            ['label' => 'Saldo saat ini', 'value' => (float) $payload['balance_summary']['total']],
        ];

        if ($entity->isFamily()) {
            $chips[] = ['label' => 'Pengeluaran bulan ini', 'value' => (float) $payload['income_expense']['month_expense']];
        } else {
            $chips[] = ['label' => 'Laba bulan ini', 'value' => (float) ($payload['profit']['period_profit'] ?? 0)];
        }

        return $chips;
    }

    public function assistantTitle(FinanceEntity $entity): string
    {
        return $entity->isFamily()
            ? 'Asisten Keuangan Keluarga'
            : 'Asisten Keuangan Usaha';
    }

    public function hasData(FinanceEntity $entity): bool
    {
        return $entity->hasFinancialRecords();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function containsSensitiveValue(array $payload): bool
    {
        $encoded = json_encode($payload) ?: '';

        foreach (['password', 'token_hash', 'remember_token', 'csrf'] as $needle) {
            if (str_contains(strtolower($encoded), $needle)) {
                return true;
            }
        }

        return $this->containsSensitiveKey($payload);
    }

    /**
     * @return array{from: ?string, to: ?string, label: string, key: string}
     */
    private function periodFromBounds(mixed $from, mixed $to, string $key = 'custom'): array
    {
        [$fromDate, $toDate] = $this->reports->normalizeRange($from, $to);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'label' => $this->profits->periodLabel($fromDate, $toDate),
            'key' => $key,
        ];
    }

    /**
     * @return array{from: string, to: string, label: string}|null
     */
    private function namedMonthPeriod(string $text, Carbon $now): ?array
    {
        foreach (self::MONTH_NAMES as $name => $month) {
            if (! preg_match('/\b'.$name.'\b(?:\s+(\d{4}))?/', $text, $match)) {
                continue;
            }

            $year = isset($match[1]) ? (int) $match[1] : (int) $now->year;
            $cursor = Carbon::create($year, $month, 1, 0, 0, 0, $now->timezone);

            return $this->periodFromBounds($cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth(), 'custom');
        }

        return null;
    }

    /**
     * @param  array<string, float>  $flows
     * @return array<string, float>
     */
    private function compactCashFlow(array $flows): array
    {
        return [
            'income' => (float) ($flows['income'] ?? 0),
            'expense' => (float) ($flows['expense'] ?? 0),
            'cash_in' => (float) ($flows['cash_in'] ?? 0),
            'cash_out' => (float) ($flows['cash_out'] ?? 0),
            'net_cash' => (float) ($flows['net_cash'] ?? 0),
            'transfer' => (float) ($flows['transfer_in'] ?? 0),
            'capital_in' => (float) ($flows['capital_in'] ?? 0),
            'capital_out' => (float) ($flows['capital_out'] ?? 0),
            'prive_in' => (float) ($flows['withdrawal_in'] ?? 0),
            'prive_out' => (float) ($flows['withdrawal_out'] ?? 0),
            'distribution_in' => (float) ($flows['distribution_in'] ?? 0),
            'distribution_out' => (float) ($flows['distribution_out'] ?? 0),
        ];
    }

    /**
     * @return list<array{name: string, total: float, count: int}>
     */
    private function categoryBreakdown(FinanceEntity $entity, ?string $from, ?string $to): array
    {
        if ($entity->isFamily()) {
            $rows = $entity->transactions()
                ->with('category')
                ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
                ->get()
                ->groupBy(fn (Transaction $transaction) => $transaction->category?->name ?: 'Tanpa Kategori');

            return $this->rankedGroups($rows);
        }

        $rows = BudgetActivity::query()
            ->with('budget.category')
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $entity->id))
            ->when($from, fn ($query) => $query->whereDate('activity_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('activity_date', '<=', $to))
            ->get()
            ->groupBy(fn (BudgetActivity $activity) => $activity->budget?->category?->name ?: ($activity->name ?: 'Tanpa Kategori'));

        return $this->rankedGroups($rows);
    }

    /**
     * @param  Collection<string, Collection<int, mixed>>  $groups
     * @return list<array{name: string, total: float, count: int}>
     */
    private function rankedGroups(Collection $groups): array
    {
        return $groups
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'total' => (float) $rows->sum('amount'),
                'count' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['password', 'token_hash', 'remember_token', 'csrf', 'session'] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsSensitiveKey(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                return true;
            }

            if (is_array($value) && $this->containsSensitiveKey($value)) {
                return true;
            }
        }

        return false;
    }
}
