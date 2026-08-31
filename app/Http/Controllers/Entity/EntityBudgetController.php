<?php

namespace App\Http\Controllers\Entity;

use App\Exceptions\PlantationServiceException;
use App\Http\Controllers\Concerns\AssignsFinanceAccount;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Entity\Concerns\ParsesRupiah;
use App\Models\Budget;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use App\Services\PlantationOperatingBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class EntityBudgetController extends Controller
{
    use AssignsFinanceAccount, ParsesRupiah, RecordsAudit;

    public function index(FinanceEntity $financeEntity): View
    {
        $plantationActive = $financeEntity->hasActivePlantationIntegration();

        return view('entity.budgets.index', [
            'entity' => $financeEntity,
            'plantationActive' => $plantationActive,
            'operatingBudgets' => $plantationActive
                ? $financeEntity->plantationOperatingBudgets()
                    ->latest('period_start')
                    ->latest('id')
                    ->get()
                : collect(),
            'budgets' => $financeEntity->budgets()
                ->with('category')
                ->withSum('activities', 'amount')
                ->latest('periode')
                ->get(),
            'title' => $plantationActive ? 'Anggaran Kebun' : 'Anggaran',
        ]);
    }

    public function create(Request $request, FinanceEntity $financeEntity): View
    {
        $plantationMode = $financeEntity->hasActivePlantationIntegration()
            && $request->query('mode') !== 'category';

        return view('entity.budgets.create', [
            'entity' => $financeEntity,
            'plantationMode' => $plantationMode,
            'categories' => $plantationMode
                ? collect()
                : $financeEntity->categories()->orderBy('name')->get(),
            'title' => $plantationMode ? 'Tambah Anggaran Kebun' : 'Tambah Anggaran',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity, PlantationOperatingBudgetService $operatingBudgets): RedirectResponse
    {
        if ($this->wantsPlantationOperatingBudget($request, $financeEntity)) {
            return $this->storePlantationOperatingBudget($request, $financeEntity, $operatingBudgets);
        }

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

    public function editOperating(FinanceEntity $financeEntity, PlantationOperatingBudget $plantationOperatingBudget): View
    {
        $this->ownedOperatingBudget($financeEntity, $plantationOperatingBudget);

        return view('entity.budgets.edit-operating', [
            'entity' => $financeEntity,
            'operatingBudget' => $plantationOperatingBudget,
            'title' => 'Ubah Anggaran Kebun',
        ]);
    }

    public function updateOperating(
        Request $request,
        FinanceEntity $financeEntity,
        PlantationOperatingBudget $plantationOperatingBudget,
        PlantationOperatingBudgetService $operatingBudgets,
    ): RedirectResponse {
        $this->ownedOperatingBudget($financeEntity, $plantationOperatingBudget);
        $payload = $this->plantationOperatingBudgetPayload($request);

        try {
            $operatingBudgets->update($plantationOperatingBudget, $payload);
        } catch (Throwable $exception) {
            return $this->plantationFailed($exception, $financeEntity);
        }

        return redirect()
            ->route('entity.budgets.index', $financeEntity)
            ->with('success', 'Anggaran kebun diperbarui dan dikirim ke Plantation. Saldo kas tidak berubah.');
    }

    public function syncOperating(
        FinanceEntity $financeEntity,
        PlantationOperatingBudget $plantationOperatingBudget,
        PlantationOperatingBudgetService $operatingBudgets,
    ): RedirectResponse {
        $this->ownedOperatingBudget($financeEntity, $plantationOperatingBudget);

        try {
            $operatingBudgets->sync($plantationOperatingBudget);
        } catch (Throwable $exception) {
            return $this->plantationFailed($exception, $financeEntity);
        }

        return redirect()
            ->route('entity.budgets.index', $financeEntity)
            ->with('success', 'Anggaran kebun berhasil dikirim ulang ke Plantation.');
    }

    private function storePlantationOperatingBudget(
        Request $request,
        FinanceEntity $financeEntity,
        PlantationOperatingBudgetService $operatingBudgets,
    ): RedirectResponse {
        abort_unless($financeEntity->isBusiness(), 404);

        if (! $financeEntity->hasActivePlantationIntegration()) {
            throw ValidationException::withMessages([
                'name' => 'Management Kebun harus aktif sebelum anggaran kebun dibuat.',
            ]);
        }

        $payload = $this->plantationOperatingBudgetPayload($request);

        try {
            $operatingBudgets->create($financeEntity, $payload);
        } catch (Throwable $exception) {
            return $this->plantationFailed($exception, $financeEntity);
        }

        return redirect()
            ->route('entity.budgets.index', $financeEntity)
            ->with('success', 'Anggaran kebun disimpan dan dikirim ke Plantation. Saldo kas tidak berubah.');
    }

    /**
     * @return array{name: string, period_start: string, period_end: string, allocated_amount: float}
     */
    private function plantationOperatingBudgetPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'allocated_amount' => $this->positiveRupiahRules(),
            'public_id' => ['prohibited'],
            'finance_entity_id' => ['prohibited'],
            'status' => ['prohibited'],
        ]);

        return [
            'name' => $validated['name'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'allocated_amount' => $this->parseRupiah($validated['allocated_amount']),
        ];
    }

    private function ownedOperatingBudget(FinanceEntity $entity, PlantationOperatingBudget $budget): void
    {
        abort_unless((int) $budget->finance_entity_id === (int) $entity->id, 404);
        abort_unless($entity->hasActivePlantationIntegration(), 404);
    }

    private function wantsPlantationOperatingBudget(Request $request, FinanceEntity $financeEntity): bool
    {
        $looksLikePlantation = $request->filled('period_start')
            || $request->filled('period_end')
            || $request->filled('allocated_amount');

        if ($looksLikePlantation) {
            return true;
        }

        return $financeEntity->hasActivePlantationIntegration()
            && ! $request->filled('category_id');
    }

    private function plantationFailed(Throwable $exception, FinanceEntity $financeEntity): RedirectResponse
    {
        $message = $exception instanceof PlantationServiceException
            || $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Terjadi kesalahan saat menghubungi Plantation Service.';

        return redirect()
            ->route('entity.budgets.index', $financeEntity)
            ->with('danger', $message);
    }

    private function owned(FinanceEntity $entity, Budget $budget): void
    {
        abort_unless((int) $budget->finance_entity_id === (int) $entity->id, 404);
    }
}
