<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\FinanceEntity;
use App\Models\RecurringTransaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityRecurringTransactionController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah;

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.recurring.index', [
            'entity' => $financeEntity,
            'recurrings' => $financeEntity->recurringTransactions()->orderBy('name')->get(),
            'title' => 'Transaksi Berulang',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.recurring.create', [
            'entity' => $financeEntity,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => 'Tambah Aturan Berulang',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $financeEntity->recurringTransactions()->create($this->prepared($request, $financeEntity));

        return redirect()->route('entity.recurring.index', $financeEntity)->with('success', 'Aturan berulang disimpan.');
    }

    public function edit(FinanceEntity $financeEntity, RecurringTransaction $recurringTransaction): View
    {
        $this->owned($financeEntity, $recurringTransaction);

        return view('entity.recurring.edit', [
            'entity' => $financeEntity,
            'recurring' => $recurringTransaction,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'accounts' => $this->selectableAccounts($financeEntity, $recurringTransaction->finance_account_id),
            'title' => 'Edit Aturan Berulang',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->owned($financeEntity, $recurringTransaction);
        $recurringTransaction->update($this->prepared($request, $financeEntity));

        return redirect()->route('entity.recurring.index', $financeEntity)->with('success', 'Aturan diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->owned($financeEntity, $recurringTransaction);
        $recurringTransaction->delete();

        return redirect()->route('entity.recurring.index', $financeEntity)->with('success', 'Aturan dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function prepared(Request $request, FinanceEntity $entity): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('finance_entity_id', $entity->id)],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:255'],
            'finance_entity_id' => ['prohibited'],
            ...$this->financeAccountRules($entity),
        ]);

        $start = Carbon::parse($validated['start_date']);
        $tmp = new RecurringTransaction([
            'frequency' => $validated['frequency'],
            'day_of_month' => $validated['day_of_month'] ?? null,
            'start_date' => $start,
        ]);

        return [
            'name' => $validated['name'],
            'amount' => $this->parseRupiah($validated['amount']),
            'category_id' => $validated['category_id'] ?? null,
            'frequency' => $validated['frequency'],
            'day_of_month' => $validated['day_of_month'] ?? null,
            'start_date' => $start,
            'end_date' => $validated['end_date'] ?? null,
            'next_due' => $tmp->calculateNextDue($start),
            'active' => true,
            'description' => $validated['description'] ?? null,
            'finance_account_id' => $this->resolvedAccountId($validated, $entity),
        ];
    }

    private function owned(FinanceEntity $entity, RecurringTransaction $recurring): void
    {
        abort_unless((int) $recurring->finance_entity_id === (int) $entity->id, 404);
    }
}
