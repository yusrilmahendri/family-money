<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Saldo;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Simpan file nota
        $notaFile = null;
        if ($request->hasFile('nota')) {
            $notaFile = $request->file('nota')->store('nota', 'public');
        }

        // Hapus format Rupiah dari total
        $total = preg_replace('/[Rp\s\.]/', '', $request->total);

        // Simpan transaksi utama
        $trx = Transaction::create([
            'amount' => $total, // sudah bersih
            'transaction_date'  => $request->date,
            'description' => $request->description,
            'keterangan_detail' => $request->keterangan_detail,
            'nota'  => $notaFile
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil disimpan!');
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

        // ==========================
        // VALIDASI
        // ==========================
        $validated = $request->validate([
            'amount' => 'required|string',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'keterangan_detail' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // FORMAT TOTAL
            $amount = preg_replace('/[^0-9]/', '', $validated['amount']);

            // UPDATE TRANSAKSI
            $transaction->update([
                'amount' => $amount,
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['date'],
                'keterangan_detail' => $validated['keterangan_detail'] ?? null,
            ]);

            // HANDLING NOTA
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
                'error' => $e->getMessage()
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
