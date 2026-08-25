<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Models\Category;
use App\Models\Income;
use App\Models\Saldo;
use App\Services\FinanceContextService;
use App\Support\FinanceContext;
use App\Support\FinanceOwnership;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

/**
 * Legacy /apps Income. Still syncs a saldos row for the old saldo list.
 * Private entity Income must not use this controller.
 */
class IncomeController extends Controller
{
    use AssignsFinanceAccount;

    public function data()
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        $q = Income::query()->with('category')
            ->forContext(FinanceContext::USAHA_KEBUN)
            ->orderBy('income_date', 'desc');

        return DataTables::of($q)
            ->addColumn('category', fn (Income $i) => $i->category?->name ?? '—')
            ->editColumn('source', fn (Income $i) => $i->source ?: '-')
            ->editColumn('description', fn (Income $i) => $i->description ?: '-')
            ->editColumn('amount', fn (Income $i) => Rupiah::format($i->amount))
            ->editColumn('income_date', fn (Income $i) => $i->income_date?->format('d M Y') ?? '-')
            ->addColumn('action', 'incomes.action')
            ->rawColumns(['action'])
            ->toJson();
    }

    public function index()
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }

        $totalIncome = (float) Income::forContext(FinanceContext::USAHA_KEBUN)->sum('amount');
        $thisMonth = (float) Income::query()
            ->forContext(FinanceContext::USAHA_KEBUN)
            ->whereYear('income_date', now()->year)
            ->whereMonth('income_date', now()->month)
            ->sum('amount');

        $perKategori = Category::forContext(FinanceContext::USAHA_KEBUN)
            ->orderBy('name')
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
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }

        return view('incomes.create', [
            'title' => 'Tambah Pemasukan',
            'categories' => Category::forContext(FinanceContext::USAHA_KEBUN)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'income_date' => ['required', 'date'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            ...$this->legacyAccountRules(FinanceContext::USAHA_KEBUN),
        ], [
            'source.required' => 'Sumber pemasukan wajib diisi.',
            'amount.required' => 'Jumlah pemasukan wajib diisi.',
            'income_date.required' => 'Tanggal pemasukan wajib diisi.',
            'category_id.required' => 'Jenis usaha wajib dipilih (untuk auto-sync saldo).',
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        DB::transaction(function () use ($validated, $amount) {
            $entity = FinanceOwnership::defaultEntityForContext(FinanceContext::USAHA_KEBUN);
            $income = Income::create([
                'category_id' => $validated['category_id'],
                'context' => FinanceContext::USAHA_KEBUN,
                'finance_entity_id' => $entity?->id,
                'finance_account_id' => $entity ? $this->resolvedAccountId($validated, $entity) : null,
                'source' => $validated['source'],
                'amount' => $amount,
                'income_date' => $validated['income_date'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncLegacySaldoFromIncome($income);
        });

        return redirect()->route('incomes.index')->with('success', 'Pemasukan dicatat & saldo otomatis bertambah.');
    }

    public function edit(Income $income)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }

        return view('incomes.edit', [
            'title' => 'Ubah Pemasukan',
            'income' => $income,
            'categories' => Category::forContext(FinanceContext::USAHA_KEBUN)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Income $income)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
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

            $this->syncLegacySaldoFromIncome($income);
        });

        return redirect()->route('incomes.index')->with('info', 'Pemasukan diperbarui & saldo otomatis disesuaikan.');
    }

    public function destroy(Income $income)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        DB::transaction(function () use ($income) {
            if (Schema::hasColumn('saldos', 'income_id')) {
                Saldo::where('income_id', $income->id)->delete();
            }
            $income->delete();
        });

        return redirect()->route('incomes.index')->with('danger', 'Pemasukan dihapus & saldo otomatis ikut hilang.');
    }

    /**
     * Legacy /apps compatibility only. Do not call from entity Income.
     *
     * SaldoGlobalService already counts Income::sum() and ignores saldos
     * that have income_id, so this row is for the legacy saldo list UI.
     */
    private function syncLegacySaldoFromIncome(Income $income): void
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
        return Rupiah::parse($raw);
    }
}
