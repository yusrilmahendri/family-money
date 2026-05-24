<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Income;
use App\Models\Saldo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

class IncomeController extends Controller
{
    public function data()
    {
        $q = Income::query()->with('category')->orderBy('income_date', 'desc');

        return DataTables::of($q)
            ->addColumn('category', fn (Income $i) => $i->category?->name ?? '—')
            ->editColumn('source', fn (Income $i) => $i->source ?: '-')
            ->editColumn('description', fn (Income $i) => $i->description ?: '-')
            ->editColumn('amount', fn (Income $i) => 'Rp '.number_format((float) $i->amount, 0, ',', '.'))
            ->editColumn('income_date', fn (Income $i) => $i->income_date?->format('d M Y') ?? '-')
            ->addColumn('action', 'incomes.action')
            ->rawColumns(['action'])
            ->toJson();
    }

    public function index()
    {
        $totalIncome = (float) Income::sum('amount');
        $thisMonth = (float) Income::query()
            ->whereYear('income_date', now()->year)
            ->whereMonth('income_date', now()->month)
            ->sum('amount');

        $perKategori = Category::orderBy('name')
            ->get()
            ->map(function (Category $cat) {
                return [
                    'name' => $cat->name,
                    'total' => (float) Income::where('category_id', $cat->id)->sum('amount'),
                    'this_month' => (float) Income::where('category_id', $cat->id)
                        ->whereYear('income_date', now()->year)
                        ->whereMonth('income_date', now()->month)
                        ->sum('amount'),
                ];
            })
            ->filter(fn ($r) => $r['total'] > 0)
            ->values();

        return view('incomes.index', [
            'title' => 'Pemasukan Usaha',
            'total_income' => $totalIncome,
            'this_month' => $thisMonth,
            'per_kategori' => $perKategori,
        ]);
    }

    public function create()
    {
        return view('incomes.create', [
            'title' => 'Tambah Pemasukan',
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'income_date' => ['required', 'date'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'source.required' => 'Sumber pemasukan wajib diisi.',
            'amount.required' => 'Jumlah pemasukan wajib diisi.',
            'income_date.required' => 'Tanggal pemasukan wajib diisi.',
            'category_id.required' => 'Jenis usaha wajib dipilih (untuk auto-sync saldo).',
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        DB::transaction(function () use ($validated, $amount) {
            $income = Income::create([
                'category_id' => $validated['category_id'],
                'source' => $validated['source'],
                'amount' => $amount,
                'income_date' => $validated['income_date'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncSaldoFromIncome($income);
        });

        return redirect()->route('incomes.index')->with('success', 'Pemasukan dicatat & saldo otomatis bertambah.');
    }

    public function edit(Income $income)
    {
        return view('incomes.edit', [
            'title' => 'Ubah Pemasukan',
            'income' => $income,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Income $income)
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'income_date' => ['required', 'date'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'category_id.required' => 'Jenis usaha wajib dipilih (untuk auto-sync saldo).',
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        DB::transaction(function () use ($income, $validated, $amount) {
            $income->update([
                'category_id' => $validated['category_id'],
                'source' => $validated['source'],
                'amount' => $amount,
                'income_date' => $validated['income_date'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncSaldoFromIncome($income);
        });

        return redirect()->route('incomes.index')->with('info', 'Pemasukan diperbarui & saldo otomatis disesuaikan.');
    }

    public function destroy(Income $income)
    {
        DB::transaction(function () use ($income) {
            if (Schema::hasColumn('saldos', 'income_id')) {
                Saldo::where('income_id', $income->id)->delete();
            }
            $income->delete();
        });

        return redirect()->route('incomes.index')->with('danger', 'Pemasukan dihapus & saldo otomatis ikut hilang.');
    }

    /**
     * Pastikan ada satu record Saldo yang mencerminkan Pemasukan ini.
     * - Kalau belum ada → buat baru.
     * - Kalau sudah ada → update jumlah/kategori/periode.
     */
    private function syncSaldoFromIncome(Income $income): void
    {
        if (! Schema::hasColumn('saldos', 'income_id')) {
            return;
        }

        $saldo = Saldo::firstOrNew(['income_id' => $income->id]);
        $saldo->fill([
            'category_id' => $income->category_id,
            'amount' => (float) $income->amount,
            'description' => '[Pemasukan] '.$income->source.($income->description ? ' — '.$income->description : ''),
            'periode_saldo' => $income->income_date,
        ]);
        $saldo->save();
    }

    private function parseRupiah(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' || $digits === '0' ? '0' : $digits;
    }
}
