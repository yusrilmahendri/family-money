<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Saldo;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class BudgetController extends Controller
{
    public function data()
    {
        $q = Budget::query()
            ->with('category')
            ->withSum('activities as activities_total', 'amount')
            ->orderBy('periode', 'desc');

        return DataTables::of($q)
            ->editColumn('amount', fn (Budget $b) => 'Rp '.number_format((float) $b->amount, 0, ',', '.'))
            ->editColumn('description', fn (Budget $b) => $b->description ?: '-')
            ->addColumn('category', fn (Budget $b) => $b->category?->name ?? '—')
            ->addColumn('terpakai', function (Budget $b) {
                $aktivitas = (float) ($b->activities_total ?? 0);

                return 'Rp '.number_format($aktivitas, 0, ',', '.');
            })
            ->addColumn('sisa_anggaran', function (Budget $b) {
                $aktivitas = (float) ($b->activities_total ?? 0);
                $sisa = (float) $b->amount - $aktivitas;

                $color = $sisa < 0 ? 'text-danger' : 'text-success';

                return '<span class="'.$color.'"><strong>Rp '.number_format($sisa, 0, ',', '.').'</strong></span>';
            })
            ->editColumn('periode', fn (Budget $b) => $b->periode?->format('d M Y') ?? '-')
            ->addColumn('action', 'budgets.action')
            ->rawColumns(['action', 'sisa_anggaran'])
            ->toJson();
    }

    public function index()
    {
        $now = Carbon::now();

        // Ringkasan global
        $totalSaldo = (float) Saldo::sum('amount');
        $totalDianggarkan = (float) Budget::sum('amount');
        $totalTransaksi = (float) Transaction::sum('amount');
        $saldoBebas = $totalSaldo - $totalDianggarkan - $totalTransaksi;

        // Plafon bulan ini (untuk indikator pengeluaran bulan berjalan)
        $budgetCap = (float) Budget::query()
            ->whereYear('periode', $now->year)
            ->whereMonth('periode', $now->month)
            ->sum('amount');

        if ($budgetCap <= 0) {
            $budgetCap = (float) (Budget::latest('periode')->value('amount') ?? 0);
        }

        $spent = (float) Transaction::query()
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->sum('amount');

        $remaining = $budgetCap - $spent;
        $updatedBudget = Budget::latest('periode')->first();

        // Rincian per jenis usaha (kategori)
        $rincianPerKategori = Category::orderBy('name')
            ->get()
            ->map(function (Category $cat) {
                $saldo = (float) Saldo::where('category_id', $cat->id)->sum('amount');
                $anggaran = (float) Budget::where('category_id', $cat->id)->sum('amount');
                $aktivitas = (float) BudgetActivity::whereIn(
                    'budget_id',
                    Budget::where('category_id', $cat->id)->pluck('id')
                )->sum('amount');
                $transaksiKategori = (float) Transaction::where('category_id', $cat->id)->sum('amount');

                return [
                    'name' => $cat->name,
                    'saldo' => $saldo,
                    'anggaran' => $anggaran,
                    'transaksi' => $transaksiKategori,
                    'sisa_saldo' => $saldo - $anggaran - $transaksiKategori,
                    'aktivitas' => $aktivitas,
                    'sisa_anggaran' => $anggaran - $aktivitas,
                ];
            })
            ->filter(fn ($row) => $row['saldo'] > 0 || $row['anggaran'] > 0 || $row['transaksi'] > 0)
            ->values();

        return view('budgets.index', [
            'title' => 'Anggaran',
            'budget_cap' => $budgetCap,
            'spent_this_month' => $spent,
            'remaining_budget' => $remaining,
            'updated_budget' => $updatedBudget,
            'over_budget' => $budgetCap > 0 && $spent > $budgetCap,
            'total_saldo' => $totalSaldo,
            'total_dianggarkan' => $totalDianggarkan,
            'total_transaksi' => $totalTransaksi,
            'saldo_bebas' => $saldoBebas,
            'rincian_per_kategori' => $rincianPerKategori,
        ]);
    }

    public function create()
    {
        return view('budgets.create', [
            'title' => 'Tambah Anggaran',
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'periode' => ['required', 'date'],
            'category_id' => ['required', 'exists:categories,id'],
        ], [
            'category_id.required' => 'Jenis usaha wajib dipilih.',
            'category_id.exists' => 'Jenis usaha tidak valid.',
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        $this->ensureSaldoEnough((int) $validated['category_id'], $amount);

        Budget::create([
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'amount_saldo' => 0,
            'periode' => $validated['periode'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('budgets.index')->with('success', 'Anggaran berhasil disimpan. Saldo jenis usaha telah dipotong.');
    }

    public function show(Budget $budget)
    {
        $budget->load(['category', 'activities']);

        $aktivitasTotal = (float) $budget->activities->sum('amount');
        $sisaAnggaran = (float) $budget->amount - $aktivitasTotal;

        // Info saldo kategori
        $saldoKategori = 0.0;
        $anggaranKategoriTotal = 0.0;
        $transaksiKategoriTotal = 0.0;
        if ($budget->category_id) {
            $saldoKategori = (float) Saldo::where('category_id', $budget->category_id)->sum('amount');
            $anggaranKategoriTotal = (float) Budget::where('category_id', $budget->category_id)->sum('amount');
            $transaksiKategoriTotal = (float) Transaction::where('category_id', $budget->category_id)->sum('amount');
        }

        return view('budgets.show', [
            'title' => 'Detail Anggaran',
            'budget' => $budget,
            'aktivitas_total' => $aktivitasTotal,
            'sisa_anggaran' => $sisaAnggaran,
            'saldo_kategori' => $saldoKategori,
            'anggaran_kategori_total' => $anggaranKategoriTotal,
            'transaksi_kategori_total' => $transaksiKategoriTotal,
        ]);
    }

    public function edit(Budget $budget)
    {
        return view('budgets.edit', [
            'title' => 'Ubah Anggaran',
            'budget' => $budget,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'amount' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'periode' => ['required', 'date'],
            'category_id' => ['required', 'exists:categories,id'],
        ], [
            'category_id.required' => 'Jenis usaha wajib dipilih.',
            'category_id.exists' => 'Jenis usaha tidak valid.',
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        $this->ensureSaldoEnough((int) $validated['category_id'], $amount, $budget->id);

        $budget->update([
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'periode' => $validated['periode'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('budgets.index')->with('success', 'Anggaran diperbarui.');
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();

        return redirect()->route('budgets.index')->with('success', 'Anggaran dihapus. Saldo dikembalikan ke saldo bebas.');
    }

    /**
     * Simpan aktivitas / pengeluaran anggaran (upah kerja, pupuk, dll).
     * Memotong anggaran (bukan saldo).
     */
    public function storeActivity(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama aktivitas wajib diisi.',
            'amount.required' => 'Jumlah biaya wajib diisi.',
            'activity_date.required' => 'Tanggal aktivitas wajib diisi.',
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        $aktivitasTotal = (float) BudgetActivity::where('budget_id', $budget->id)->sum('amount');
        $sisa = (float) $budget->amount - $aktivitasTotal;

        if ($amount > $sisa + 0.01) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Sisa anggaran tidak cukup. Sisa: Rp %s, jumlah yang dimasukkan: Rp %s.',
                    number_format($sisa, 0, ',', '.'),
                    number_format($amount, 0, ',', '.')
                ),
            ]);
        }

        BudgetActivity::create([
            'budget_id' => $budget->id,
            'name' => $validated['name'],
            'amount' => $amount,
            'activity_date' => $validated['activity_date'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('budgets.show', $budget)->with('success', 'Aktivitas anggaran berhasil dicatat.');
    }

    public function updateActivity(Request $request, Budget $budget, BudgetActivity $activity)
    {
        if ($activity->budget_id !== $budget->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        $aktivitasLain = (float) BudgetActivity::where('budget_id', $budget->id)
            ->where('id', '!=', $activity->id)
            ->sum('amount');
        $sisa = (float) $budget->amount - $aktivitasLain;

        if ($amount > $sisa + 0.01) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Sisa anggaran tidak cukup. Sisa: Rp %s.',
                    number_format($sisa, 0, ',', '.')
                ),
            ]);
        }

        $activity->update([
            'name' => $validated['name'],
            'amount' => $amount,
            'activity_date' => $validated['activity_date'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('budgets.show', $budget)->with('info', 'Aktivitas diperbarui.');
    }

    public function destroyActivity(Budget $budget, BudgetActivity $activity)
    {
        if ($activity->budget_id !== $budget->id) {
            abort(404);
        }

        $activity->delete();

        return redirect()->route('budgets.show', $budget)->with('danger', 'Aktivitas dihapus.');
    }

    /**
     * Endpoint AJAX: info saldo & anggaran untuk satu kategori.
     * Tersedia = saldo - anggaran lain - transaksi pribadi pada kategori tsb.
     */
    public function categoryInfo(Category $category, Request $request)
    {
        $excludeBudgetId = $request->query('exclude_budget_id');

        $saldo = (float) Saldo::where('category_id', $category->id)->sum('amount');

        $budgetQuery = Budget::where('category_id', $category->id);
        if ($excludeBudgetId) {
            $budgetQuery->where('id', '!=', $excludeBudgetId);
        }
        $anggaran = (float) $budgetQuery->sum('amount');
        $transaksi = (float) Transaction::where('category_id', $category->id)->sum('amount');
        $tersedia = $saldo - $anggaran - $transaksi;

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'saldo' => $saldo,
            'anggaran' => $anggaran,
            'transaksi' => $transaksi,
            'tersedia' => $tersedia,
            'saldo_formatted' => 'Rp '.number_format($saldo, 0, ',', '.'),
            'anggaran_formatted' => 'Rp '.number_format($anggaran, 0, ',', '.'),
            'transaksi_formatted' => 'Rp '.number_format($transaksi, 0, ',', '.'),
            'tersedia_formatted' => 'Rp '.number_format($tersedia, 0, ',', '.'),
        ]);
    }

    private function ensureSaldoEnough(int $categoryId, float $amount, ?int $excludeBudgetId = null): void
    {
        $saldo = (float) Saldo::where('category_id', $categoryId)->sum('amount');

        $budgetQuery = Budget::where('category_id', $categoryId);
        if ($excludeBudgetId) {
            $budgetQuery->where('id', '!=', $excludeBudgetId);
        }
        $sudahDianggarkan = (float) $budgetQuery->sum('amount');
        $transaksiKategori = (float) Transaction::where('category_id', $categoryId)->sum('amount');
        $tersedia = $saldo - $sudahDianggarkan - $transaksiKategori;

        if ($amount > $tersedia + 0.01) {
            $kategori = Category::find($categoryId);
            $namaKategori = $kategori?->name ?? 'kategori ini';

            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Saldo "%s" tidak cukup. Tersedia: Rp %s (sudah dipotong anggaran lain dan transaksi pribadi), anggaran yang Anda masukkan: Rp %s.',
                    $namaKategori,
                    number_format($tersedia, 0, ',', '.'),
                    number_format($amount, 0, ',', '.')
                ),
            ]);
        }
    }

    private function parseRupiah(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' || $digits === '0' ? '0' : $digits;
    }
}
