<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index()
    {
        $debts = Debt::query()->orderBy('title')->get();

        return view('debts.index', [
            'title' => 'Utang & cicilan',
            'debts' => $debts,
        ]);
    }

    public function create()
    {
        return view('debts.create', [
            'title' => 'Catat utang / cicilan',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'principal_total' => ['required', 'string'],
            'remaining_balance' => ['nullable', 'string'],
            'monthly_installment' => ['nullable', 'string'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $principal = $this->parseRupiah($validated['principal_total']);
        $remaining = $validated['remaining_balance']
            ? $this->parseRupiah($validated['remaining_balance'])
            : $principal;
        $installment = $validated['monthly_installment']
            ? $this->parseRupiah($validated['monthly_installment'])
            : '0';

        Debt::create([
            'title' => $validated['title'],
            'principal_total' => $principal,
            'remaining_balance' => $remaining,
            'monthly_installment' => $installment,
            'due_day' => $validated['due_day'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('debts.index')->with('success', 'Utang / cicilan tersimpan.');
    }

    public function show(Debt $debt)
    {
        $debt->load(['payments' => fn ($q) => $q->orderBy('paid_on', 'desc')]);

        return view('debts.show', [
            'title' => $debt->title,
            'debt' => $debt,
        ]);
    }

    public function edit(Debt $debt)
    {
        return view('debts.edit', [
            'title' => 'Ubah utang',
            'debt' => $debt,
        ]);
    }

    public function update(Request $request, Debt $debt)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'principal_total' => ['required', 'string'],
            'remaining_balance' => ['required', 'string'],
            'monthly_installment' => ['nullable', 'string'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $debt->update([
            'title' => $validated['title'],
            'principal_total' => $this->parseRupiah($validated['principal_total']),
            'remaining_balance' => $this->parseRupiah($validated['remaining_balance']),
            'monthly_installment' => $validated['monthly_installment']
                ? $this->parseRupiah($validated['monthly_installment'])
                : '0',
            'due_day' => $validated['due_day'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('debts.index')->with('success', 'Data utang diperbarui.');
    }

    public function destroy(Debt $debt)
    {
        $debt->delete();

        return redirect()->route('debts.index')->with('success', 'Utang dihapus.');
    }

    public function storePayment(Request $request, Debt $debt)
    {
        $validated = $request->validate([
            'amount' => ['required', 'string'],
            'paid_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $this->parseRupiah($validated['amount']);

        DebtPayment::create([
            'debt_id' => $debt->id,
            'amount' => $amount,
            'paid_on' => $validated['paid_on'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $newRemaining = max(0, (float) $debt->remaining_balance - $amount);
        $debt->update(['remaining_balance' => $newRemaining]);

        return redirect()->route('debts.show', $debt)->with('success', 'Pembayaran cicilan dicatat.');
    }

    private function parseRupiah(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' || $digits === '0' ? '0' : $digits;
    }
}
