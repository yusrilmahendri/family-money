<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\FinanceEntity;
use App\Models\Transaction;
use App\Support\FinanceOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityTransactionController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        $transactions = $financeEntity->transactions()
            ->with('financeAccount')
            ->latest('transaction_date')
            ->paginate(20);

        return view('entity.transactions.index', [
            'entity' => $financeEntity,
            'transactions' => $transactions,
            'title' => 'Pengeluaran',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.transactions.create', [
            'entity' => $financeEntity,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => 'Tambah Pengeluaran',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $this->validated($request, $financeEntity);

        $transaction = $financeEntity->transactions()->create([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'category_id' => $validated['category_id'] ?? null,
            'context' => FinanceOwnership::contextFor($financeEntity),
            'amount' => $this->parseRupiah($validated['amount']),
            'transaction_date' => $validated['transaction_date'],
            'description' => $validated['description'] ?? null,
            'keterangan_detail' => $validated['keterangan_detail'] ?? null,
        ]);
        $this->auditLogs()->recordCreated($transaction, $financeEntity);

        return redirect()
            ->route('entity.transactions.index', $financeEntity)
            ->with('success', 'Pengeluaran dicatat.');
    }

    public function edit(FinanceEntity $financeEntity, Transaction $transaction): View
    {
        $this->owned($financeEntity, $transaction);

        return view('entity.transactions.edit', [
            'entity' => $financeEntity,
            'transaction' => $transaction,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'accounts' => $this->selectableAccounts($financeEntity, $transaction->finance_account_id),
            'title' => 'Edit Pengeluaran',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, Transaction $transaction): RedirectResponse
    {
        $this->owned($financeEntity, $transaction);
        $validated = $this->validated($request, $financeEntity, $transaction->finance_account_id);
        $old = $this->auditLogs()->snapshot($transaction);

        $transaction->update([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'category_id' => $validated['category_id'] ?? null,
            'context' => FinanceOwnership::contextFor($financeEntity),
            'amount' => $this->parseRupiah($validated['amount']),
            'transaction_date' => $validated['transaction_date'],
            'description' => $validated['description'] ?? null,
            'keterangan_detail' => $validated['keterangan_detail'] ?? null,
        ]);
        $this->auditLogs()->recordUpdated($transaction->fresh(), $old, $financeEntity);

        return redirect()
            ->route('entity.transactions.index', $financeEntity)
            ->with('success', 'Pengeluaran diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, Transaction $transaction): RedirectResponse
    {
        $this->owned($financeEntity, $transaction);
        $old = $this->auditLogs()->snapshot($transaction);
        $transaction->delete();
        $this->auditLogs()->recordDeleted($transaction, $old, $financeEntity);

        return redirect()
            ->route('entity.transactions.index', $financeEntity)
            ->with('success', 'Pengeluaran dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, FinanceEntity $entity, ?int $currentAccountId = null): array
    {
        return $request->validate([
            'amount' => $this->positiveRupiahRules(),
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'keterangan_detail' => ['nullable', 'string'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('finance_entity_id', $entity->id)],
            'finance_entity_id' => ['prohibited'],
            ...$this->financeAccountRules($entity, $currentAccountId),
        ]);
    }

    private function owned(FinanceEntity $entity, Transaction $transaction): void
    {
        abort_unless((int) $transaction->finance_entity_id === (int) $entity->id, 404);
    }
}
