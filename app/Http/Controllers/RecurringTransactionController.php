<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Service\RecurringTransactionRunner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

class RecurringTransactionController extends Controller
{
    public function data()
    {
        $q = RecurringTransaction::query()->with('category')->orderBy('next_due', 'asc');

        return DataTables::of($q)
            ->editColumn('name', fn (RecurringTransaction $r) => $r->name ?: '-')
            ->editColumn('amount', fn (RecurringTransaction $r) => 'Rp '.number_format((float) $r->amount, 0, ',', '.'))
            ->addColumn('category', fn (RecurringTransaction $r) => $r->category?->name ?? '— Umum —')
            ->addColumn('frequency_label', fn (RecurringTransaction $r) => $this->frequencyLabel($r))
            ->editColumn('next_due', function (RecurringTransaction $r) {
                if (! $r->next_due) {
                    return '-';
                }
                $color = $r->next_due->isPast() && $r->active ? 'text-danger' : ($r->next_due->isToday() ? 'text-warning' : 'text-success');

                return '<span class="'.$color.'">'.$r->next_due->translatedFormat('d M Y').'</span>';
            })
            ->addColumn('status', function (RecurringTransaction $r) {
                return $r->active
                    ? '<span class="label label-success">Aktif</span>'
                    : '<span class="label label-default">Nonaktif</span>';
            })
            ->addColumn('action', 'recurring.action')
            ->rawColumns(['action', 'next_due', 'status'])
            ->toJson();
    }

    public function index(RecurringTransactionRunner $runner)
    {
        if (! Schema::hasTable('recurring_transactions')) {
            return redirect()->route('dashboard')->with('danger',
                'Tabel transaksi berulang belum ada. Jalankan: php artisan migrate');
        }

        // Auto-post yang sudah jatuh tempo saat user buka halaman
        $created = $runner->runDue();

        if ($created > 0) {
            session()->flash('success', "{$created} transaksi otomatis baru saja diposting dari aturan berulang.");
        }

        $upcoming = RecurringTransaction::query()
            ->where('active', true)
            ->orderBy('next_due')
            ->limit(5)
            ->get();

        return view('recurring.index', [
            'title' => 'Transaksi Berulang',
            'total_aktif' => RecurringTransaction::where('active', true)->count(),
            'total_due' => RecurringTransaction::where('active', true)->whereDate('next_due', '<=', now())->count(),
            'upcoming' => $upcoming,
        ]);
    }

    public function create()
    {
        return view('recurring.create', [
            'title' => 'Tambah Aturan Berulang',
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAndPrepare($request);

        $rt = RecurringTransaction::create($data);

        return redirect()->route('recurring-transactions.index')->with('success', 'Aturan berulang "'.$rt->name.'" tersimpan.');
    }

    public function edit(RecurringTransaction $recurring)
    {
        return view('recurring.edit', [
            'title' => 'Ubah Aturan Berulang',
            'recurring' => $recurring,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, RecurringTransaction $recurring)
    {
        $data = $this->validateAndPrepare($request);

        $recurring->update($data);

        return redirect()->route('recurring-transactions.index')->with('info', 'Aturan diperbarui.');
    }

    public function destroy(RecurringTransaction $recurring)
    {
        $recurring->delete();

        return redirect()->route('recurring-transactions.index')->with('danger', 'Aturan dihapus.');
    }

    /**
     * Posting manual sekarang juga.
     */
    public function postNow(RecurringTransaction $recurring)
    {
        Transaction::create([
            'category_id' => $recurring->category_id,
            'amount' => $recurring->amount,
            'transaction_date' => Carbon::today(),
            'description' => '[Manual posting] '.$recurring->name,
            'keterangan_detail' => $recurring->description,
        ]);

        $recurring->last_posted_at = Carbon::today();
        // Geser next_due ke setelah hari ini
        $recurring->next_due = $recurring->calculateNextDue(Carbon::today()->addDay());
        $recurring->save();

        return redirect()->route('recurring-transactions.index')->with('success', 'Transaksi "'.$recurring->name.'" berhasil diposting.');
    }

    private function validateAndPrepare(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['nullable'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $amount = (float) preg_replace('/\D/', '', $validated['amount']);
        $active = (bool) ($validated['active'] ?? false);

        // Build temporary model untuk hitung next_due
        $tmp = new RecurringTransaction([
            'frequency' => $validated['frequency'],
            'day_of_month' => $validated['day_of_month'] ?? null,
            'day_of_week' => $validated['day_of_week'] ?? null,
            'start_date' => $startDate,
        ]);
        $nextDue = $tmp->calculateNextDue($startDate);

        return [
            'name' => $validated['name'],
            'amount' => $amount,
            'category_id' => $validated['category_id'] ?? null,
            'frequency' => $validated['frequency'],
            'day_of_month' => $validated['day_of_month'] ?? null,
            'day_of_week' => $validated['day_of_week'] ?? null,
            'start_date' => $startDate,
            'end_date' => $validated['end_date'] ?? null,
            'next_due' => $nextDue,
            'active' => $active,
            'description' => $validated['description'] ?? null,
        ];
    }

    private function frequencyLabel(RecurringTransaction $r): string
    {
        return match ($r->frequency) {
            'daily' => 'Setiap hari',
            'weekly' => 'Setiap '.['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$r->day_of_week ?? 0],
            'monthly' => 'Setiap bulan tgl '.($r->day_of_month ?? '?'),
            'yearly' => 'Setahun sekali',
            default => $r->frequency,
        };
    }
}
