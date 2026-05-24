<?php

namespace App\Service;

use App\Models\BudgetActivity;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class InsightService
{
    /**
     * Hitung deteksi anomali: bandingkan bulan tertentu dengan rata-rata 6 bulan sebelumnya.
     * Mengembalikan list anomali yang ditemukan beserta penjelasannya.
     */
    public function detectAnomalies(?int $year = null, ?int $month = null): array
    {
        $now = Carbon::now();
        $year  = $year  ?: (int) $now->year;
        $month = $month ?: (int) $now->month;

        $current = Carbon::create($year, $month, 1);
        $compareMonths = collect(range(1, 6))->map(fn ($i) => $current->copy()->subMonths($i));

        $hasIncomes = Schema::hasTable('incomes');

        $stats = function (array $values) {
            $n = count($values);
            if ($n === 0) return ['avg' => 0, 'max' => 0, 'min' => 0];
            return [
                'avg' => array_sum($values) / $n,
                'max' => max($values),
                'min' => min($values),
            ];
        };

        // pemasukan
        $pemasukanCurrent = $hasIncomes
            ? (float) Income::whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
            : 0;
        $pemasukanHist = $compareMonths->map(fn (Carbon $c) => $hasIncomes
            ? (float) Income::whereYear('income_date', $c->year)->whereMonth('income_date', $c->month)->sum('amount')
            : 0)->all();
        $pStat = $stats($pemasukanHist);

        // biaya
        $biayaCurrent = (float) BudgetActivity::whereYear('activity_date', $year)
            ->whereMonth('activity_date', $month)->sum('amount');
        $biayaHist = $compareMonths->map(fn (Carbon $c) =>
            (float) BudgetActivity::whereYear('activity_date', $c->year)->whereMonth('activity_date', $c->month)->sum('amount')
        )->all();
        $bStat = $stats($biayaHist);

        $anomalies = [];

        $thresholdHigh = 1.5; // 50% di atas rata-rata
        $thresholdLow  = 0.5; // 50% di bawah rata-rata

        if ($pStat['avg'] > 0) {
            if ($pemasukanCurrent < $pStat['avg'] * $thresholdLow) {
                $anomalies[] = [
                    'tipe' => 'pemasukan_turun',
                    'level' => 'warning',
                    'judul' => 'Pemasukan turun drastis',
                    'detail' => sprintf(
                        'Pemasukan bulan ini Rp %s, jauh di bawah rata-rata 6 bulan lalu (Rp %s).',
                        number_format($pemasukanCurrent, 0, ',', '.'),
                        number_format($pStat['avg'], 0, ',', '.')
                    ),
                    'angka_sekarang' => $pemasukanCurrent,
                    'rata_rata' => $pStat['avg'],
                ];
            } elseif ($pemasukanCurrent > $pStat['avg'] * $thresholdHigh) {
                $anomalies[] = [
                    'tipe' => 'pemasukan_naik',
                    'level' => 'success',
                    'judul' => 'Pemasukan jauh di atas rata-rata',
                    'detail' => sprintf(
                        'Pemasukan bulan ini Rp %s, sekitar %s%% dari rata-rata 6 bulan lalu (Rp %s).',
                        number_format($pemasukanCurrent, 0, ',', '.'),
                        $pStat['avg'] > 0 ? number_format(($pemasukanCurrent / $pStat['avg']) * 100, 0) : '∞',
                        number_format($pStat['avg'], 0, ',', '.')
                    ),
                    'angka_sekarang' => $pemasukanCurrent,
                    'rata_rata' => $pStat['avg'],
                ];
            }
        }

        if ($bStat['avg'] > 0 && $biayaCurrent > $bStat['avg'] * $thresholdHigh) {
            $anomalies[] = [
                'tipe' => 'biaya_melonjak',
                'level' => 'danger',
                'judul' => 'Biaya operasional melonjak',
                'detail' => sprintf(
                    'Biaya bulan ini Rp %s, %s%% dari rata-rata 6 bulan lalu (Rp %s).',
                    number_format($biayaCurrent, 0, ',', '.'),
                    number_format(($biayaCurrent / $bStat['avg']) * 100, 0),
                    number_format($bStat['avg'], 0, ',', '.')
                ),
                'angka_sekarang' => $biayaCurrent,
                'rata_rata' => $bStat['avg'],
            ];
        }

        // Deteksi item biaya yang abnormal besar
        $topBiaya = BudgetActivity::with('budget.category')
            ->whereYear('activity_date', $year)->whereMonth('activity_date', $month)
            ->orderByDesc('amount')->limit(3)->get();

        $rataBiayaItem = $bStat['avg'] > 0 ? $bStat['avg'] / 6 : 0;
        foreach ($topBiaya as $b) {
            if ($rataBiayaItem > 0 && (float) $b->amount > $rataBiayaItem * 2) {
                $anomalies[] = [
                    'tipe' => 'biaya_item_besar',
                    'level' => 'warning',
                    'judul' => 'Pengeluaran besar: '.$b->name,
                    'detail' => sprintf(
                        '"%s" sebesar Rp %s (kategori: %s) jauh di atas rata-rata biaya bulanan per item.',
                        $b->name,
                        number_format((float) $b->amount, 0, ',', '.'),
                        $b->budget?->category?->name ?? '-'
                    ),
                    'angka_sekarang' => (float) $b->amount,
                    'rata_rata' => $rataBiayaItem,
                ];
            }
        }

        return [
            'bulan' => $current->translatedFormat('F Y'),
            'pemasukan' => [
                'current' => $pemasukanCurrent,
                'history' => $pemasukanHist,
                'avg' => $pStat['avg'],
            ],
            'biaya' => [
                'current' => $biayaCurrent,
                'history' => $biayaHist,
                'avg' => $bStat['avg'],
            ],
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Forecast sederhana 3 bulan ke depan menggunakan rata-rata bergerak 6 bulan +
     * tren linear (least-squares slope) dari 6 bulan terakhir.
     */
    public function forecastNext3Months(): array
    {
        $now = Carbon::now();

        $hasIncomes = Schema::hasTable('incomes');

        // ambil 6 bulan terakhir terhitung dari bulan lalu (biar lengkap)
        $start = $now->copy()->subMonths(6)->startOfMonth();

        $history = [];
        for ($i = 0; $i < 6; $i++) {
            $c = $start->copy()->addMonths($i);
            $history[] = [
                'tahun' => $c->year,
                'bulan' => $c->month,
                'label' => $c->translatedFormat('M Y'),
                'pemasukan' => $hasIncomes
                    ? (float) Income::whereYear('income_date', $c->year)->whereMonth('income_date', $c->month)->sum('amount')
                    : 0,
                'biaya' => (float) BudgetActivity::whereYear('activity_date', $c->year)
                    ->whereMonth('activity_date', $c->month)->sum('amount'),
            ];
        }

        $pemasukanSeries = array_column($history, 'pemasukan');
        $biayaSeries     = array_column($history, 'biaya');

        $forecast = [];
        for ($k = 1; $k <= 3; $k++) {
            $c = $now->copy()->addMonths($k);
            $forecast[] = [
                'tahun' => $c->year,
                'bulan' => $c->month,
                'label' => $c->translatedFormat('M Y'),
                'pemasukan' => max(0, $this->projectNext($pemasukanSeries, $k)),
                'biaya' => max(0, $this->projectNext($biayaSeries, $k)),
            ];
        }
        foreach ($forecast as &$f) {
            $f['laba'] = $f['pemasukan'] - $f['biaya'];
        }
        unset($f);

        return [
            'history' => $history,
            'forecast' => $forecast,
        ];
    }

    /**
     * Linear projection: y = intercept + slope * x
     * x = 0..n-1, lalu prediksi y untuk x = n + k - 1.
     */
    private function projectNext(array $series, int $k): float
    {
        $n = count($series);
        if ($n === 0) return 0;
        if ($n === 1) return (float) $series[0];

        $xs = range(0, $n - 1);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($series) / $n;

        $num = 0.0; $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($xs[$i] - $meanX) * ($series[$i] - $meanY);
            $den += ($xs[$i] - $meanX) ** 2;
        }
        $slope = $den == 0 ? 0 : $num / $den;
        $intercept = $meanY - $slope * $meanX;

        return $intercept + $slope * ($n + $k - 1);
    }
}
