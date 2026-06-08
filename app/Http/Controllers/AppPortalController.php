<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Transaction;
use App\Support\FinanceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AppPortalController extends Controller
{
    /**
     * Halaman portal pilih "aplikasi" (konteks keuangan).
     */
    public function index()
    {
        $hasTrxContext = Schema::hasColumn('transactions', 'context');

        $summary = [];
        foreach (FinanceContext::all() as $key => $label) {
            $summary[$key] = [
                'label' => $label,
                'transaksi' => $hasTrxContext
                    ? (int) Transaction::forContext($key)->count()
                    : 0,
            ];
        }

        return view('apps.index', [
            'title' => 'Pilih Aplikasi',
            'contexts' => FinanceContext::all(),
            'current' => FinanceContext::current(),
            'summary' => $summary,
        ]);
    }

    /**
     * Set konteks aktif ke session lalu redirect ke dashboard.
     */
    public function select(Request $request)
    {
        $validated = $request->validate([
            'context' => FinanceContext::validationRule(),
        ], [
            'context.required' => 'Pilih salah satu aplikasi terlebih dahulu.',
            'context.in' => 'Aplikasi yang dipilih tidak valid.',
        ]);

        FinanceContext::set($validated['context']);

        // Jika request dari switcher (ingin tetap di halaman sekarang), hormati redirect_to
        $redirectTo = $request->input('redirect_to');
        if ($redirectTo && str_starts_with($redirectTo, '/')) {
            return redirect($redirectTo)
                ->with('success', 'Beralih ke '.FinanceContext::label($validated['context']).'.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Aktif: '.FinanceContext::label($validated['context']).'.');
    }
}
