<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceEntityType;
use App\Exceptions\PlantationServiceException;
use App\Http\Controllers\Controller;
use App\Models\FinanceEntity;
use App\Services\PlantationIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PlantationIntegrationController extends Controller
{
    public function __construct(private readonly PlantationIntegrationService $integrations) {}

    public function index(): View
    {
        $entities = FinanceEntity::query()
            ->where('type', FinanceEntityType::BUSINESS)
            ->with('plantationIntegration')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.plantation-integrations.index', [
            'title' => 'Management Kebun',
            'entities' => $entities,
        ]);
    }

    public function show(FinanceEntity $financeEntity): View|RedirectResponse
    {
        if (! $financeEntity->isBusiness()) {
            return redirect()
                ->route('admin.plantation-integrations.index')
                ->with('danger', 'Hanya Finance Entity bertipe BUSINESS yang dapat dikelola di Management Kebun.');
        }

        $financeEntity->load('plantationIntegration');

        return view('admin.plantation-integrations.show', [
            'title' => 'Kelola Management Kebun',
            'entity' => $financeEntity,
            'integration' => $financeEntity->plantationIntegration,
            'processedEventsCount' => \App\Models\ProcessedIntegrationEvent::query()
                ->where('finance_entity_id', $financeEntity->id)
                ->count(),
            'lastProcessedEvent' => \App\Models\ProcessedIntegrationEvent::query()
                ->where('finance_entity_id', $financeEntity->id)
                ->latest('processed_at')
                ->first(),
        ]);
    }

    public function activate(FinanceEntity $financeEntity): RedirectResponse
    {
        try {
            $this->integrations->activate($financeEntity);
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.index'));
        }

        return redirect()
            ->route('admin.plantation-integrations.show', $financeEntity)
            ->with('success', 'Management Kebun berhasil diaktifkan.');
    }

    public function sync(FinanceEntity $financeEntity): RedirectResponse
    {
        try {
            $this->integrations->sync($financeEntity);
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.show', $financeEntity));
        }

        return redirect()
            ->route('admin.plantation-integrations.show', $financeEntity)
            ->with('success', 'Metadata berhasil disinkronkan ke Plantation.');
    }

    public function syncHarvestReceivables(FinanceEntity $financeEntity): RedirectResponse
    {
        try {
            $counts = $this->integrations->syncHarvestReceivables($financeEntity);
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.show', $financeEntity));
        }

        return redirect()
            ->route('admin.plantation-integrations.show', $financeEntity)
            ->with('success', sprintf(
                'Piutang panen disinkronkan (%d penjualan, %d pembayaran).',
                $counts['posted'],
                $counts['payments'],
            ));
    }

    public function deactivate(FinanceEntity $financeEntity): RedirectResponse
    {
        try {
            $this->integrations->deactivate($financeEntity);
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.index'));
        }

        return redirect()
            ->route('admin.plantation-integrations.index')
            ->with('success', 'Management Kebun dinonaktifkan. Data kebun tidak dihapus.');
    }

    public function reactivate(FinanceEntity $financeEntity): RedirectResponse
    {
        try {
            $this->integrations->reactivate($financeEntity);
        } catch (Throwable $exception) {
            return $this->failed($exception, route('admin.plantation-integrations.index'));
        }

        return redirect()
            ->route('admin.plantation-integrations.index')
            ->with('success', 'Management Kebun diaktifkan kembali.');
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
