<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Models\GoalContribution;
use App\Models\SavingsGoal;
use App\Services\FinanceContextService;
use App\Support\FinanceContext;
use App\Support\Rupiah;
use Illuminate\Http\Request;

class SavingsGoalController extends Controller
{
    use AssignsFinanceAccount;

    public function index()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $goals = SavingsGoal::query()->orderBy('title')->get();

        return view('savings_goals.index', [
            'title' => 'Tabungan & goals',
            'goals' => $goals,
        ]);
    }

    public function create()
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }

        return view('savings_goals.create', [
            'title' => 'Goal tabungan baru',
        ]);
    }

    public function store(Request $request)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'string'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        SavingsGoal::create([
            'finance_entity_id' => \App\Support\FinanceOwnership::defaultEntityIdForContext(\App\Support\FinanceContext::PRIBADI),
            'title' => $validated['title'],
            'target_amount' => $this->parseRupiah($validated['target_amount']),
            'deadline' => $validated['deadline'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('savings-goals.index')->with('success', 'Goal tabungan disimpan.');
    }

    public function show(SavingsGoal $savings_goal)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $savings_goal->load(['contributions' => fn ($q) => $q->orderBy('contributed_on', 'desc')]);
        $saved = $savings_goal->savedTotal();
        $target = (float) $savings_goal->target_amount;
        $pct = $target > 0 ? min(100, round(($saved / $target) * 100, 1)) : 0;

        return view('savings_goals.show', [
            'title' => $savings_goal->title,
            'goal' => $savings_goal,
            'saved_total' => $saved,
            'progress_pct' => $pct,
        ]);
    }

    public function edit(SavingsGoal $savings_goal)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }

        return view('savings_goals.edit', [
            'title' => 'Ubah goal',
            'goal' => $savings_goal,
        ]);
    }

    public function update(Request $request, SavingsGoal $savings_goal)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'string'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $savings_goal->update([
            'title' => $validated['title'],
            'target_amount' => $this->parseRupiah($validated['target_amount']),
            'deadline' => $validated['deadline'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('savings-goals.index')->with('success', 'Goal diperbarui.');
    }

    public function destroy(SavingsGoal $savings_goal)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $savings_goal->delete();

        return redirect()->route('savings-goals.index')->with('success', 'Goal dihapus.');
    }

    public function storeContribution(Request $request, SavingsGoal $savings_goal)
    {
        if ($r = FinanceContextService::guardPersonal()) {
            return $r;
        }
        $validated = $request->validate([
            'amount' => ['required', 'string'],
            'contributed_on' => ['required', 'date'],
            ...$this->legacyAccountRules(FinanceContext::PRIBADI),
        ]);

        $entity = $savings_goal->financeEntity;

        GoalContribution::create([
            'savings_goal_id' => $savings_goal->id,
            'finance_account_id' => $entity ? $this->resolvedAccountId($validated, $entity) : null,
            'amount' => $this->parseRupiah($validated['amount']),
            'contributed_on' => $validated['contributed_on'],
        ]);

        return redirect()->route('savings-goals.show', $savings_goal)->with('success', 'Tabungan ke goal dicatat.');
    }

    private function parseRupiah(string $raw): string
    {
        return Rupiah::parse($raw);
    }
}
