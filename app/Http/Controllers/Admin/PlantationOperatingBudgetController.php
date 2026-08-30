<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PlantationServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlantationOperatingBudgetRequest;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use App\Services\PlantationOperatingBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PlantationOperatingBudgetController extends Controller
{
    public function __construct(private readonly PlantationOperatingBudgetService $budgets) {}

    public function index(FinanceEntity $financeEntity): View|RedirectResponse
    {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        return view('admin.plantation-operating-budgets.index', [
            'title' => 'Anggaran Kebun',
            'entity' => $financeEntity,
            'integration' => $financeEntity->plantationIntegration,
            'budgets' => $financeEntity->plantationOperatingBudgets()
                ->latest('period_start')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(FinanceEntity $financeEntity): View|RedirectResponse
    {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        return view('admin.plantation-operating-budgets.create', [
            'title' => 'Buat Anggaran Kebun',
            'entity' => $financeEntity,
        ]);
    }

    public function store(StorePlantationOperatingBudgetRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        try {
            $this->budgets->create($financeEntity, $request->payload());
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.operating-budgets.index', $financeEntity));
        }

        return redirect()
            ->route('admin.plantation-integrations.operating-budgets.index', $financeEntity)
            ->with('success', 'Anggaran kebun dibuat dan dikirim ke Plantation.');
    }

    public function edit(FinanceEntity $financeEntity, PlantationOperatingBudget $operatingBudget): View|RedirectResponse
    {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        abort_unless((int) $operatingBudget->finance_entity_id === (int) $financeEntity->id, 404);

        return view('admin.plantation-operating-budgets.edit', [
            'title' => 'Ubah Anggaran Kebun',
            'entity' => $financeEntity,
            'budget' => $operatingBudget,
        ]);
    }

    public function update(
        StorePlantationOperatingBudgetRequest $request,
        FinanceEntity $financeEntity,
        PlantationOperatingBudget $operatingBudget,
    ): RedirectResponse {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        abort_unless((int) $operatingBudget->finance_entity_id === (int) $financeEntity->id, 404);

        try {
            $this->budgets->update($operatingBudget, $request->payload());
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.operating-budgets.edit', [$financeEntity, $operatingBudget]));
        }

        return redirect()
            ->route('admin.plantation-integrations.operating-budgets.index', $financeEntity)
            ->with('success', 'Anggaran kebun diperbarui dan disinkronkan ke Plantation.');
    }

    public function sync(FinanceEntity $financeEntity, PlantationOperatingBudget $operatingBudget): RedirectResponse
    {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        abort_unless((int) $operatingBudget->finance_entity_id === (int) $financeEntity->id, 404);

        try {
            $this->budgets->sync($operatingBudget);
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.operating-budgets.index', $financeEntity));
        }

        return redirect()
            ->route('admin.plantation-integrations.operating-budgets.index', $financeEntity)
            ->with('success', 'Anggaran berhasil dikirim ulang ke Plantation.');
    }

    private function guard(FinanceEntity $financeEntity): ?RedirectResponse
    {
        $financeEntity->loadMissing('plantationIntegration');

        if (! $financeEntity->isBusiness() || $financeEntity->plantationIntegration === null) {
            return redirect()
                ->route('admin.plantation-integrations.index')
                ->with('danger', 'Entity ini belum terhubung ke Management Kebun.');
        }

        return null;
    }

    private function failed(Throwable $exception, string $redirectTo): RedirectResponse
    {
        $message = $exception instanceof PlantationServiceException
            || $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Terjadi kesalahan saat menghubungi Plantation Service.';

        return redirect()
            ->to($redirectTo)
            ->with('danger', $message);
    }
}
