<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceEntityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfitDistributionRequest;
use App\Models\FinanceEntity;
use App\Services\BusinessProfitService;
use App\Services\ProfitDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfitDistributionController extends Controller
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

        return view('admin.profit-distributions.index', [
            'title' => $financeEntity->isBusiness() ? 'Pembagian Laba' : 'Profit Distribution Received',
            'entity' => $financeEntity,
            'distributions' => $distributions
                ->with(['businessEntity', 'familyEntity', 'sourceAccount', 'destinationAccount'])
                ->latest('distribution_date')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        abort_unless($financeEntity->isBusiness(), 404);

        [$from, $to] = $this->profits->currentMonthBounds();

        return view('admin.profit-distributions.create', [
            'title' => 'Bagi Laba',
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'families' => FinanceEntity::query()
                ->where('type', FinanceEntityType::FAMILY)
                ->where('is_active', true)
                ->whereHas('accounts', fn ($query) => $query->where('is_active', true))
                ->with('activeAccounts')
                ->orderBy('name')
                ->get(),
            'availability' => $this->distributions->availability($financeEntity, $from, $to),
        ]);
    }

    public function store(StoreProfitDistributionRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        abort_unless($financeEntity->isBusiness(), 404);

        $family = $request->resolvedFamily();
        abort_unless($family instanceof FinanceEntity, 422);

        $this->distributions->create($financeEntity, $family, $request->payload());

        return redirect()
            ->route('admin.finance-entities.profit-distributions.index', $financeEntity)
            ->with('success', 'Pembagian laba dicatat.');
    }
}
