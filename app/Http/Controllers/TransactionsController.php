<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Income;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Saldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Service\BudgetService;
use Illuminate\Support\Facades\Storage;
use App\Exports\TransactionExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class TransactionsController extends Controller
{
    public function data()
    {
        $transactions = Transaction::orderBy('transaction_date', 'desc');

        return DataTables::of($transactions)
            ->addColumn('name', function (Transaction $model) {
                return $model->category->name ?? '-';
            })
            // FORMAT RUPIAH
            ->editColumn('amount', function ($row) {
                return 'Rp ' . number_format($row->amount, 0, ',', '.');
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
        // Total saldo dari tabel saldos
        $totalSaldo = Saldo::sum('amount');

        // Total amount dari tabel transactions
        $totalAmount = Transaction::sum('amount');
        // Total keseluruhan amount + price
        $totalSemua = $totalAmount;

        // Sisa saldo (saldo - total transaksi)
        $sisaSaldo = $totalSaldo - $totalSemua;
        $dateTransaksi = Transaction::latest()->first();

        return view('transactions.index', [
            'transaksi' => Transaction::all(),
            'dateTransaksi' => $dateTransaksi,
            'title' => 'Transaction List',
            'totalAmount' => $totalAmount,
            'totalSemua' => $totalSemua,
            'totalSaldo' => $totalSaldo,
            'sisaSaldo' => $sisaSaldo,
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('transactions.create', [
            'title' => 'Tambah Transaksi',
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Transaksi memotong saldo (kebutuhan pribadi: BPJS dll).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'total' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'keterangan_detail' => ['nullable', 'string'],
            'nota' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $total = (float) preg_replace('/[^0-9]/', '', $validated['total']);

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

        Transaction::create([
            'category_id' => $validated['category_id'] ?? null,
            'amount' => $total,
            'transaction_date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'keterangan_detail' => $validated['keterangan_detail'] ?? null,
            'nota' => $notaFile,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil disimpan. Saldo telah dipotong.');
    }

    /**
     * Pastikan saldo kategori cukup untuk transaksi.
     */
    private function ensureSaldoEnough(int $categoryId, float $amount, ?int $excludeTransactionId = null): void
    {
        $saldo = (float) Saldo::where('category_id', $categoryId)->sum('amount')
            + (float) Income::where('category_id', $categoryId)->sum('amount');
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
                    'Saldo "%s" tidak cukup. Tersedia: Rp %s, transaksi yang dimasukkan: Rp %s.',
                    $namaKategori,
                    number_format($tersedia, 0, ',', '.'),
                    number_format($amount, 0, ',', '.')
                ),
                'amount' => sprintf(
                    'Saldo "%s" tidak cukup. Tersedia: Rp %s.',
                    $namaKategori,
                    number_format($tersedia, 0, ',', '.')
                ),
            ]);
        }
    }

    /**
     * Pastikan saldo bebas (global) cukup untuk transaksi tanpa kategori.
     */
    private function ensureSaldoGlobalEnough(float $amount, ?int $excludeTransactionId = null): void
    {
        $totalSaldo = (float) Saldo::sum('amount') + (float) Income::sum('amount');
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
                    'Saldo bebas tidak cukup. Tersedia: Rp %s, transaksi yang dimasukkan: Rp %s.',
                    number_format($tersedia, 0, ',', '.'),
                    number_format($amount, 0, ',', '.')
                ),
                'amount' => sprintf(
                    'Saldo bebas tidak cukup. Tersedia: Rp %s.',
                    number_format($tersedia, 0, ',', '.')
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
        return view('transactions.edit', [
            'transaction' => Transaction::findOrFail($id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|string',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'category_id' => ['nullable', 'exists:categories,id'],
            'keterangan_detail' => 'nullable|string',
        ]);

        $amount = (float) preg_replace('/[^0-9]/', '', $validated['amount']);

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
        return Excel::download(new TransactionExport, 'data-transaksi-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Export transactions to PDF
     */
    public function exportPdf()
    {
        $transactions = Transaction::with(['category', 'items'])->orderBy('transaction_date', 'desc')->get();
        $totalTransaksi = Transaction::sum('amount');

        $pdf = Pdf::loadView('transactions.pdf', [
            'transactions' => $transactions,
            'totalTransaksi' => $totalTransaksi,
        ]);

        return $pdf->download('data-transaksi-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
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
