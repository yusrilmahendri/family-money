<?php

namespace App\Http\Controllers;

use App\Exports\DashboardExport;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Income;
use App\Models\Saldo;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\RecurringTransactionRunner;
use App\Services\SaldoGlobalService;
use App\Support\FinanceContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    protected $saldoGlobalService;

    public function __construct(SaldoGlobalService $saldoGlobalService)
    {
        $this->saldoGlobalService = $saldoGlobalService;
    }

    public function index(RecurringTransactionRunner $runner)
    {
        // Guard ringan: wajib pilih konteks dulu (tanpa middleware)
        if (! FinanceContext::isSelected()) {
            return redirect()->route('apps.index');
        }

        if (Schema::hasTable('recurring_transactions')) {
            $runner->runDue();
        }

        // Dashboard dipisah per konteks (feature set berbeda)
        return FinanceContext::current() === FinanceContext::USAHA_KEBUN
            ? $this->farmDashboard()
            : $this->personalDashboard();
    }

    /**
     * Saldo GLOBAL (shared) — bersifat dinamis.
     *
     * - masuk  : seluruh saldo yang masuk sejak awal s/d akhir bulan berjalan
     *            (saldo manual + pemasukan usaha yang auto-sync).
     * - keluar : seluruh pengeluaran NYATA (transaksi pribadi + biaya operasional usaha),
     *            apa pun konteksnya — keduanya mengurangi saldo yang sama.
     * - sisa   : masuk - keluar (inilah angka "Saldo Global" yang ditampilkan).
     */
    protected function saldoGlobal(Carbon $now): array
    {
        return $this->saldoGlobalService->getBreakdown();
    }

    /**
     * Dashboard Keuangan PRIBADI.
     * Widget: pengeluaran pribadi bulan ini, total cicilan, goals tabungan.
     */
    protected function personalDashboard()
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        // Saldo GLOBAL/shared — event-based (income - cashout), dinamis
        $saldo = $this->saldoGlobal($now);
        $totalSaldo = $saldo['saldo'];

        // Transaksi pribadi
        $trxContext = FinanceContext::PRIBADI;
        $pengeluaranBulanIni = (float) Transaction::forContext($trxContext)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->sum('amount');
        $totalPengeluaran = (float) Transaction::forContext($trxContext)->sum('amount');
        $jumlahTransaksiBulanIni = (int) Transaction::forContext($trxContext)
            ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->count();
        $lastTrans = Transaction::forContext($trxContext)->latest('transaction_date')->first();

        // Utang & cicilan
        $totalCicilan = Schema::hasTable('debts') ? (float) Debt::sum('monthly_installment') : 0;
        $totalSisaUtang = Schema::hasTable('debts') ? (float) Debt::sum('remaining_balance') : 0;
        $jumlahUtang = Schema::hasTable('debts') ? (int) Debt::count() : 0;

        // Goals tabungan
        $goals = Schema::hasTable('savings_goals')
            ? SavingsGoal::orderBy('title')->get()->map(function (SavingsGoal $g) {
                $saved = (float) $g->savedTotal();
                $target = (float) $g->target_amount;

                return [
                    'title' => $g->title,
                    'target' => $target,
                    'saved' => $saved,
                    'pct' => $target > 0 ? min(100, round($saved / $target * 100, 1)) : 0,
                ];
            })
            : collect();

        // Pengeluaran pribadi 12 bulan (chart)
        $pengeluaranBulanan = [];
        for ($i = 1; $i <= 12; $i++) {
            $pengeluaranBulanan[] = [
                'bulan' => Carbon::create()->month($i)->translatedFormat('M'),
                'total' => (float) Transaction::forContext($trxContext)
                    ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $i)->sum('amount'),
            ];
        }

        return view('dashboard.personal', [
            'financeContextLabel' => FinanceContext::label($trxContext),
            'totalSaldo' => $totalSaldo,
            'saldoMasuk' => $saldo['income'],
            'saldoKeluar' => $saldo['cash_out'],
            'saldoBreakdown' => $saldo,
            'pengeluaranBulanIni' => $pengeluaranBulanIni,
            'totalPengeluaran' => $totalPengeluaran,
            'jumlahTransaksiBulanIni' => $jumlahTransaksiBulanIni,
            'lastTrans' => $lastTrans,
            'totalCicilan' => $totalCicilan,
            'totalSisaUtang' => $totalSisaUtang,
            'jumlahUtang' => $jumlahUtang,
            'goals' => $goals,
            'pengeluaranBulanan' => $pengeluaranBulanan,
        ]);
    }

    /**
     * Dashboard Keuangan USAHA KEBUN.
     * Widget: pemasukan usaha bulan ini, biaya operasional, laba/rugi, top biaya.
     */
    protected function farmDashboard()
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        $hasIncomes = Schema::hasTable('incomes');

        // Saldo GLOBAL/shared — event-based (income - cashout), dinamis
        $saldo = $this->saldoGlobal($now);
        $totalSaldo = $saldo['saldo'];

        // Pemasukan usaha
        $pemasukanBulanIni = $hasIncomes
            ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)
                ->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
            : 0;
        $totalPemasukan = $hasIncomes ? (float) Income::forContext(FinanceContext::USAHA_KEBUN)->sum('amount') : 0;

        // Biaya operasional (BudgetActivity)
        $biayaBulanIni = (float) BudgetActivity::whereYear('activity_date', $year)
            ->whereMonth('activity_date', $month)->sum('amount');
        $totalBiaya = (float) BudgetActivity::sum('amount');

        $labaBulanIni = $pemasukanBulanIni - $biayaBulanIni;

        // Top 5 biaya operasional bulan ini
        $topBiaya = BudgetActivity::with('budget.category')
            ->whereYear('activity_date', $year)
            ->whereMonth('activity_date', $month)
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'name' => $a->name,
                'category' => $a->budget?->category?->name ?? '—',
                'amount' => (float) $a->amount,
            ]);

        // Cashflow usaha 12 bulan (pemasukan vs biaya)
        $cashflowBulanan = [];
        for ($i = 1; $i <= 12; $i++) {
            $income = $hasIncomes
                ? (float) Income::whereYear('income_date', $year)->whereMonth('income_date', $i)->sum('amount')
                : 0;
            $biaya = (float) BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $i)->sum('amount');
            $cashflowBulanan[] = [
                'bulan' => Carbon::create()->month($i)->translatedFormat('M'),
                'pemasukan' => $income,
                'biaya' => $biaya,
                'laba' => $income - $biaya,
            ];
        }

        // Laba/rugi per jenis usaha bulan ini
        $labaPerUsaha = Category::forContext(FinanceContext::USAHA_KEBUN)->get()
            ->map(function (Category $cat) use ($year, $month, $hasIncomes) {
                $pendapatan = $hasIncomes
                    ? (float) Income::where('category_id', $cat->id)
                        ->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
                    : 0;
                $biaya = (float) BudgetActivity::whereIn(
                    'budget_id',
                    Budget::where('category_id', $cat->id)->pluck('id')
                )->whereYear('activity_date', $year)->whereMonth('activity_date', $month)->sum('amount');

                return [
                    'name' => $cat->name,
                    'pendapatan' => $pendapatan,
                    'biaya' => $biaya,
                    'laba' => $pendapatan - $biaya,
                ];
            })->filter(fn ($r) => $r['pendapatan'] > 0 || $r['biaya'] > 0)->values();

        return view('dashboard.farm', [
            'financeContextLabel' => FinanceContext::label(FinanceContext::USAHA_KEBUN),
            'totalSaldo' => $totalSaldo,
            'saldoMasuk' => $saldo['income'],
            'saldoKeluar' => $saldo['cash_out'],
            'saldoBreakdown' => $saldo,
            'pemasukanBulanIni' => $pemasukanBulanIni,
            'totalPemasukan' => $totalPemasukan,
            'biayaBulanIni' => $biayaBulanIni,
            'totalBiaya' => $totalBiaya,
            'labaBulanIni' => $labaBulanIni,
            'topBiaya' => $topBiaya,
            'cashflowBulanan' => $cashflowBulanan,
            'labaPerUsaha' => $labaPerUsaha,
        ]);
    }

    public function filterSummary(Request $request)
    {
        $hasIncomes = Schema::hasTable('incomes');

        $month = $request->month;
        $year = $request->year;
        $categoryId = $request->category;

        // Setelah auto-sync, semua "uang masuk" sudah ada di Saldo.
        // Pakai Saldo untuk total dana, Income hanya untuk perhitungan pendapatan
        // khusus (laporan), jadi tidak perlu ditambahkan di sini.
        $saldoQuery = Saldo::query();
        $incomeQuery = null;
        $pengeluaranQuery = Transaction::query();

        if ($month) {
            if ($incomeQuery) {
                $incomeQuery->whereMonth('income_date', $month);
            }
            $pengeluaranQuery->whereMonth('transaction_date', $month);
            $saldoQuery->whereMonth('created_at', $month);
        }

        if ($year) {
            if ($incomeQuery) {
                $incomeQuery->whereYear('income_date', $year);
            }
            $pengeluaranQuery->whereYear('transaction_date', $year);
            $saldoQuery->whereYear('created_at', $year);
        }

        if ($categoryId) {
            $saldoQuery->where('category_id', $categoryId);
            if ($incomeQuery) {
                $incomeQuery->where('category_id', $categoryId);
            }
            $pengeluaranQuery->where('category_id', $categoryId);
        }

        $totalPemasukan = $incomeQuery ? (float) $incomeQuery->sum('amount') : 0;
        $totalSaldo = (float) $saldoQuery->sum('amount') + $totalPemasukan;
        $totalPengeluaran = (float) $pengeluaranQuery->sum('amount');
        $sisaSaldo = $totalSaldo - $totalPengeluaran;

        $comparison = [
            ['name' => 'Pemasukan', 'y' => $totalSaldo],
            ['name' => 'Pengeluaran', 'y' => $totalPengeluaran],
        ];

        $saldoPerKategori = [];
        $categories = Category::when($categoryId, fn ($q) => $q->where('id', $categoryId))->get();

        foreach ($categories as $category) {
            $pemasukanKategori = $hasIncomes
                ? (float) Income::where('category_id', $category->id)
                    ->when($month, fn ($q) => $q->whereMonth('income_date', $month))
                    ->when($year, fn ($q) => $q->whereYear('income_date', $year))
                    ->sum('amount')
                : 0;

            $total = (float) Saldo::where('category_id', $category->id)
                ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->sum('amount')
                + $pemasukanKategori;

            if ($total > 0) {
                $saldoPerKategori[] = ['name' => $category->name, 'y' => $total];
            }
        }

        return response()->json([
            'comparison' => $comparison,
            'saldoPerKategori' => $saldoPerKategori,
            'summary' => [
                'totalSaldo' => $totalSaldo,
                'totalPengeluaran' => $totalPengeluaran,
                'sisaSaldo' => $sisaSaldo,
            ],
        ]);
    }

    public function exportExcel()
    {
        $data = $this->getDashboardData();

        return Excel::download(new DashboardExport($data), 'laporan-dashboard-'.date('Y-m-d').'.xlsx');
    }

    public function exportPdf()
    {
        $data = $this->getDashboardData();

        $pdf = Pdf::loadView('dashboard-pdf', $data);

        return $pdf->download('laporan-dashboard-'.date('Y-m-d').'.pdf');
    }

    private function getDashboardData()
    {
        // Saldo sudah termasuk pemasukan auto-sync; tidak perlu menjumlah Income lagi.
        $totalSaldo = (float) Saldo::sum('amount');
        $totalPengeluaran = (float) Transaction::sum('amount');
        $sisaSaldo = $totalSaldo - $totalPengeluaran;
        $jumlahTransaksi = Transaction::count();
        $categories = Category::all();

        $pengeluaranBulanan = [];
        for ($i = 1; $i <= 12; $i++) {
            $bulanNama = Carbon::create()->month($i)->translatedFormat('M');
            $total = Transaction::whereMonth('created_at', $i)->sum('amount');

            $pengeluaranBulanan[] = ['bulan' => $bulanNama, 'total' => $total];
        }

        $saldoPerKategori = [];
        foreach ($categories as $category) {
            $totalSaldoCategory = (float) Saldo::where('category_id', $category->id)->sum('amount');

            if ($totalSaldoCategory > 0) {
                $saldoPerKategori[] = ['name' => $category->name, 'y' => $totalSaldoCategory];
            }
        }

        return [
            'totalSaldo' => $totalSaldo,
            'totalPengeluaran' => $totalPengeluaran,
            'sisaSaldo' => $sisaSaldo,
            'jumlahTransaksi' => $jumlahTransaksi,
            'pengeluaranBulanan' => $pengeluaranBulanan,
            'saldoPerKategori' => $saldoPerKategori,
        ];
    }
}
