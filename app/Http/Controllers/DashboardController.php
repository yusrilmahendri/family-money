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

        $totalSaldoMasuk = (float) Saldo::sum('amount');
        $totalPemasukan = $hasIncomes ? (float) Income::sum('amount') : 0;
        $totalSaldo = $totalSaldoMasuk + $totalPemasukan;
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

        // Saldo per kategori (saldo + pemasukan)
        $saldoPerKategori = [];
        foreach ($categories as $category) {
            $total = (float) Saldo::where('category_id', $category->id)->sum('amount')
                + ($hasIncomes ? (float) Income::where('category_id', $category->id)->sum('amount') : 0);

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
        $labaPerUsaha = $categories->map(function (Category $cat) use ($year, $month) {
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
        $month = $request->month;
        $year = $request->year;
        $categoryId = $request->category;

        $saldoQuery = Saldo::query();
        $incomeQuery = Income::query();
        $pengeluaranQuery = Transaction::query();

        if ($month) {
            $incomeQuery->whereMonth('income_date', $month);
            $pengeluaranQuery->whereMonth('transaction_date', $month);
            $saldoQuery->whereMonth('created_at', $month);
        }

        if ($year) {
            $incomeQuery->whereYear('income_date', $year);
            $pengeluaranQuery->whereYear('transaction_date', $year);
            $saldoQuery->whereYear('created_at', $year);
        }

        if ($categoryId) {
            $saldoQuery->where('category_id', $categoryId);
            $incomeQuery->where('category_id', $categoryId);
            $pengeluaranQuery->where('category_id', $categoryId);
        }

        $totalSaldo = (float) $saldoQuery->sum('amount') + (float) $incomeQuery->sum('amount');
        $totalPengeluaran = (float) $pengeluaranQuery->sum('amount');
        $sisaSaldo = $totalSaldo - $totalPengeluaran;

        $comparison = [
            ['name' => 'Pemasukan', 'y' => $totalSaldo],
            ['name' => 'Pengeluaran', 'y' => $totalPengeluaran],
        ];

        $saldoPerKategori = [];
        $categories = Category::when($categoryId, fn ($q) => $q->where('id', $categoryId))->get();

        foreach ($categories as $category) {
            $total = (float) Saldo::where('category_id', $category->id)
                ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->sum('amount')
                + (float) Income::where('category_id', $category->id)
                    ->when($month, fn ($q) => $q->whereMonth('income_date', $month))
                    ->when($year, fn ($q) => $q->whereYear('income_date', $year))
                    ->sum('amount');

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
        $totalSaldo = (float) Saldo::sum('amount') + (float) Income::sum('amount');
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
            $totalSaldoCategory = (float) Saldo::where('category_id', $category->id)->sum('amount')
                + (float) Income::where('category_id', $category->id)->sum('amount');

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
