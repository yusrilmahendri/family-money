<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Support\FinanceOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Entity Income is inflow only. It does not create or sync saldos.
 * Account balance comes from FinanceAccountBalanceService.
 */
class EntityIncomeController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.incomes.index', [
            'entity' => $financeEntity,
            'incomes' => $financeEntity->incomes()->with('financeAccount')->latest('income_date')->paginate(20),
            'title' => 'Pemasukan',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.incomes.create', [
            'entity' => $financeEntity,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => 'Tambah Pemasukan',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $this->validated($request, $financeEntity);

        $income = $financeEntity->incomes()->create([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'category_id' => $validated['category_id'],
            'context' => FinanceOwnership::contextFor($financeEntity),
            'source' => $validated['source'],
            'amount' => $this->parseRupiah($validated['amount']),
            'income_date' => $validated['income_date'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->auditLogs()->recordCreated($income, $financeEntity);

        return redirect()->route('entity.incomes.index', $financeEntity)->with('success', 'Pemasukan dicatat.');
    }

    public function edit(FinanceEntity $financeEntity, Income $income): View
    {
        $this->owned($financeEntity, $income);

        return view('entity.incomes.edit', [
            'entity' => $financeEntity,
            'income' => $income,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'accounts' => $this->selectableAccounts($financeEntity, $income->finance_account_id),
            'title' => 'Edit Pemasukan',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, Income $income): RedirectResponse
    {
        $this->owned($financeEntity, $income);
        $validated = $this->validated($request, $financeEntity, $income->finance_account_id);
        $old = $this->auditLogs()->snapshot($income);

        $income->update([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'category_id' => $validated['category_id'],
            'context' => FinanceOwnership::contextFor($financeEntity),
            'source' => $validated['source'],
            'amount' => $this->parseRupiah($validated['amount']),
            'income_date' => $validated['income_date'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->auditLogs()->recordUpdated($income->fresh(), $old, $financeEntity);

        return redirect()->route('entity.incomes.index', $financeEntity)->with('success', 'Pemasukan diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, Income $income): RedirectResponse
    {
        $this->owned($financeEntity, $income);
        $old = $this->auditLogs()->snapshot($income);
        $income->delete();
        $this->auditLogs()->recordDeleted($income, $old, $financeEntity);

        return redirect()->route('entity.incomes.index', $financeEntity)->with('success', 'Pemasukan dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, FinanceEntity $entity, ?int $currentAccountId = null): array
    {
        return $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'amount' => $this->positiveRupiahRules(),
            'income_date' => ['required', 'date'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('finance_entity_id', $entity->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'finance_entity_id' => ['prohibited'],
            ...$this->financeAccountRules($entity, $currentAccountId),
        ]);
    }

    private function owned(FinanceEntity $entity, Income $income): void
    {
        abort_unless((int) $income->finance_entity_id === (int) $entity->id, 404);
    }
}
