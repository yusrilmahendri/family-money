<?php

namespace App\Http\Controllers\Entity;

use App\Enums\AuditAction;
use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'principal_total' => $this->positiveRupiahRules(),
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

        $payments = $debt->payments()
            ->with('financeAccount')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();

        $principalTotal = (float) $debt->principal_total;
        $remainingAmount = $debt->remainingAmount();
        $totalPaid = $debt->totalPaid();
        $percentage = $debt->paymentProgressPercentage();
        $isPaidOff = $debt->isPaidOff();

        return view('entity.debts.show', [
            'entity' => $financeEntity,
            'debt' => $debt,
            'accounts' => $isPaidOff ? collect() : $financeEntity->activeAccounts()->get(),
            'title' => $debt->title,
            'principalTotal' => $principalTotal,
            'remainingAmount' => $remainingAmount,
            'totalPaid' => $totalPaid,
            'percentage' => $percentage,
            'progressVisual' => min($percentage, 100.0),
            'isPaidOff' => $isPaidOff,
            'paymentCount' => $payments->count(),
            'payments' => $payments->map(fn ($payment) => [
                'date_label' => $payment->paid_on?->copy()->locale('id')->translatedFormat('d M Y') ?: '—',
                'account_name' => $payment->financeAccount?->name ?: 'Rekening tidak tersedia',
                'amount' => (float) $payment->amount,
            ]),
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
            'principal_total' => $this->positiveRupiahRules(),
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
            'amount' => $this->positiveRupiahRules(),
            'paid_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            ...$this->financeAccountRules($financeEntity),
        ]);

        $amount = $this->parseRupiah($validated['amount']);
        DB::transaction(function () use ($validated, $financeEntity, $debt, $amount): void {
            $locked = Debt::query()
                ->whereKey($debt->id)
                ->where('finance_entity_id', $financeEntity->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = $locked->remainingAmount();

            if ($remaining <= 0.0) {
                throw ValidationException::withMessages([
                    'amount' => 'Hutang ini sudah lunas.',
                ]);
            }

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran tidak boleh melebihi sisa hutang. Sisa hutang hanya '.Rupiah::format($remaining).'.',
                ]);
            }

            $payment = $locked->payments()->create([
                'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
                'amount' => $amount,
                'paid_on' => $validated['paid_on'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $locked->update([
                'remaining_balance' => max(0, $remaining - $amount),
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
