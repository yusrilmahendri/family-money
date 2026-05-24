<?php

namespace App\Http\Controllers;

use App\Exports\DashboardExport;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Income;
use App\Models\RecurringTransaction;
use App\Models\Saldo;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Service\RecurringTransactionRunner;
use App\Service\SaldoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    protected $saldoService;

    public function __construct(SaldoService $saldoService)
    {
        $this->saldoService = $saldoService;
    }

    public function index(RecurringTransactionRunner $runner)
    {
        $hasIncomes = Schema::hasTable('incomes');
        $hasRecurring = Schema::hasTable('recurring_transactions');

        if ($hasRecurring) {
            $runner->runDue();
        }

        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        // Pemasukan sudah auto-sync ke tabel saldos lewat IncomeController.
        $hasIncomeIdColumn = Schema::hasColumn('saldos', 'income_id');
        $totalSaldoMasuk   = $hasIncomeIdColumn
            ? (float) Saldo::whereNull('income_id')->sum('amount')
            : (float) Saldo::sum('amount');
        $totalPemasukan    = $hasIncomeIdColumn
            ? (float) Saldo::whereNotNull('income_id')->sum('amount')
            : ($hasIncomes ? (float) Income::sum('amount') : 0);
        $totalSaldo = (float) Saldo::sum('amount');
        $totalPengeluaran = (float) Transaction::sum('amount');
        $totalAnggaran = (float) Budget::sum('amount');
        $sisaSaldo = $totalSaldo - $totalAnggaran - $totalPengeluaran;

        // Bulan ini
        $pemasukanBulanIni = $hasIncomes
            ? (float) Income::whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('amount')
            : 0;
        $pengeluaranBulanIni = (float) Transaction::whereYear('transaction_date', $year)->whereMonth('transaction_date', $month)->sum('amount');
        $biayaUsahaBulanIni = (float) BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $month)->sum('amount');
        $labaBulanIni = $pemasukanBulanIni - $biayaUsahaBulanIni;

        $lastTrans = Transaction::latest('transaction_date')->first();
        $jumlahTransaksi = Transaction::count();
        $categories = Category::all();

        // Cashflow 12 bulan: pemasukan vs pengeluaran
        $cashflowBulanan = [];
        for ($i = 1; $i <= 12; $i++) {
            $bulanNama = Carbon::create()->month($i)->translatedFormat('M');
            $income = $hasIncomes
                ? (float) Income::whereYear('income_date', $year)->whereMonth('income_date', $i)->sum('amount')
                : 0;
            $expense = (float) Transaction::whereYear('transaction_date', $year)->whereMonth('transaction_date', $i)->sum('amount');
            $biaya = (float) BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $i)->sum('amount');

            $cashflowBulanan[] = [
                'bulan' => $bulanNama,
                'pemasukan' => $income,
                'pengeluaran' => $expense,
                'biaya_usaha' => $biaya,
                'laba' => $income - $biaya,
            ];
        }

        // Legacy chart data (pengeluaran bulanan)
        $pengeluaranBulanan = array_map(fn ($c) => [
            'bulan' => $c['bulan'],
            'total' => $c['pengeluaran'],
        ], $cashflowBulanan);

        // Saldo per kategori (Saldo sudah termasuk pemasukan auto-sync)
        $saldoPerKategori = [];
        foreach ($categories as $category) {
            $total = (float) Saldo::where('category_id', $category->id)->sum('amount');

            if ($total > 0) {
                $saldoPerKategori[] = ['name' => $category->name, 'y' => $total];
            }
        }

        $comparison = [
            ['name' => 'Pemasukan', 'y' => $totalSaldo],
            ['name' => 'Pengeluaran', 'y' => $totalPengeluaran],
        ];

        // Top 5 aktivitas anggaran bulan ini
        $topAktivitas = BudgetActivity::with('budget.category')
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

        // Laba/rugi per jenis usaha bulan ini
        $labaPerUsaha = $categories->map(function (Category $cat) use ($year, $month, $hasIncomes) {
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

        // Recurring yang akan jatuh tempo
        $recurringDue = $hasRecurring
            ? RecurringTransaction::where('active', true)
                ->whereDate('next_due', '<=', $now->copy()->addDays(7))
                ->orderBy('next_due')
                ->limit(5)
                ->get()
            : collect();

        return view('dashboard', [
            'totalSaldo' => $totalSaldo,
            'totalSaldoMasuk' => $totalSaldoMasuk,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'totalAnggaran' => $totalAnggaran,
            'sisaSaldo' => $sisaSaldo,
            'pemasukanBulanIni' => $pemasukanBulanIni,
            'pengeluaranBulanIni' => $pengeluaranBulanIni,
            'biayaUsahaBulanIni' => $biayaUsahaBulanIni,
            'labaBulanIni' => $labaBulanIni,
            'lastTrans' => $lastTrans,
            'jumlahTransaksi' => $jumlahTransaksi,
            'pengeluaranBulanan' => $pengeluaranBulanan,
            'cashflowBulanan' => $cashflowBulanan,
            'categories' => $categories,
            'saldoPerKategori' => $saldoPerKategori,
            'comparison' => $comparison,
            'topAktivitas' => $topAktivitas,
            'labaPerUsaha' => $labaPerUsaha,
            'recurringDue' => $recurringDue,
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
