<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\BudgetActivity;
use App\Models\FinanceEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityOperationalExpenseController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        $activities = BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $financeEntity->id))
            ->with(['budget.category', 'financeAccount'])
            ->latest('activity_date')
            ->paginate(20);

        return view('entity.operational.index', [
            'entity' => $financeEntity,
            'activities' => $activities,
            'title' => 'Biaya Operasional',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.operational.create', [
            'entity' => $financeEntity,
            'budgets' => $financeEntity->budgets()->with('category')->latest('periode')->get(),
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => 'Tambah Biaya Operasional',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $request->validate([
            'budget_id' => ['required', Rule::exists('budgets', 'id')->where('finance_entity_id', $financeEntity->id)],
            'name' => ['required', 'string', 'max:255'],
            'amount' => $this->positiveRupiahRules(),
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            ...$this->financeAccountRules($financeEntity),
        ]);

        $budget = $financeEntity->budgets()->findOrFail($validated['budget_id']);

        $activity = $budget->activities()->create([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'name' => $validated['name'],
            'amount' => $this->parseRupiah($validated['amount']),
            'activity_date' => $validated['activity_date'],
            'description' => $validated['description'] ?? null,
        ]);
        $this->auditLogs()->recordCreated($activity, $financeEntity);

        return redirect()->route('entity.operational.index', $financeEntity)->with('success', 'Biaya operasional dicatat.');
    }
}
