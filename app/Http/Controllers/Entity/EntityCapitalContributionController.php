<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessCapitalContributionRequest;
use App\Models\FinanceEntity;
use App\Services\BusinessCapitalContributionService;
use App\Support\FinanceEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntityCapitalContributionController extends Controller
{
    public function __construct(private readonly BusinessCapitalContributionService $contributions) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $contributions = $financeEntity->isFamily()
            ? $financeEntity->capitalContributionsGiven()
            : $financeEntity->capitalContributionsReceived();

        return view('entity.capital-contributions.index', [
            'entity' => $financeEntity,
            'contributions' => $contributions
                ->with(['sourceEntity', 'businessEntity', 'sourceAccount', 'destinationAccount'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
            'title' => $financeEntity->isFamily() ? 'Modal ke Usaha' : 'Modal Masuk',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        $businesses = FinanceEntityAccess::capitalDestinations()
            ->load('activeAccounts');

        return view('entity.capital-contributions.create', [
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'businesses' => $businesses,
            'title' => 'Tambah Modal Usaha',
        ]);
    }

    public function store(StoreBusinessCapitalContributionRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $business = $request->resolvedBusiness();
        abort_unless($business instanceof FinanceEntity, 422);

        $this->contributions->create(
            $financeEntity,
            $business,
            $request->payload()
        );

        return redirect()
            ->route('entity.capital-contributions.index', $financeEntity)
            ->with('success', 'Modal usaha dicatat.');
    }
}
