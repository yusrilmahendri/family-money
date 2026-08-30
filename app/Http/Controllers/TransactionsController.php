<?php

namespace App\Http\Controllers;

use App\Exports\TransactionExport;
use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Saldo;
use App\Models\Transaction;
use App\Services\FinanceContextService;
use App\Support\FinanceContext;
use App\Support\FinanceOwnership;
use App\Support\Rupiah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class TransactionsController extends Controller
{
    use AssignsFinanceAccount;

    public function data()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }

        $transactions = Transaction::forContext(FinanceContext::PRIBADI)
            ->orderBy('transaction_date', 'desc');

        return DataTables::of($transactions)
            ->addColumn('name', function (Transaction $model) {
                return $model->category->name ?? '-';
            })
            // FORMAT RUPIAH
            ->editColumn('amount', function ($row) {
                return Rupiah::format($row->amount);
            })

            // FORMAT DESCRIPTION
            ->editColumn('description', function ($row) {
                return $row->description ?: '-';
            })

            // FORMAT KETERANGAN DETAIL
            ->editColumn('keterangan_detail', function ($row) {
                return $row->keterangan_detail ?: '-';
            })

            // FORMAT TANGGAL KE d M Y
            ->editColumn('transaction_date', function ($row) {
                return \Carbon\Carbon::parse($row->transaction_date)->format('d M Y');
            })

            ->addColumn('action', 'transactions.action')
            ->addIndexColumn()

            ->rawColumns(['action', 'name_items']) // 🔥 WAJIB AGAR RENDER HTML
            ->toJson();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $context = FinanceContext::PRIBADI;

        // Total saldo bersifat GLOBAL (shared semua konteks)
        $totalSaldo = Saldo::sum('amount');

        // Total transaksi konteks aktif (sesuai daftar yang ditampilkan)
        $totalAmount = Transaction::forContext($context)->sum('amount');
        $totalSemua = $totalAmount;

        // Sisa saldo global = saldo - seluruh transaksi (semua konteks)
        $sisaSaldo = $totalSaldo - Transaction::sum('amount');
        $dateTransaksi = Transaction::forContext($context)->latest()->first();

        return view('transactions.index', [
            'transaksi' => Transaction::forContext($context)->get(),
            'dateTransaksi' => $dateTransaksi,
            'title' => 'Transaksi '.FinanceContext::label($context),
            'totalAmount' => $totalAmount,
            'totalSemua' => $totalSemua,
            'totalSaldo' => $totalSaldo,
            'sisaSaldo' => $sisaSaldo,
            'contextLabel' => FinanceContext::label($context),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $context = FinanceContext::PRIBADI;

        return view('transactions.create', [
            'title' => 'Tambah Transaksi',
            'context' => $context,
            'categories' => Category::forContext($context)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Transaksi memotong saldo (kebutuhan pribadi: BPJS dll).
     */
    public function store(Request $request)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }

        $validated = $request->validate([
            'total' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'keterangan_detail' => ['nullable', 'string'],
            'nota' => ['nullable', 'file', 'image', 'max:4096'],
            ...$this->legacyAccountRules(FinanceContext::PRIBADI),
        ]);

        $total = Rupiah::toFloat($validated['total']);

        // Validasi: kalau ada category_id, pastikan saldo kategori cukup
        if (! empty($validated['category_id'])) {
            $this->ensureSaldoEnough((int) $validated['category_id'], $total);
        } else {
            $this->ensureSaldoGlobalEnough($total);
        }

        $notaFile = null;
        if ($request->hasFile('nota')) {
            $notaFile = $request->file('nota')->store('nota', 'public');
        }

        $entity = FinanceOwnership::defaultEntityForContext(FinanceContext::PRIBADI);

        Transaction::create([
            'category_id' => $validated['category_id'] ?? null,
            'context' => FinanceContext::PRIBADI,
            'finance_entity_id' => $entity?->id,
            'finance_account_id' => $entity ? $this->resolvedAccountId($validated, $entity) : null,
            'amount' => $total,
            'transaction_date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'keterangan_detail' => $validated['keterangan_detail'] ?? null,
            'detail_description' => $validated['keterangan_detail'] ?? null,
            'nota' => $notaFile,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil disimpan. Saldo telah dipotong.');
    }

    /**
     * Pastikan saldo kategori cukup untuk transaksi.
     */
    private function ensureSaldoEnough(int $categoryId, float $amount, ?int $excludeTransactionId = null): void
    {
        // Pemasukan Usaha sudah auto-sinkron ke saldos, jadi cukup Saldo::sum.
        $saldo = (float) Saldo::where('category_id', $categoryId)->sum('amount');
        $anggaran = (float) Budget::where('category_id', $categoryId)->sum('amount');

        $trxQuery = Transaction::where('category_id', $categoryId);
        if ($excludeTransactionId) {
            $trxQuery->where('id', '!=', $excludeTransactionId);
        }
        $transaksiLain = (float) $trxQuery->sum('amount');

        $tersedia = $saldo - $anggaran - $transaksiLain;

        if ($amount > $tersedia + 0.01) {
            $kategori = Category::find($categoryId);
            $namaKategori = $kategori?->name ?? 'kategori ini';

            throw ValidationException::withMessages([
                'total' => sprintf(
                    'Saldo "%s" tidak cukup. Tersedia: %s, transaksi yang dimasukkan: %s.',
                    $namaKategori,
                    Rupiah::format($tersedia),
                    Rupiah::format($amount)
                ),
                'amount' => sprintf(
                    'Saldo "%s" tidak cukup. Tersedia: %s.',
                    $namaKategori,
                    Rupiah::format($tersedia)
                ),
            ]);
        }
    }

    /**
     * Pastikan saldo bebas (global) cukup untuk transaksi tanpa kategori.
     */
    private function ensureSaldoGlobalEnough(float $amount, ?int $excludeTransactionId = null): void
    {
        $totalSaldo = (float) Saldo::sum('amount');
        $totalAnggaran = (float) Budget::sum('amount');

        $trxQuery = Transaction::query();
        if ($excludeTransactionId) {
            $trxQuery->where('id', '!=', $excludeTransactionId);
        }
        $totalTransaksi = (float) $trxQuery->sum('amount');

        $tersedia = $totalSaldo - $totalAnggaran - $totalTransaksi;

        if ($amount > $tersedia + 0.01) {
            throw ValidationException::withMessages([
                'total' => sprintf(
                    'Saldo bebas tidak cukup. Tersedia: %s, transaksi yang dimasukkan: %s.',
                    Rupiah::format($tersedia),
                    Rupiah::format($amount)
                ),
                'amount' => sprintf(
                    'Saldo bebas tidak cukup. Tersedia: %s.',
                    Rupiah::format($tersedia)
                ),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $transaction = Transaction::findOrFail($id);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'context' => $transaction->context ?? FinanceContext::PRIBADI,
            'categories' => Category::forContext($transaction->context ?? FinanceContext::PRIBADI)
                ->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $transaction = Transaction::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|string',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'category_id' => ['nullable', 'exists:categories,id'],
            'keterangan_detail' => 'nullable|string',
        ]);

        $amount = Rupiah::toFloat($validated['amount']);

        // Validasi saldo (tidak menghitung transaksi yang sedang di-edit)
        if (! empty($validated['category_id'])) {
            $this->ensureSaldoEnough((int) $validated['category_id'], $amount, $transaction->id);
        } else {
            $this->ensureSaldoGlobalEnough($amount, $transaction->id);
        }

        DB::beginTransaction();

        try {
            $transaction->update([
                'category_id' => $validated['category_id'] ?? null,
                'amount' => $amount,
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['date'],
                'keterangan_detail' => $validated['keterangan_detail'] ?? null,
                'detail_description' => $validated['keterangan_detail'] ?? null,
            ]);

            if ($request->hasFile('nota')) {
                if ($transaction->nota) {
                    Storage::disk('public')->delete($transaction->nota);
                }

                $notaPath = $request->file('nota')->store('nota', 'public');
                $transaction->update(['nota' => $notaPath]);
            }

            DB::commit();

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Transaction updated successfully!');

        } catch (\Exception $e) {

            DB::rollBack();

            return back()->withErrors([
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Export transactions to Excel
     */
    public function exportExcel()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $context = FinanceContext::PRIBADI;

        return Excel::download(new TransactionExport($context), 'data-transaksi-'.strtolower($context).'-'.date('Y-m-d').'.xlsx');
    }

    /**
     * Export transactions to PDF
     */
    public function exportPdf()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $context = FinanceContext::PRIBADI;

        $transactions = Transaction::with(['category', 'items'])
            ->forContext($context)
            ->orderBy('transaction_date', 'desc')
            ->get();
        $totalTransaksi = Transaction::forContext($context)->sum('amount');

        $pdf = Pdf::loadView('transactions.pdf', [
            'transactions' => $transactions,
            'totalTransaksi' => $totalTransaksi,
            'contextLabel' => FinanceContext::label($context),
        ]);

        return $pdf->download('data-transaksi-'.strtolower($context).'-'.date('Y-m-d').'.pdf');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $transaction = Transaction::findOrFail($id);

        // Hapus file nota jika ada
        if ($transaction->nota) {
            Storage::disk('public')->delete($transaction->nota);
        }

        // Hapus transaction items (cascade delete)
        $transaction->items()->delete();

        // Hapus transaction
        $transaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dihapus!');
    }
}
