<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PlantationServiceException;
use App\Http\Controllers\Concerns\LogsUnexpectedPlantationBudgetSync;
use App\Http\Controllers\Controller;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use App\Services\PlantationOperatingBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class PlantationOperatingBudgetController extends Controller
{
    use LogsUnexpectedPlantationBudgetSync;

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

    public function sync(FinanceEntity $financeEntity, PlantationOperatingBudget $operatingBudget): RedirectResponse
    {
        if ($redirect = $this->guard($financeEntity)) {
            return $redirect;
        }

        abort_unless((int) $operatingBudget->finance_entity_id === (int) $financeEntity->id, 404);

        try {
            $this->budgets->sync($operatingBudget);
        } catch (Throwable $exception) {
            return $this->failed($exception, $financeEntity, $operatingBudget);
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

    private function failed(
        Throwable $exception,
        FinanceEntity $financeEntity,
        PlantationOperatingBudget $operatingBudget,
    ): RedirectResponse {
        $this->logUnexpectedPlantationBudgetSync($exception, 'sync', $financeEntity, $operatingBudget);

        $message = PlantationServiceException::flashMessage(
            $exception,
            'Terjadi kesalahan saat sinkronisasi anggaran.',
        );

        return redirect()
            ->route('admin.plantation-integrations.operating-budgets.index', $financeEntity)
            ->with('danger', $message);
    }
}
