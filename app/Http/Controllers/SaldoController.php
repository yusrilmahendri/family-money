<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Saldo;
use App\Models\Category;
use App\Models\Income;
use App\Models\Budget;
use App\Models\Transaction;
use App\Exports\SaldoExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class SaldoController extends Controller
{

    public function data()
    {
        $saldo = Saldo::orderBy('periode_saldo', 'desc');

        return DataTables::of($saldo)
            ->addColumn('category', function ($model) {
                $name = $model->category ? $model->category->name : '-';
                if (!empty($model->income_id)) {
                    $name .= ' <span class="label label-info" style="font-size:10px;">AUTO</span>';
                }
                return $name;
            })
            ->addColumn('amount', function ($model) {
                return 'Rp ' . number_format($model->amount, 0, ',', '.');
            })
            ->addColumn('description', function ($model) {
                return $model->description ?: '-';
            })
            ->addColumn('nota_image', function ($model) {
                if (!empty($model->nota_image)) {
                    $url = asset('storage/' . $model->nota_image);
                    return '<a href="' . $url . '" target="_blank"><img src="' . $url . '" alt="Nota" style="max-width:60px;max-height:60px;"></a>';
                } else {
                    return '-';
                }
            })
            ->addColumn('periode_saldo', function ($model) {
                return \Carbon\Carbon::parse($model->periode_saldo)->translatedFormat('d F Y');
            })
            ->addColumn('action', 'saldo.action')
            ->addIndexColumn()
            ->rawColumns(['action','nota_image','category'])
            ->toJson();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $updated_saldo = Saldo::latest()->first(); // ambil transaksi saldo terbaru

        $hasIncomeIdColumn = Schema::hasColumn('saldos', 'income_id');

        // Pemasukan Usaha SUDAH auto-sinkron ke tabel saldos (lewat IncomeController).
        // Jadi:
        //   - Saldo Manual  = saldo yang income_id-nya NULL
        //   - Pemasukan Usaha = saldo yang income_id-nya TIDAK NULL
        //   - Total Dana    = Saldo::sum() (semua)
        $totalSaldoManual = $hasIncomeIdColumn
            ? (float) Saldo::whereNull('income_id')->sum('amount')
            : (float) Saldo::sum('amount');
        $totalPemasukan   = $hasIncomeIdColumn
            ? (float) Saldo::whereNotNull('income_id')->sum('amount')
            : 0;
        $totalDana        = (float) Saldo::sum('amount');
        $totalDianggarkan = (float) Budget::sum('amount');
        $totalTransaksi   = (float) Transaction::sum('amount');
        $saldoBebas       = $totalDana - $totalDianggarkan - $totalTransaksi;

        return view('saldo.index', [
            'Transaksi'          => Saldo::all(),
            'total_saldo'        => $totalDana,
            'total_saldo_manual' => $totalSaldoManual,
            'total_pemasukan'    => $totalPemasukan,
            'total_dana'         => $totalDana,
            'total_dianggarkan'  => $totalDianggarkan,
            'total_transaksi'    => $totalTransaksi,
            'saldo_bebas'        => $saldoBebas,
            'updated_saldo'      => $updated_saldo,
            'title'              => 'Saldo List',
            'categories'         => Category::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('saldo.create', [
            'title' => 'Tambah Saldo',
            'categories' => \App\Models\Category::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'amount' => 'required',
            'description' => 'required|max:255',
            'periode_saldo' => 'required|date',
            'category_id' => 'required',
            'nota_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        Saldo::create([
            'amount' => $this->cleanRupiah($validatedData['amount']),
            'description' => $validatedData['description'],
            'periode_saldo' => $validatedData['periode_saldo'],
            'category_id' => $validatedData['category_id'],
            'nota_image' => $request->file('nota_image') ? $request->file('nota_image')->store('nota', 'public') : null,
        ]);
        return redirect()->route('saldos.index')->with('success', 'Saldo berhasil ditambahkan!');
    }

    private function cleanRupiah($rupiah)
    {
        // Hapus "Rp", spasi, dan titik
        $clean = str_replace(['Rp', ' ', '.'], '', $rupiah);

        // Ubah koma menjadi titik jika ada
        $clean = str_replace(',', '.', $clean);

        return (float) $clean;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return view('saldo.show', [
            'title' => 'Detail Saldo',
            'Saldo' => Saldo::findOrFail($id),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $saldo = Saldo::findOrFail($id);

        if (!empty($saldo->income_id)) {
            return redirect()->route('incomes.edit', $saldo->income_id)
                ->with('info', 'Saldo ini berasal dari Pemasukan Usaha. Silakan edit dari halaman Pemasukan.');
        }

        return view('saldo.edit', [
            'title' => 'Edit Saldo',
            'Saldo' => $saldo,
            'categories' => Category::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, string $id)
{
    $validatedData = $request->validate([
        'amount'        => 'required',
        'description'   => 'required|max:255',
        'periode_saldo' => 'required|date',
        'category_id'   => 'required',
        'nota_image'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $saldo = Saldo::findOrFail($id);

    // Default pakai file lama
    $notaImagePath = $saldo->nota_image;

    // Jika upload baru
    if ($request->hasFile('nota_image')) {

        // Hapus file lama jika ada
        if ($saldo->nota_image && Storage::disk('public')->exists($saldo->nota_image)) {
            Storage::disk('public')->delete($saldo->nota_image);
        }

        // Simpan file baru
        $notaImagePath = $request->file('nota_image')
            ->store('nota', 'public');
    }

    $saldo->update([
        'amount'        => $this->cleanRupiah($validatedData['amount']),
        'description'   => $validatedData['description'],
        'periode_saldo' => $validatedData['periode_saldo'],
        'category_id'   => $validatedData['category_id'],
        'nota_image'    => $notaImagePath,
    ]);

    return redirect()
        ->route('saldos.index')
        ->with('info', 'Saldo berhasil diperbarui!');
}

    /**
     * Get total saldo by category
     */
    public function getByCategoryId($categoryId)
    {
        $total = Saldo::where('category_id', $categoryId)->sum('amount');

        return response()->json([
            'total' => $total,
            'category_id' => $categoryId
        ]);
    }

    /**
     * Get filtered saldo by month and/or year
     */
    public function getFilteredSaldo(Request $request)
    {
        $query = Saldo::query();

        if ($request->has('month') && $request->month != '') {
            $query->whereMonth('periode_saldo', $request->month);
        }

        if ($request->has('year') && $request->year != '') {
            $query->whereYear('periode_saldo', $request->year);
        }

        $total = $query->sum('amount');

        return response()->json([
            'total' => $total,
            'month' => $request->month,
            'year' => $request->year
        ]);
    }

    /**
     * Export saldo to Excel
     */
    public function exportExcel()
    {
        return Excel::download(new SaldoExport, 'data-saldo-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Export saldo to PDF
     */
    public function exportPdf()
    {
        $saldos = Saldo::with('category')->orderBy('periode_saldo', 'desc')->get();
        $totalSaldo = Saldo::sum('amount');

        $pdf = Pdf::loadView('saldo.pdf', [
            'saldos' => $saldos,
            'totalSaldo' => $totalSaldo,
        ]);

        return $pdf->download('data-saldo-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $saldo = Saldo::findOrFail($id);

        if (!empty($saldo->income_id)) {
            return redirect()->route('saldos.index')
                ->with('danger', 'Saldo ini berasal dari Pemasukan Usaha. Hapus dari halaman Pemasukan Usaha agar konsisten.');
        }

        $saldo->delete();
        return redirect()->route('saldos.index')
            ->with('danger', 'Data Saldo Berhasil dihapuskan');
    }
}
