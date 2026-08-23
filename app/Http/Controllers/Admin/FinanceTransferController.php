<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceTransferRequest;
use App\Models\FinanceEntity;
use App\Services\FinanceTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceTransferController extends Controller
{
    public function __construct(private readonly FinanceTransferService $transfers) {}

    public function index(FinanceEntity $financeEntity): View
    {
        return view('admin.transfers.index', [
            'title' => 'Transfer',
            'entity' => $financeEntity,
            'transfers' => $financeEntity->transfers()
                ->with(['sourceAccount', 'destinationAccount'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('admin.transfers.create', [
            'title' => 'Transfer Kas / Rekening',
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
        ]);
    }

    public function store(StoreFinanceTransferRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $this->transfers->create($financeEntity, $request->payload());

        return redirect()
            ->route('admin.finance-entities.transfers.index', $financeEntity)
            ->with('success', 'Transfer dicatat.');
    }
}
