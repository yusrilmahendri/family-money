<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class OperationalExpenseController extends Controller
{
    /**
     * DataTables JSON: semua biaya operasional (alias BudgetActivity).
     */
    public function data()
    {
        $q = BudgetActivity::query()
            ->with(['budget.category'])
            ->orderBy('activity_date', 'desc');

        return DataTables::of($q)
            ->addColumn('category', fn (BudgetActivity $a) => $a->budget?->category?->name ?? '—')
            ->addColumn('budget', function (BudgetActivity $a) {
                $b = $a->budget;
                if (!$b) {
                    return '<span class="text-muted">— Anggaran terhapus —</span>';
                }
                $periode = optional($b->periode)->translatedFormat('M Y') ?? '-';
                return e($b->description ?: 'Anggaran').' <small class="text-muted">('.$periode.')</small>';
            })
            ->editColumn('name', fn (BudgetActivity $a) => e($a->name).($a->description ? '<br><small class="text-muted">'.e($a->description).'</small>' : ''))
            ->editColumn('amount', fn (BudgetActivity $a) => 'Rp '.number_format((float) $a->amount, 0, ',', '.'))
            ->editColumn('activity_date', fn (BudgetActivity $a) => optional($a->activity_date)->translatedFormat('d M Y') ?? '-')
            ->addColumn('action', 'operational.action')
            ->rawColumns(['action', 'budget', 'name'])
            ->toJson();
    }

    public function index()
    {
        $now = now();

        $totalBiayaBulanIni = (float) BudgetActivity::query()
            ->whereYear('activity_date', $now->year)
            ->whereMonth('activity_date', $now->month)
            ->sum('amount');

        $totalBiayaTahunIni = (float) BudgetActivity::query()
            ->whereYear('activity_date', $now->year)
            ->sum('amount');

        $totalBiayaSemua = (float) BudgetActivity::sum('amount');

        return view('operational.index', [
            'title' => 'Biaya Operasional',
            'categories' => Category::orderBy('name')->get(),
            'budgets' => Budget::with('category')->orderBy('periode', 'desc')->get(),
            'total_bulan_ini' => $totalBiayaBulanIni,
            'total_tahun_ini' => $totalBiayaTahunIni,
            'total_semua' => $totalBiayaSemua,
        ]);
    }

    public function create()
    {
        return view('operational.create', [
            'title' => 'Tambah Biaya Operasional',
            'categories' => Category::orderBy('name')->get(),
            'budgets' => Budget::with('category')->orderBy('periode', 'desc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'budget_id' => ['required', 'exists:budgets,id'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'budget_id.required' => 'Pilih anggaran yang akan dikurangi.',
            'name.required' => 'Nama biaya wajib diisi (mis. Upah, Gaji, Pupuk).',
            'amount.required' => 'Jumlah biaya wajib diisi.',
            'activity_date.required' => 'Tanggal biaya wajib diisi.',
        ]);

        $budget = Budget::findOrFail($validated['budget_id']);
        $amount = (float) $this->parseRupiah($validated['amount']);

        $sudahTerpakai = (float) BudgetActivity::where('budget_id', $budget->id)->sum('amount');
        $sisa = (float) $budget->amount - $sudahTerpakai;

        if ($amount > $sisa + 0.01) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Sisa anggaran "%s" hanya Rp %s, sedangkan biaya yang dimasukkan Rp %s.',
                    $budget->description ?: ($budget->category?->name ?? 'Anggaran'),
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

        return redirect()->route('operational.index')
            ->with('success', 'Biaya operasional dicatat. Sisa anggaran berkurang.');
    }

    public function edit(BudgetActivity $operational)
    {
        return view('operational.edit', [
            'title' => 'Ubah Biaya Operasional',
            'activity' => $operational,
            'budgets' => Budget::with('category')->orderBy('periode', 'desc')->get(),
        ]);
    }

    public function update(Request $request, BudgetActivity $operational)
    {
        $validated = $request->validate([
            'budget_id' => ['required', 'exists:budgets,id'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $budget = Budget::findOrFail($validated['budget_id']);
        $amount = (float) $this->parseRupiah($validated['amount']);

        $aktivitasLain = (float) BudgetActivity::where('budget_id', $budget->id)
            ->where('id', '!=', $operational->id)
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

        $operational->update([
            'budget_id' => $budget->id,
            'name' => $validated['name'],
            'amount' => $amount,
            'activity_date' => $validated['activity_date'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('operational.index')->with('info', 'Biaya operasional diperbarui.');
    }

    public function destroy(BudgetActivity $operational)
    {
        $operational->delete();

        return redirect()->route('operational.index')
            ->with('danger', 'Biaya operasional dihapus. Sisa anggaran dikembalikan.');
    }

    /**
     * AJAX: info sisa anggaran (untuk validasi di form).
     */
    public function budgetInfo(Budget $budget)
    {
        $budget->load('category');
        $terpakai = (float) BudgetActivity::where('budget_id', $budget->id)->sum('amount');
        $sisa = (float) $budget->amount - $terpakai;

        return response()->json([
            'budget' => [
                'id' => $budget->id,
                'amount' => (float) $budget->amount,
                'description' => $budget->description,
                'category' => $budget->category?->name,
                'periode' => optional($budget->periode)->format('Y-m-d'),
            ],
            'terpakai' => $terpakai,
            'sisa' => $sisa,
            'amount_formatted' => 'Rp '.number_format((float) $budget->amount, 0, ',', '.'),
            'terpakai_formatted' => 'Rp '.number_format($terpakai, 0, ',', '.'),
            'sisa_formatted' => 'Rp '.number_format($sisa, 0, ',', '.'),
        ]);
    }

    private function parseRupiah(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' || $digits === '0' ? '0' : $digits;
    }
}
