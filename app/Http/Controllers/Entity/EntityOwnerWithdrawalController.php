<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOwnerWithdrawalRequest;
use App\Models\FinanceEntity;
use App\Services\OwnerWithdrawalService;
use App\Support\FinanceEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntityOwnerWithdrawalController extends Controller
{
    public function __construct(private readonly OwnerWithdrawalService $withdrawals) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $withdrawals = $financeEntity->isBusiness()
            ? $financeEntity->ownerWithdrawalsGiven()
            : $financeEntity->ownerWithdrawalsReceived();

        return view('entity.owner-withdrawals.index', [
            'entity' => $financeEntity,
            'withdrawals' => $withdrawals
                ->with(['businessEntity', 'familyEntity', 'sourceAccount', 'destinationAccount'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
            'title' => $financeEntity->isBusiness() ? 'Prive / Owner Withdrawal' : 'Penerimaan dari Prive Usaha',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.owner-withdrawals.create', [
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'families' => FinanceEntityAccess::withdrawalDestinations()->load('activeAccounts'),
            'title' => 'Tarik Prive',
        ]);
    }

    public function store(StoreOwnerWithdrawalRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $family = $request->resolvedFamily();
        abort_unless($family instanceof FinanceEntity, 422);

        $this->withdrawals->create($financeEntity, $family, $request->payload());

        return redirect()
            ->route('entity.owner-withdrawals.index', $financeEntity)
            ->with('success', 'Prive dicatat.');
    }
}
