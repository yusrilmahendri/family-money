<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceEntityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOwnerWithdrawalRequest;
use App\Models\FinanceEntity;
use App\Services\OwnerWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OwnerWithdrawalController extends Controller
{
    public function __construct(private readonly OwnerWithdrawalService $withdrawals) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $withdrawals = $financeEntity->isBusiness()
            ? $financeEntity->ownerWithdrawalsGiven()
            : $financeEntity->ownerWithdrawalsReceived();

        return view('admin.owner-withdrawals.index', [
            'title' => $financeEntity->isBusiness() ? 'Prive / Owner Withdrawal' : 'Penerimaan dari Prive Usaha',
            'entity' => $financeEntity,
            'withdrawals' => $withdrawals
                ->with(['businessEntity', 'familyEntity', 'sourceAccount', 'destinationAccount'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        abort_unless($financeEntity->isBusiness(), 404);

        return view('admin.owner-withdrawals.create', [
            'title' => 'Tarik Prive',
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'families' => FinanceEntity::query()
                ->where('type', FinanceEntityType::FAMILY)
                ->where('is_active', true)
                ->whereHas('accounts', fn ($query) => $query->where('is_active', true))
                ->with('activeAccounts')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreOwnerWithdrawalRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        abort_unless($financeEntity->isBusiness(), 404);

        $family = $request->resolvedFamily();
        abort_unless($family instanceof FinanceEntity, 422);

        $this->withdrawals->create($financeEntity, $family, $request->payload());

        return redirect()
            ->route('admin.finance-entities.owner-withdrawals.index', $financeEntity)
            ->with('success', 'Prive dicatat.');
    }
}
