<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\FinanceEntity;
use App\Models\SavingsGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntitySavingsGoalController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.savings-goals.index', [
            'entity' => $financeEntity,
            'goals' => $financeEntity->savingsGoals()->orderBy('title')->get(),
            'title' => 'Tabungan',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.savings-goals.create', [
            'entity' => $financeEntity,
            'title' => 'Tambah Goal Tabungan',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'string'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'finance_entity_id' => ['prohibited'],
        ]);

        $financeEntity->savingsGoals()->create([
            'title' => $validated['title'],
            'target_amount' => $this->parseRupiah($validated['target_amount']),
            'deadline' => $validated['deadline'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('entity.savings-goals.index', $financeEntity)->with('success', 'Goal tabungan disimpan.');
    }

    public function show(FinanceEntity $financeEntity, SavingsGoal $savings_goal): View
    {
        $this->owned($financeEntity, $savings_goal);

        $chrono = $savings_goal->contributions()
            ->with('financeAccount')
            ->orderBy('contributed_on')
            ->orderBy('id')
            ->get();

        $targetAmount = (float) $savings_goal->target_amount;
        $totalCollected = (float) $chrono->sum('amount');
        $remainingAmount = max(0.0, $targetAmount - $totalCollected);
        $excessAmount = max(0.0, $totalCollected - $targetAmount);
        $percentage = $targetAmount > 0.0
            ? round(($totalCollected / $targetAmount) * 100, 1)
            : 0.0;
        $progressVisual = min($percentage, 100.0);

        $running = 0.0;
        $contributions = $chrono
            ->map(function ($contribution) use (&$running) {
                $running += (float) $contribution->amount;

                return [
                    'id' => $contribution->id,
                    'date_label' => $contribution->contributed_on?->copy()->locale('id')->translatedFormat('d M Y') ?: '—',
                    'account_name' => $contribution->financeAccount?->name ?: 'Rekening tidak tersedia',
                    'amount' => (float) $contribution->amount,
                    'cumulative' => $running,
                ];
            })
            ->reverse()
            ->values();

        return view('entity.savings-goals.show', [
            'entity' => $financeEntity,
            'goal' => $savings_goal,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => $savings_goal->title,
            'targetAmount' => $targetAmount,
            'totalCollected' => $totalCollected,
            'remainingAmount' => $remainingAmount,
            'excessAmount' => $excessAmount,
            'percentage' => $percentage,
            'progressVisual' => $progressVisual,
            'isAchieved' => $percentage >= 100.0,
            'contributionCount' => $chrono->count(),
            'contributions' => $contributions,
        ]);
    }

    public function edit(FinanceEntity $financeEntity, SavingsGoal $savings_goal): View
    {
        $this->owned($financeEntity, $savings_goal);

        return view('entity.savings-goals.edit', [
            'entity' => $financeEntity,
            'goal' => $savings_goal,
            'title' => 'Edit Goal',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, SavingsGoal $savings_goal): RedirectResponse
    {
        $this->owned($financeEntity, $savings_goal);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'string'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'finance_entity_id' => ['prohibited'],
        ]);

        $savings_goal->update([
            'title' => $validated['title'],
            'target_amount' => $this->parseRupiah($validated['target_amount']),
            'deadline' => $validated['deadline'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('entity.savings-goals.index', $financeEntity)->with('success', 'Goal diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, SavingsGoal $savings_goal): RedirectResponse
    {
        $this->owned($financeEntity, $savings_goal);
        $savings_goal->delete();

        return redirect()->route('entity.savings-goals.index', $financeEntity)->with('success', 'Goal dihapus.');
    }

    public function storeContribution(Request $request, FinanceEntity $financeEntity, SavingsGoal $savings_goal): RedirectResponse
    {
        $this->owned($financeEntity, $savings_goal);
        $validated = $request->validate([
            'amount' => $this->positiveRupiahRules(),
            'contributed_on' => ['required', 'date'],
            ...$this->financeAccountRules($financeEntity),
        ]);

        $contribution = $savings_goal->contributions()->create([
            'finance_account_id' => $this->resolvedAccountId($validated, $financeEntity),
            'amount' => $this->parseRupiah($validated['amount']),
            'contributed_on' => $validated['contributed_on'],
        ]);
        $this->auditLogs()->recordCreated($contribution, $financeEntity);

        return redirect()->route('entity.savings-goals.show', [$financeEntity, $savings_goal])
            ->with('success', 'Setoran tabungan berhasil dicatat.');
    }

    private function owned(FinanceEntity $entity, SavingsGoal $goal): void
    {
        abort_unless((int) $goal->finance_entity_id === (int) $entity->id, 404);
    }
}
