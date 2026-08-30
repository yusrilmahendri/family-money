<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PlantationServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlantationAccessLinkRequest;
use App\Models\FinanceEntity;
use App\Services\PlantationIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PlantationAccessLinkController extends Controller
{
    public function __construct(private readonly PlantationIntegrationService $integrations) {}

    public function index(FinanceEntity $financeEntity): View|RedirectResponse
    {
        if (! $financeEntity->isBusiness() || $financeEntity->plantationIntegration === null) {
            return redirect()
                ->route('admin.plantation-integrations.index')
                ->with('danger', 'Entity ini belum terhubung ke Management Kebun.');
        }

        $serviceUnavailable = false;
        $links = [];

        try {
            $links = $this->integrations->listAccessLinks($financeEntity);
        } catch (PlantationServiceException) {
            $serviceUnavailable = true;
        }

        return view('admin.plantation-access-links.index', [
            'title' => 'Access Links Kebun',
            'entity' => $financeEntity,
            'integration' => $financeEntity->plantationIntegration,
            'links' => $links,
            'serviceUnavailable' => $serviceUnavailable,
        ]);
    }

    public function store(StorePlantationAccessLinkRequest $request, FinanceEntity $financeEntity): View|RedirectResponse
    {
        try {
            $issued = $this->integrations->issueAccessLink($financeEntity, $request->validated());
        } catch (Throwable $exception) {
            return $this->failed($exception, $financeEntity);
        }

        return view('admin.plantation-access-links.created', [
            'title' => 'Private Link Kebun Dibuat',
            'entity' => $financeEntity,
            'accessUrl' => $issued['access_url'] ?? null,
        ]);
    }

    public function revoke(FinanceEntity $financeEntity, int $tokenId): RedirectResponse
    {
        try {
            $this->integrations->revokeAccessLink($financeEntity, $tokenId);
        } catch (Throwable $exception) {
            return $this->failed($exception, $financeEntity);
        }

        return $this->backToLinks($financeEntity, 'Access link kebun direvoke.');
    }

    public function activate(FinanceEntity $financeEntity, int $tokenId): RedirectResponse
    {
        try {
            $this->integrations->activateAccessLink($financeEntity, $tokenId);
        } catch (Throwable $exception) {
            return $this->failed($exception, $financeEntity);
        }

        return $this->backToLinks($financeEntity, 'Access link kebun diaktifkan kembali.');
    }

    public function regenerate(FinanceEntity $financeEntity, int $tokenId): View|RedirectResponse
    {
        try {
            $issued = $this->integrations->regenerateAccessLink($financeEntity, $tokenId);
        } catch (Throwable $exception) {
            return $this->failed($exception, $financeEntity);
        }

        return view('admin.plantation-access-links.created', [
            'title' => 'Private Link Kebun Diperbarui',
            'entity' => $financeEntity,
            'accessUrl' => $issued['access_url'] ?? null,
        ]);
    }

    public function destroy(FinanceEntity $financeEntity, int $tokenId): RedirectResponse
    {
        try {
            $this->integrations->deleteAccessLink($financeEntity, $tokenId);
        } catch (Throwable $exception) {
            return $this->failed($exception, $financeEntity);
        }

        return $this->backToLinks($financeEntity, 'Access link kebun dihapus.');
    }

    private function backToLinks(FinanceEntity $entity, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.plantation-integrations.access-links.index', $entity)
            ->with('success', $message);
    }

    private function failed(Throwable $exception, FinanceEntity $entity): RedirectResponse
    {
        $message = $exception instanceof PlantationServiceException
            || $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Terjadi kesalahan saat menghubungi Plantation Service.';

        return redirect()
            ->route('admin.plantation-integrations.access-links.index', $entity)
            ->with('danger', $message);
    }
}
