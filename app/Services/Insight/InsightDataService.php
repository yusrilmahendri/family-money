<?php

namespace App\Services\Insight;

use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Income;
use App\Models\Transaction;
use App\Support\FinanceContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Penyedia data Insight AI yang SELALU terikat konteks aktif
 * (\App\Support\FinanceContext::current()).
 *
 * - PRIBADI    : hanya transaksi pribadi (transactions context=PRIBADI).
 * - USAHA_KEBUN: pemasukan usaha (incomes context=USAHA_KEBUN) + biaya
 *                operasional (budget_activities — secara desain memang
 *                eksklusif konteks usaha).
 *
 * Tidak pernah mencampur data antar konteks.
 */
class InsightDataService
{
    /**
     * Konteks aktif (default PRIBADI bila belum dipilih).
     */
    public function getContext(): string
    {
        return FinanceContext::currentOrDefault();
    }

    public function getContextLabel(): string
    {
        return FinanceContext::label($this->getContext());
    }

    public function isPersonal(): bool
    {
        return $this->getContext() === FinanceContext::PRIBADI;
    }

    public function isFarm(): bool
    {
        return $this->getContext() === FinanceContext::USAHA_KEBUN;
    }

    /**
     * Mode tampilan ('personal' / 'farm') untuk view & forecast.
     */
    public function mode(): string
    {
        return $this->isFarm() ? 'farm' : 'personal';
    }

    /**
     * Apakah ada data untuk konteks aktif?
     */
    public function hasData(): bool
    {
        if ($this->isFarm()) {
            $income = Schema::hasTable('incomes')
                ? Income::forContext(FinanceContext::USAHA_KEBUN)->exists()
                : false;
            $biaya = BudgetActivity::query()->exists();

            return $income || $biaya;
        }

        return Transaction::forContext(FinanceContext::PRIBADI)->exists();
    }

    /* ============================== SUMMARY ============================== */

    /**
     * Payload ringkasan (untuk AI) — fokus konteks aktif.
     */
    public function getSummaryPayload(?int $year = null, ?int $month = null): array
    {
        $now = Carbon::now();
        $year = $year ?: (int) $now->year;
        $month = $month ?: (int) $now->month;

        $base = [
            'context' => $this->getContext(),
            'context_label' => $this->getContextLabel(),
            'mode' => $this->mode(),
            'periode' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'tanggal_hari_ini' => $now->toDateString(),
            'has_data' => $this->hasData(),
        ];

        if ($this->isFarm()) {
            return array_merge($base, $this->farmSummary($year, $month));
        }

        return array_merge($base, $this->personalSummary($year, $month));
    }

    protected function personalSummary(int $year, int $month): array
    {
        $pengeluaranBulanIni = (float) Transaction::forContext(FinanceContext::PRIBADI)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->sum('amount');

        $jumlahTransaksiBulanIni = (int) Transaction::forContext(FinanceContext::PRIBADI)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->count();

        // Kategori terbesar bulan ini
        $perKategori = Transaction::forContext(FinanceContext::PRIBADI)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)
            ->get()
            ->groupBy(fn (Transaction $t) => $t->category?->name ?: 'Tanpa Kategori')
            ->map(fn ($rows, $name) => [
                'kategori' => $name,
                'total' => (float) $rows->sum('amount'),
                'jumlah' => $rows->count(),
            ])
            ->sortByDesc('total')->take(5)->values()->all();

        // Tren 6 bulan terakhir
        $tren = [];
        $cursor = Carbon::create($year, $month, 1);
        for ($i = 5; $i >= 0; $i--) {
            $c = $cursor->copy()->subMonths($i);
            $tren[] = [
                'label' => $c->translatedFormat('M Y'),
                'pengeluaran' => (float) Transaction::forContext(FinanceContext::PRIBADI)
                    ->whereYear('transaction_date', $c->year)->whereMonth('transaction_date', $c->month)->sum('amount'),
            ];
        }

        return [
            'metrik' => [
                'total_pengeluaran_bulan_ini' => $pengeluaranBulanIni,
                'jumlah_transaksi_bulan_ini' => $jumlahTransaksiBulanIni,
                'total_pengeluaran_keseluruhan' => (float) Transaction::forContext(FinanceContext::PRIBADI)->sum('amount'),
            ],
            'kategori_terbesar_bulan_ini' => $perKategori,
            'tren_6_bulan' => $tren,
        ];
    }

    protected function farmSummary(int $year, int $month): array
    {
        $hasIncomes = Schema::hasTable('incomes');

        $pemasukanBulanIni = $hasIncomes
            ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)
                ->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
            : 0;
        $biayaBulanIni = (float) BudgetActivity::whereYear('activity_date', $year)
            ->whereMonth('activity_date', $month)->sum('amount');

        // Top biaya operasional bulan ini
        $topBiaya = BudgetActivity::with('budget.category')
            ->whereYear('activity_date', $year)->whereMonth('activity_date', $month)
            ->orderByDesc('amount')->limit(5)->get()
            ->map(fn ($a) => [
                'nama' => $a->name,
                'jenis_usaha' => $a->budget?->category?->name ?? '-',
                'jumlah' => (float) $a->amount,
            ])->all();

        // Pemasukan per jenis usaha bulan ini
        $perKategori = Category::forContext(FinanceContext::USAHA_KEBUN)->orderBy('name')->get()
            ->map(function (Category $cat) use ($year, $month, $hasIncomes) {
                $pendapatan = $hasIncomes
                    ? (float) Income::where('category_id', $cat->id)
                        ->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
                    : 0;
                $biaya = (float) BudgetActivity::whereIn(
                    'budget_id',
                    \App\Models\Budget::where('category_id', $cat->id)->pluck('id')
                )->whereYear('activity_date', $year)->whereMonth('activity_date', $month)->sum('amount');

                return [
                    'jenis_usaha' => $cat->name,
                    'pemasukan' => $pendapatan,
                    'biaya' => $biaya,
                    'laba' => $pendapatan - $biaya,
                ];
            })->filter(fn ($r) => $r['pemasukan'] > 0 || $r['biaya'] > 0)->values()->all();

        // Tren 6 bulan
        $tren = [];
        $cursor = Carbon::create($year, $month, 1);
        for ($i = 5; $i >= 0; $i--) {
            $c = $cursor->copy()->subMonths($i);
            $pem = $hasIncomes
                ? (float) Income::whereYear('income_date', $c->year)->whereMonth('income_date', $c->month)->sum('amount')
                : 0;
            $bia = (float) BudgetActivity::whereYear('activity_date', $c->year)->whereMonth('activity_date', $c->month)->sum('amount');
            $tren[] = [
                'label' => $c->translatedFormat('M Y'),
                'pemasukan' => $pem,
                'biaya' => $bia,
                'laba' => $pem - $bia,
            ];
        }

        return [
            'metrik' => [
                'total_pemasukan_bulan_ini' => $pemasukanBulanIni,
                'total_biaya_operasional_bulan_ini' => $biayaBulanIni,
                'laba_rugi_bulan_ini' => $pemasukanBulanIni - $biayaBulanIni,
                'total_pemasukan_keseluruhan' => $hasIncomes ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)->sum('amount') : 0,
                'total_biaya_keseluruhan' => (float) BudgetActivity::sum('amount'),
            ],
            'top_biaya_bulan_ini' => $topBiaya,
            'per_jenis_usaha_bulan_ini' => $perKategori,
            'tren_6_bulan' => $tren,
        ];
    }

    /* ============================== ANOMALY ============================== */

    /**
     * Payload anomali — terfilter konteks aktif.
     */
    public function getAnomalyPayload(?int $year = null, ?int $month = null): array
    {
        $now = Carbon::now();
        $year = $year ?: (int) $now->year;
        $month = $month ?: (int) $now->month;

        $current = Carbon::create($year, $month, 1);
        $compareMonths = collect(range(1, 6))->map(fn ($i) => $current->copy()->subMonths($i));

        $base = [
            'context' => $this->getContext(),
            'context_label' => $this->getContextLabel(),
            'mode' => $this->mode(),
            'bulan' => $current->translatedFormat('F Y'),
            'has_data' => $this->hasData(),
        ];

        return $this->isFarm()
            ? array_merge($base, $this->farmAnomalies($year, $month, $compareMonths))
            : array_merge($base, $this->personalAnomalies($year, $month, $compareMonths));
    }

    protected function stats(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['avg' => 0, 'max' => 0, 'min' => 0];
        }

        return ['avg' => array_sum($values) / $n, 'max' => max($values), 'min' => min($values)];
    }

    protected function personalAnomalies(int $year, int $month, $compareMonths): array
    {
        $current = (float) Transaction::forContext(FinanceContext::PRIBADI)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->sum('amount');

        $hist = $compareMonths->map(fn (Carbon $c) => (float) Transaction::forContext(FinanceContext::PRIBADI)
            ->whereYear('transaction_date', $c->year)->whereMonth('transaction_date', $c->month)->sum('amount'))->all();
        $stat = $this->stats($hist);

        $anomalies = [];
        if ($stat['avg'] > 0) {
            if ($current > $stat['avg'] * 1.5) {
                $anomalies[] = [
                    'tipe' => 'pengeluaran_melonjak',
                    'level' => 'danger',
                    'judul' => 'Pengeluaran pribadi melonjak',
                    'detail' => sprintf(
                        'Pengeluaran bulan ini Rp %s, sekitar %s%% dari rata-rata 6 bulan lalu (Rp %s).',
                        number_format($current, 0, ',', '.'),
                        number_format($current / $stat['avg'] * 100, 0),
                        number_format($stat['avg'], 0, ',', '.')
                    ),
                    'angka_sekarang' => $current,
                    'rata_rata' => $stat['avg'],
                ];
            } elseif ($current < $stat['avg'] * 0.5) {
                $anomalies[] = [
                    'tipe' => 'pengeluaran_turun',
                    'level' => 'success',
                    'judul' => 'Pengeluaran pribadi jauh menurun',
                    'detail' => sprintf(
                        'Pengeluaran bulan ini Rp %s, jauh di bawah rata-rata 6 bulan lalu (Rp %s).',
                        number_format($current, 0, ',', '.'),
                        number_format($stat['avg'], 0, ',', '.')
                    ),
                    'angka_sekarang' => $current,
                    'rata_rata' => $stat['avg'],
                ];
            }
        }

        // Transaksi tunggal yang abnormal besar
        $rataItem = $stat['avg'] > 0 ? $stat['avg'] / 6 : 0;
        $topTrx = Transaction::forContext(FinanceContext::PRIBADI)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)
            ->orderByDesc('amount')->limit(3)->get();
        foreach ($topTrx as $t) {
            if ($rataItem > 0 && (float) $t->amount > $rataItem * 2) {
                $anomalies[] = [
                    'tipe' => 'transaksi_besar',
                    'level' => 'warning',
                    'judul' => 'Pengeluaran besar: '.($t->description ?: 'Transaksi'),
                    'detail' => sprintf(
                        'Transaksi Rp %s (%s) jauh di atas rata-rata pengeluaran bulanan.',
                        number_format((float) $t->amount, 0, ',', '.'),
                        $t->description ?: ($t->category?->name ?? 'tanpa kategori')
                    ),
                    'angka_sekarang' => (float) $t->amount,
                    'rata_rata' => $rataItem,
                ];
            }
        }

        return [
            'metrics' => [
                'utama' => [
                    'label' => 'Pengeluaran',
                    'current' => $current,
                    'history' => $hist,
                    'avg' => $stat['avg'],
                ],
            ],
            'anomalies' => $anomalies,
        ];
    }

    protected function farmAnomalies(int $year, int $month, $compareMonths): array
    {
        $hasIncomes = Schema::hasTable('incomes');

        $pemCurrent = $hasIncomes
            ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
            : 0;
        $pemHist = $compareMonths->map(fn (Carbon $c) => $hasIncomes
            ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)->whereYear('income_date', $c->year)->whereMonth('income_date', $c->month)->sum('amount')
            : 0)->all();
        $pStat = $this->stats($pemHist);

        $biaCurrent = (float) BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $month)->sum('amount');
        $biaHist = $compareMonths->map(fn (Carbon $c) => (float) BudgetActivity::whereYear('activity_date', $c->year)->whereMonth('activity_date', $c->month)->sum('amount'))->all();
        $bStat = $this->stats($biaHist);

        $anomalies = [];
        if ($pStat['avg'] > 0) {
            if ($pemCurrent < $pStat['avg'] * 0.5) {
                $anomalies[] = [
                    'tipe' => 'pemasukan_turun', 'level' => 'warning',
                    'judul' => 'Pemasukan usaha turun drastis',
                    'detail' => sprintf('Pemasukan bulan ini Rp %s, jauh di bawah rata-rata 6 bulan (Rp %s).',
                        number_format($pemCurrent, 0, ',', '.'), number_format($pStat['avg'], 0, ',', '.')),
                    'angka_sekarang' => $pemCurrent, 'rata_rata' => $pStat['avg'],
                ];
            } elseif ($pemCurrent > $pStat['avg'] * 1.5) {
                $anomalies[] = [
                    'tipe' => 'pemasukan_naik', 'level' => 'success',
                    'judul' => 'Pemasukan usaha jauh di atas rata-rata',
                    'detail' => sprintf('Pemasukan bulan ini Rp %s, sekitar %s%% dari rata-rata 6 bulan (Rp %s).',
                        number_format($pemCurrent, 0, ',', '.'), number_format($pemCurrent / $pStat['avg'] * 100, 0), number_format($pStat['avg'], 0, ',', '.')),
                    'angka_sekarang' => $pemCurrent, 'rata_rata' => $pStat['avg'],
                ];
            }
        }
        if ($bStat['avg'] > 0 && $biaCurrent > $bStat['avg'] * 1.5) {
            $anomalies[] = [
                'tipe' => 'biaya_melonjak', 'level' => 'danger',
                'judul' => 'Biaya operasional melonjak',
                'detail' => sprintf('Biaya bulan ini Rp %s, %s%% dari rata-rata 6 bulan (Rp %s).',
                    number_format($biaCurrent, 0, ',', '.'), number_format($biaCurrent / $bStat['avg'] * 100, 0), number_format($bStat['avg'], 0, ',', '.')),
                'angka_sekarang' => $biaCurrent, 'rata_rata' => $bStat['avg'],
            ];
        }

        $rataItem = $bStat['avg'] > 0 ? $bStat['avg'] / 6 : 0;
        $topBiaya = BudgetActivity::with('budget.category')
            ->whereYear('activity_date', $year)->whereMonth('activity_date', $month)
            ->orderByDesc('amount')->limit(3)->get();
        foreach ($topBiaya as $b) {
            if ($rataItem > 0 && (float) $b->amount > $rataItem * 2) {
                $anomalies[] = [
                    'tipe' => 'biaya_item_besar', 'level' => 'warning',
                    'judul' => 'Biaya besar: '.$b->name,
                    'detail' => sprintf('"%s" sebesar Rp %s (jenis usaha: %s) jauh di atas rata-rata biaya bulanan per item.',
                        $b->name, number_format((float) $b->amount, 0, ',', '.'), $b->budget?->category?->name ?? '-'),
                    'angka_sekarang' => (float) $b->amount, 'rata_rata' => $rataItem,
                ];
            }
        }

        return [
            'metrics' => [
                'pemasukan' => ['label' => 'Pemasukan', 'current' => $pemCurrent, 'history' => $pemHist, 'avg' => $pStat['avg']],
                'biaya' => ['label' => 'Biaya', 'current' => $biaCurrent, 'history' => $biaHist, 'avg' => $bStat['avg']],
            ],
            'anomalies' => $anomalies,
        ];
    }

    /* ============================== FORECAST ============================== */

    /**
     * Payload forecast — terfilter konteks aktif.
     */
    public function getForecastPayload(int $months = 3): array
    {
        $now = Carbon::now();
        $start = $now->copy()->subMonths(6)->startOfMonth();

        if ($this->isFarm()) {
            return $this->farmForecast($now, $start, $months);
        }

        return $this->personalForecast($now, $start, $months);
    }

    protected function personalForecast(Carbon $now, Carbon $start, int $months): array
    {
        $history = [];
        for ($i = 0; $i < 6; $i++) {
            $c = $start->copy()->addMonths($i);
            $history[] = [
                'label' => $c->translatedFormat('M Y'),
                'pengeluaran' => (float) Transaction::forContext(FinanceContext::PRIBADI)
                    ->whereYear('transaction_date', $c->year)->whereMonth('transaction_date', $c->month)->sum('amount'),
            ];
        }

        $series = array_column($history, 'pengeluaran');
        $forecast = [];
        for ($k = 1; $k <= $months; $k++) {
            $c = $now->copy()->addMonths($k);
            $forecast[] = [
                'label' => $c->translatedFormat('M Y'),
                'pengeluaran' => max(0, $this->projectNext($series, $k)),
            ];
        }

        return [
            'mode' => 'personal',
            'series' => ['pengeluaran'],
            'history' => $history,
            'forecast' => $forecast,
        ];
    }

    protected function farmForecast(Carbon $now, Carbon $start, int $months): array
    {
        $hasIncomes = Schema::hasTable('incomes');

        $history = [];
        for ($i = 0; $i < 6; $i++) {
            $c = $start->copy()->addMonths($i);
            $history[] = [
                'label' => $c->translatedFormat('M Y'),
                'pemasukan' => $hasIncomes
                    ? (float) Income::whereYear('income_date', $c->year)->whereMonth('income_date', $c->month)->sum('amount')
                    : 0,
                'biaya' => (float) BudgetActivity::whereYear('activity_date', $c->year)->whereMonth('activity_date', $c->month)->sum('amount'),
            ];
        }

        $pemSeries = array_column($history, 'pemasukan');
        $biaSeries = array_column($history, 'biaya');

        $forecast = [];
        for ($k = 1; $k <= $months; $k++) {
            $c = $now->copy()->addMonths($k);
            $pem = max(0, $this->projectNext($pemSeries, $k));
            $bia = max(0, $this->projectNext($biaSeries, $k));
            $forecast[] = [
                'label' => $c->translatedFormat('M Y'),
                'pemasukan' => $pem,
                'biaya' => $bia,
                'laba' => $pem - $bia,
            ];
        }

        return [
            'mode' => 'farm',
            'series' => ['pemasukan', 'biaya', 'laba'],
            'history' => $history,
            'forecast' => $forecast,
        ];
    }

    /**
     * Proyeksi linear (least-squares): y = intercept + slope * x.
     */
    protected function projectNext(array $series, int $k): float
    {
        $n = count($series);
        if ($n === 0) {
            return 0;
        }
        if ($n === 1) {
            return (float) $series[0];
        }

        $xs = range(0, $n - 1);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($series) / $n;

        $num = 0.0;
        $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($xs[$i] - $meanX) * ($series[$i] - $meanY);
            $den += ($xs[$i] - $meanX) ** 2;
        }
        $slope = $den == 0 ? 0 : $num / $den;
        $intercept = $meanY - $slope * $meanX;

        return $intercept + $slope * ($n + $k - 1);
    }

    /* ============================== CHAT ============================== */

    /**
     * Konteks ringkas untuk chatbot (30 hari terakhir + agregasi), terfilter konteks aktif.
     */
    public function getChatContext(): array
    {
        $now = Carbon::now();
        $since = $now->copy()->subDays(30)->toDateString();

        $base = [
            'context' => $this->getContext(),
            'context_label' => $this->getContextLabel(),
            'mode' => $this->mode(),
            'tanggal_hari_ini' => $now->toDateString(),
            'rentang' => '30 hari terakhir ('.$since.' s/d '.$now->toDateString().')',
        ];

        if ($this->isFarm()) {
            $hasIncomes = Schema::hasTable('incomes');

            $incomes30 = $hasIncomes
                ? Income::forContext(FinanceContext::USAHA_KEBUN)->with('category')
                    ->whereDate('income_date', '>=', $since)->orderByDesc('income_date')->limit(40)->get()
                    ->map(fn ($i) => [
                        'tanggal' => optional($i->income_date)->format('Y-m-d'),
                        'sumber' => $i->source,
                        'jenis_usaha' => $i->category?->name,
                        'jumlah' => (float) $i->amount,
                    ])->all()
                : [];

            $biaya30 = BudgetActivity::with('budget.category')
                ->whereDate('activity_date', '>=', $since)->orderByDesc('activity_date')->limit(40)->get()
                ->map(fn ($a) => [
                    'tanggal' => optional($a->activity_date)->format('Y-m-d'),
                    'nama' => $a->name,
                    'jenis_usaha' => $a->budget?->category?->name,
                    'jumlah' => (float) $a->amount,
                ])->all();

            $totalPem = $hasIncomes ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)->whereDate('income_date', '>=', $since)->sum('amount') : 0;
            $totalBia = (float) BudgetActivity::whereDate('activity_date', '>=', $since)->sum('amount');

            return array_merge($base, [
                'agregasi_30_hari' => [
                    'total_pemasukan' => $totalPem,
                    'total_biaya_operasional' => $totalBia,
                    'laba_rugi' => $totalPem - $totalBia,
                ],
                'pemasukan_terbaru' => $incomes30,
                'biaya_terbaru' => $biaya30,
            ]);
        }

        $trx30 = Transaction::forContext(FinanceContext::PRIBADI)->with('category')
            ->whereDate('transaction_date', '>=', $since)->orderByDesc('transaction_date')->limit(50)->get()
            ->map(fn ($t) => [
                'tanggal' => optional($t->transaction_date)->format('Y-m-d'),
                'keterangan' => $t->insightDescriptionLabel(),
                'kategori' => $t->category?->name,
                'jumlah' => (float) $t->amount,
            ])->all();

        $total30 = (float) Transaction::forContext(FinanceContext::PRIBADI)->whereDate('transaction_date', '>=', $since)->sum('amount');

        return array_merge($base, [
            'agregasi_30_hari' => [
                'total_pengeluaran' => $total30,
                'jumlah_transaksi' => count($trx30),
            ],
            'transaksi_terbaru' => $trx30,
        ]);
    }

    public function getChatContextText(): string
    {
        $data = $this->getChatContext();

        return "Data keuangan (KONTEKS: ".$data['context_label'].") — ".$data['rentang'].":\n".
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
