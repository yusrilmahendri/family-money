<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfitDistributionRequest;
use App\Models\FinanceEntity;
use App\Services\BusinessProfitService;
use App\Services\ProfitDistributionService;
use App\Support\FinanceEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntityProfitDistributionController extends Controller
{
    public function __construct(
        private readonly ProfitDistributionService $distributions,
        private readonly BusinessProfitService $profits,
    ) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $distributions = $financeEntity->isBusiness()
            ? $financeEntity->profitDistributionsGiven()
            : $financeEntity->profitDistributionsReceived();

        return view('entity.profit-distributions.index', [
            'entity' => $financeEntity,
            'distributions' => $distributions
                ->with(['businessEntity', 'familyEntity', 'sourceAccount', 'destinationAccount'])
                ->latest('distribution_date')
                ->latest('id')
                ->paginate(20),
            'title' => $financeEntity->isBusiness() ? 'Pembagian Laba' : 'Profit Distribution Received',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        [$from, $to] = $this->profits->currentMonthBounds();

        return view('entity.profit-distributions.create', [
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'families' => FinanceEntityAccess::distributionDestinations()->load('activeAccounts'),
            'availability' => $this->distributions->availability($financeEntity, $from, $to),
            'title' => 'Bagi Laba',
        ]);
    }

    public function store(StoreProfitDistributionRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $family = $request->resolvedFamily();
        abort_unless($family instanceof FinanceEntity, 422);

        $this->distributions->create($financeEntity, $family, $request->payload());

        return redirect()
            ->route('entity.profit-distributions.index', $financeEntity)
            ->with('success', 'Pembagian laba dicatat.');
    }
}
