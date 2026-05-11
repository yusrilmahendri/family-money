<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BudgetController extends Controller
{
    public function data()
    {
        $q = Budget::query()->orderBy('periode', 'desc');

        return DataTables::of($q)
            ->editColumn('amount', fn (Budget $b) => 'Rp '.number_format((float) $b->amount, 0, ',', '.'))
            ->editColumn('description', fn (Budget $b) => $b->description ?: '-')
            ->addColumn('category', fn (Budget $b) => $b->category?->name ?? 'Semua / umum')
            ->editColumn('periode', fn (Budget $b) => $b->periode?->format('d M Y') ?? '-')
            ->addColumn('action', 'budgets.action')
            ->rawColumns(['action'])
            ->toJson();
    }

    public function index()
    {
        $now = Carbon::now();
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

        return view('budgets.index', [
            'title' => 'Anggaran',
            'budget_cap' => $budgetCap,
            'spent_this_month' => $spent,
            'remaining_budget' => $remaining,
            'updated_budget' => $updatedBudget,
            'over_budget' => $budgetCap > 0 && $spent > $budgetCap,
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
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $amount = $this->parseRupiah($validated['amount']);

        Budget::create([
            'category_id' => $validated['category_id'] ?? null,
            'amount' => $amount,
            'amount_saldo' => 0,
            'periode' => $validated['periode'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('budgets.index')->with('success', 'Anggaran berhasil disimpan.');
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
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $budget->update([
            'category_id' => $validated['category_id'] ?? null,
            'amount' => $this->parseRupiah($validated['amount']),
            'periode' => $validated['periode'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('budgets.index')->with('success', 'Anggaran diperbarui.');
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();

        return redirect()->route('budgets.index')->with('success', 'Anggaran dihapus.');
    }

    private function parseRupiah(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' || $digits === '0' ? '0' : $digits;
    }
}
