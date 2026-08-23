<?php

namespace App\Http\Controllers\Entity;

use App\Enums\AuditAction;
use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\Debt;
use App\Models\FinanceEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EntityDebtController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.debts.index', [
            'entity' => $financeEntity,
            'debts' => $financeEntity->debts()->orderBy('title')->get(),
            'title' => 'Hutang',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.debts.create', [
            'entity' => $financeEntity,
            'title' => 'Tambah Hutang',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'principal_total' => ['required', 'string'],
            'remaining_balance' => ['nullable', 'string'],
            'monthly_installment' => ['nullable', 'string'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'finance_entity_id' => ['prohibited'],
        ]);

        $principal = $this->parseRupiah($validated['principal_total']);

        $financeEntity->debts()->create([
            'title' => $validated['title'],
            'principal_total' => $principal,
            'remaining_balance' => ($validated['remaining_balance'] ?? null)
                ? $this->parseRupiah($validated['remaining_balance'])
                : $principal,
            'monthly_installment' => ($validated['monthly_installment'] ?? null)
                ? $this->parseRupiah($validated['monthly_installment'])
                : 0,
            'due_day' => $validated['due_day'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('entity.debts.index', $financeEntity)->with('success', 'Hutang dicatat.');
    }

    public function show(FinanceEntity $financeEntity, Debt $debt): View
    {
        $this->owned($financeEntity, $debt);
        $debt->load(['payments' => fn ($q) => $q->with('financeAccount')->orderByDesc('paid_on')]);

        return view('entity.debts.show', [
            'entity' => $financeEntity,
            'debt' => $debt,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => $debt->title,
        ]);
    }

    public function edit(FinanceEntity $financeEntity, Debt $debt): View
    {
        $this->owned($financeEntity, $debt);

        return view('entity.debts.edit', [
            'entity' => $financeEntity,
            'debt' => $debt,
            'title' => 'Edit Hutang',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, Debt $debt): RedirectResponse
    {
        $this->owned($financeEntity, $debt);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'principal_total' => ['required', 'string'],
            'remaining_balance' => ['nullable', 'string'],
            'monthly_installment' => ['nullable', 'string'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'finance_entity_id' => ['prohibited'],
        ]);

        $debt->update([
            'title' => $validated['title'],
            'principal_total' => $this->parseRupiah($validated['principal_total']),
            'remaining_balance' => ($validated['remaining_balance'] ?? null)
                ? $this->parseRupiah($validated['remaining_balance'])
                : $debt->remaining_balance,
            'monthly_installment' => ($validated['monthly_installment'] ?? null)
                ? $this->parseRupiah($validated['monthly_installment'])
                : 0,
            'due_day' => $validated['due_day'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('entity.debts.index', $financeEntity)->with('success', 'Hutang diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, Debt $debt): RedirectResponse
    {
        $this->owned($financeEntity, $debt);
        $debt->delete();

        return redirect()->route('entity.debts.index', $financeEntity)->with('success', 'Hutang dihapus.');
    }

    public function storePayment(Request $request, FinanceEntity $financeEntity, Debt $debt): RedirectResponse
    {
        $this->owned($financeEntity, $debt);
        $validated = $request->validate([
            'amount' => ['required', 'string'],
            'paid_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            ...$this->financeAccountRules($financeEntity),
        ]);

        $amount = $this->parseRupiah($validated['amount']);
        DB::transaction(function () use ($validated, $financeEntity, $debt, $amount): void {
            $payment = $debt->payments()->create([
                'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
                'amount' => $amount,
                'paid_on' => $validated['paid_on'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $debt->update([
                'remaining_balance' => max(0, (float) $debt->remaining_balance - $amount),
            ]);
            $this->auditLogs()->record($payment, AuditAction::PAYMENT, $financeEntity);
        });

        return redirect()->route('entity.debts.show', [$financeEntity, $debt])->with('success', 'Pembayaran dicatat.');
    }

    private function owned(FinanceEntity $entity, Debt $debt): void
    {
        abort_unless((int) $debt->finance_entity_id === (int) $entity->id, 404);
    }
}
