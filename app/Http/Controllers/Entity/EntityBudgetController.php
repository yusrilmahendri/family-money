<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\Budget;
use App\Models\FinanceEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityBudgetController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.budgets.index', [
            'entity' => $financeEntity,
            'budgets' => $financeEntity->budgets()
                ->with('category')
                ->withSum('activities', 'amount')
                ->latest('periode')
                ->get(),
            'title' => 'Anggaran',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.budgets.create', [
            'entity' => $financeEntity,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'title' => 'Tambah Anggaran',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => $this->positiveRupiahRules(),
            'periode' => ['required', 'date'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('finance_entity_id', $financeEntity->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'finance_entity_id' => ['prohibited'],
        ]);

        $budget = $financeEntity->budgets()->create([
            'category_id' => $validated['category_id'],
            'amount' => $this->parseRupiah($validated['amount']),
            'amount_saldo' => 0,
            'periode' => $validated['periode'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->auditLogs()->recordCreated($budget, $financeEntity);

        return redirect()->route('entity.budgets.index', $financeEntity)->with('success', 'Anggaran disimpan.');
    }

    public function show(FinanceEntity $financeEntity, Budget $budget): View
    {
        $this->owned($financeEntity, $budget);
        $budget->load(['category', 'activities.financeAccount']);

        return view('entity.budgets.show', [
            'entity' => $financeEntity,
            'budget' => $budget,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => 'Anggaran',
        ]);
    }

    public function edit(FinanceEntity $financeEntity, Budget $budget): View
    {
        $this->owned($financeEntity, $budget);

        return view('entity.budgets.edit', [
            'entity' => $financeEntity,
            'budget' => $budget,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'title' => 'Edit Anggaran',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, Budget $budget): RedirectResponse
    {
        $this->owned($financeEntity, $budget);
        $validated = $request->validate([
            'amount' => $this->positiveRupiahRules(),
            'periode' => ['required', 'date'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('finance_entity_id', $financeEntity->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'finance_entity_id' => ['prohibited'],
        ]);
        $old = $this->auditLogs()->snapshot($budget);

        $budget->update([
            'category_id' => $validated['category_id'],
            'amount' => $this->parseRupiah($validated['amount']),
            'periode' => $validated['periode'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->auditLogs()->recordUpdated($budget->fresh(), $old, $financeEntity);

        return redirect()->route('entity.budgets.index', $financeEntity)->with('success', 'Anggaran diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, Budget $budget): RedirectResponse
    {
        $this->owned($financeEntity, $budget);
        $budget->delete();

        return redirect()->route('entity.budgets.index', $financeEntity)->with('success', 'Anggaran dihapus.');
    }

    public function storeActivity(Request $request, FinanceEntity $financeEntity, Budget $budget): RedirectResponse
    {
        $this->owned($financeEntity, $budget);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => $this->positiveRupiahRules(),
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            ...$this->financeAccountRules($financeEntity),
        ]);

        $activity = $budget->activities()->create([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'name' => $validated['name'],
            'amount' => $this->parseRupiah($validated['amount']),
            'activity_date' => $validated['activity_date'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->auditLogs()->recordCreated($activity, $financeEntity);

        return redirect()->route('entity.budgets.show', [$financeEntity, $budget])->with('success', 'Biaya dicatat.');
    }

    private function owned(FinanceEntity $entity, Budget $budget): void
    {
        abort_unless((int) $budget->finance_entity_id === (int) $entity->id, 404);
    }
}
