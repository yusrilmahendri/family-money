<?php

namespace App\Service;

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Income;
use App\Models\RecurringTransaction;
use App\Models\Saldo;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Mengumpulkan ringkasan data keuangan agar bisa di-feed ke AI.
 */
class FinanceContextService
{
    /**
     * Snapshot lengkap untuk chatbot / insight.
     */
    public function snapshot(): array
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        $hasIncomes = Schema::hasTable('incomes');
        $hasRecurring = Schema::hasTable('recurring_transactions');

        $totalSaldo = (float) Saldo::sum('amount');
        $totalAnggaran = (float) Budget::sum('amount');
        $totalBiaya = (float) BudgetActivity::sum('amount');
        $totalTransaksi = (float) Transaction::sum('amount');
        $saldoBebas = $totalSaldo - $totalAnggaran - $totalTransaksi;

        $pemasukanBulanIni = $hasIncomes
            ? (float) Income::whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
            : 0;
        $biayaBulanIni = (float) BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $month)->sum('amount');
        $trxBulanIni = (float) Transaction::whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->sum('amount');
        $labaBulanIni = $pemasukanBulanIni - $biayaBulanIni;

        $perKategori = Category::orderBy('name')->get()->map(function (Category $cat) use ($year, $month, $hasIncomes) {
            $budgetIds = Budget::where('category_id', $cat->id)->pluck('id');
            return [
                'jenis_usaha' => $cat->name,
                'saldo' => (float) Saldo::where('category_id', $cat->id)->sum('amount'),
                'anggaran' => (float) Budget::where('category_id', $cat->id)->sum('amount'),
                'biaya_total' => (float) BudgetActivity::whereIn('budget_id', $budgetIds)->sum('amount'),
                'pemasukan_bulan_ini' => $hasIncomes
                    ? (float) Income::where('category_id', $cat->id)
                        ->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
                    : 0,
                'biaya_bulan_ini' => (float) BudgetActivity::whereIn('budget_id', $budgetIds)
                    ->whereYear('activity_date', $year)->whereMonth('activity_date', $month)->sum('amount'),
            ];
        })->filter(fn ($r) => $r['saldo'] > 0 || $r['anggaran'] > 0 || $r['biaya_total'] > 0)->values()->all();

        $topBiayaBulanIni = BudgetActivity::with('budget.category')
            ->whereYear('activity_date', $year)->whereMonth('activity_date', $month)
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'nama' => $a->name,
                'jenis_usaha' => $a->budget?->category?->name,
                'jumlah' => (float) $a->amount,
                'tanggal' => optional($a->activity_date)->format('Y-m-d'),
            ])->all();

        $utang = [];
        if (Schema::hasTable('debts')) {
            $utang = Debt::orderByDesc('created_at')->limit(10)->get()->map(function (Debt $d) {
                $sisa = (float) ($d->remaining_balance ?? $d->principal_total ?? 0);
                return [
                    'nama' => $d->title,
                    'pokok' => (float) ($d->principal_total ?? 0),
                    'sisa' => $sisa,
                    'cicilan_bulanan' => (float) ($d->monthly_installment ?? 0),
                ];
            })->all();
        }

        $goals = [];
        if (Schema::hasTable('savings_goals')) {
            $goals = SavingsGoal::orderByDesc('created_at')->limit(10)->get()->map(function (SavingsGoal $g) {
                return [
                    'nama' => $g->title,
                    'target' => (float) ($g->target_amount ?? 0),
                    'terkumpul' => $g->savedTotal(),
                    'deadline' => optional($g->deadline)->format('Y-m-d'),
                ];
            })->all();
        }

        $recurring = [];
        if ($hasRecurring) {
            $recurring = RecurringTransaction::where('active', true)
                ->orderBy('next_due')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'nama' => $r->name,
                    'jumlah' => (float) $r->amount,
                    'frekuensi' => $r->frequency,
                    'jatuh_tempo_berikutnya' => optional($r->next_due)->format('Y-m-d'),
                ])->all();
        }

        return [
            'periode' => $now->translatedFormat('F Y'),
            'tanggal_hari_ini' => $now->toDateString(),
            'ringkasan_global' => [
                'total_saldo_seluruh_kategori' => $totalSaldo,
                'total_anggaran' => $totalAnggaran,
                'total_biaya_operasional' => $totalBiaya,
                'total_transaksi_pribadi' => $totalTransaksi,
                'saldo_bebas' => $saldoBebas,
            ],
            'bulan_berjalan' => [
                'pemasukan_usaha' => $pemasukanBulanIni,
                'biaya_operasional' => $biayaBulanIni,
                'transaksi_pribadi' => $trxBulanIni,
                'laba_rugi' => $labaBulanIni,
            ],
            'per_jenis_usaha' => $perKategori,
            'top_biaya_bulan_ini' => $topBiayaBulanIni,
            'utang' => $utang,
            'goals_tabungan' => $goals,
            'transaksi_berulang' => $recurring,
        ];
    }

    /**
     * Series 12 bulan (untuk forecast & deteksi anomali).
     */
    public function series12Bulan(?int $year = null): array
    {
        $year = $year ?: (int) now()->year;
        $hasIncomes = Schema::hasTable('incomes');

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $pemasukan = $hasIncomes
                ? (float) Income::whereYear('income_date', $year)->whereMonth('income_date', $m)->sum('amount')
                : 0;
            $biaya = (float) BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $m)->sum('amount');
            $trx = (float) Transaction::whereYear('transaction_date', $year)->whereMonth('transaction_date', $m)->sum('amount');

            $rows[] = [
                'tahun' => $year,
                'bulan' => $m,
                'label' => Carbon::create($year, $m, 1)->translatedFormat('M'),
                'pemasukan' => $pemasukan,
                'biaya' => $biaya,
                'transaksi_pribadi' => $trx,
                'laba' => $pemasukan - $biaya,
            ];
        }
        return $rows;
    }

    /**
     * Format snapshot menjadi blok teks pendek (untuk dimasukkan ke system prompt).
     */
    public function snapshotAsText(): string
    {
        $s = $this->snapshot();
        return "Data keuangan saat ini (".$s['tanggal_hari_ini']."):\n".
            json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
