<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceivablePaymentRequest;
use App\Http\Requests\StoreReceivableRequest;
use App\Http\Requests\UpdateReceivableRequest;
use App\Models\FinanceEntity;
use App\Models\Receivable;
use App\Services\ReceivableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntityReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $receivables) {}

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.receivables.index', [
            'entity' => $financeEntity,
            'receivables' => $financeEntity->receivables()
                ->latest('receivable_date')
                ->latest('id')
                ->paginate(20),
            'title' => 'Piutang',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.receivables.create', [
            'entity' => $financeEntity,
            'title' => 'Tambah Piutang',
        ]);
    }

    public function store(StoreReceivableRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $this->receivables->create($financeEntity, $request->payload());

        return redirect()
            ->route('entity.receivables.index', $financeEntity)
            ->with('success', 'Piutang dicatat.');
    }

    public function show(FinanceEntity $financeEntity, Receivable $receivable): View
    {
        $this->owned($financeEntity, $receivable);
        $receivable->load(['payments' => fn ($q) => $q->with('financeAccount')->latest('payment_date')->latest('id')]);

        return view('entity.receivables.show', [
            'entity' => $financeEntity,
            'receivable' => $receivable,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => $receivable->party_name,
        ]);
    }

    public function edit(FinanceEntity $financeEntity, Receivable $receivable): View
    {
        $this->owned($financeEntity, $receivable);

        return view('entity.receivables.edit', [
            'entity' => $financeEntity,
            'receivable' => $receivable,
            'title' => 'Edit Piutang',
        ]);
    }

    public function update(UpdateReceivableRequest $request, FinanceEntity $financeEntity, Receivable $receivable): RedirectResponse
    {
        $this->owned($financeEntity, $receivable);
        $this->receivables->update($receivable, $request->payload());

        return redirect()
            ->route('entity.receivables.show', [$financeEntity, $receivable])
            ->with('success', 'Piutang diperbarui.');
    }

    public function storePayment(StoreReceivablePaymentRequest $request, FinanceEntity $financeEntity, Receivable $receivable): RedirectResponse
    {
        $this->owned($financeEntity, $receivable);
        $this->receivables->recordPayment($receivable, $request->payload());

        return redirect()
            ->route('entity.receivables.show', [$financeEntity, $receivable])
            ->with('success', 'Pembayaran piutang dicatat.');
    }

    private function owned(FinanceEntity $entity, Receivable $receivable): void
    {
        abort_unless((int) $receivable->finance_entity_id === (int) $entity->id, 404);
    }
}
