<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\FinanceEntityType;
use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyFinanceEntityRequest;
use App\Http\Requests\Admin\StoreFinanceEntityRequest;
use App\Http\Requests\Admin\UpdateFinanceEntityRequest;
use App\Models\FinanceEntity;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class FinanceEntityController extends Controller
{
    use RecordsAudit;

    public function index(): View
    {
        $entities = FinanceEntity::query()
            ->latest()
            ->paginate(15);

        return view('admin.finance-entities.index', [
            'title' => 'Finance Entities',
            'entities' => $entities,
        ]);
    }

    public function create(): View
    {
        return view('admin.finance-entities.create', [
            'title' => 'Tambah Finance Entity',
            'types' => FinanceEntityType::cases(),
        ]);
    }

    public function store(StoreFinanceEntityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        DB::transaction(function () use ($data): void {
            $entity = FinanceEntity::create($data);
            app(FinanceAccountService::class)->ensureDefaultAccount($entity);
            $this->auditLogs()->recordCreated($entity, $entity);
        });

        return redirect()
            ->route('admin.finance-entities.index')
            ->with('success', 'Finance entity berhasil dibuat.');
    }

    public function edit(FinanceEntity $financeEntity): View
    {
        return view('admin.finance-entities.edit', [
            'title' => 'Edit Finance Entity',
            'entity' => $financeEntity,
            'types' => FinanceEntityType::cases(),
        ]);
    }

    public function update(UpdateFinanceEntityRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $old = $this->auditLogs()->snapshot($financeEntity);
        $financeEntity->update($request->validated());
        $this->auditLogs()->recordUpdated($financeEntity->fresh(), $old, $financeEntity);

        return redirect()
            ->route('admin.finance-entities.index')
            ->with('success', 'Finance entity berhasil diperbarui.');
    }

    public function activate(FinanceEntity $financeEntity): RedirectResponse
    {
        $old = $this->auditLogs()->snapshot($financeEntity);
        $financeEntity->update(['is_active' => true]);
        $this->auditLogs()->record($financeEntity->fresh(), AuditAction::ACTIVATE, $financeEntity, $old, $this->auditLogs()->snapshot($financeEntity->fresh()));

        return redirect()
            ->route('admin.finance-entities.index')
            ->with('success', 'Finance entity diaktifkan.');
    }

    public function deactivate(FinanceEntity $financeEntity): RedirectResponse
    {
        $old = $this->auditLogs()->snapshot($financeEntity);
        $financeEntity->update(['is_active' => false]);
        $this->auditLogs()->record($financeEntity->fresh(), AuditAction::DEACTIVATE, $financeEntity, $old, $this->auditLogs()->snapshot($financeEntity->fresh()));

        return redirect()
            ->route('admin.finance-entities.index')
            ->with('success', 'Finance entity dinonaktifkan.');
    }

    public function destroy(
        DestroyFinanceEntityRequest $request,
        FinanceEntity $financeEntity,
        FinanceEntityDeletionService $deletion,
    ): RedirectResponse {
        try {
            $deletion->delete($financeEntity);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.finance-entities.index')
                ->with('danger', 'Finance Entity gagal dihapus. Tidak ada data yang diubah.');
        }

        return redirect()
            ->route('admin.finance-entities.index')
            ->with('success', 'Finance Entity dan seluruh data terkait berhasil dihapus permanen.');
    }
}
