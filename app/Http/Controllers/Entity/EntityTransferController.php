<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceTransferRequest;
use App\Models\FinanceEntity;
use App\Services\FinanceTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntityTransferController extends Controller
{
    public function __construct(private readonly FinanceTransferService $transfers) {}

    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.transfers.index', [
            'entity' => $financeEntity,
            'transfers' => $financeEntity->transfers()
                ->with(['sourceAccount', 'destinationAccount'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
            'title' => 'Transfer',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.transfers.create', [
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'title' => 'Transfer Kas / Rekening',
        ]);
    }

    public function store(StoreFinanceTransferRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $this->transfers->create($financeEntity, $request->payload());

        return redirect()
            ->route('entity.transfers.index', $financeEntity)
            ->with('success', 'Transfer dicatat.');
    }
}
