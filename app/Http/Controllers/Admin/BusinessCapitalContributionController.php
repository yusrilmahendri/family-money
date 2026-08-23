<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceEntityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessCapitalContributionRequest;
use App\Models\FinanceEntity;
use App\Services\BusinessCapitalContributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessCapitalContributionController extends Controller
{
    public function __construct(private readonly BusinessCapitalContributionService $contributions) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $contributions = $financeEntity->isFamily()
            ? $financeEntity->capitalContributionsGiven()
            : $financeEntity->capitalContributionsReceived();

        return view('admin.capital-contributions.index', [
            'title' => $financeEntity->isFamily() ? 'Modal ke Usaha' : 'Modal Masuk',
            'entity' => $financeEntity,
            'contributions' => $contributions
                ->with(['sourceEntity', 'businessEntity', 'sourceAccount', 'destinationAccount'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        abort_unless($financeEntity->isFamily(), 404);

        return view('admin.capital-contributions.create', [
            'title' => 'Tambah Modal Usaha',
            'entity' => $financeEntity,
            'accounts' => $financeEntity->activeAccounts()->get(),
            'businesses' => FinanceEntity::query()
                ->where('type', FinanceEntityType::BUSINESS)
                ->where('is_active', true)
                ->whereHas('accounts', fn ($query) => $query->where('is_active', true))
                ->with('activeAccounts')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreBusinessCapitalContributionRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        abort_unless($financeEntity->isFamily(), 404);

        $business = $request->resolvedBusiness();
        abort_unless($business instanceof FinanceEntity, 422);

        $this->contributions->create(
            $financeEntity,
            $business,
            $request->payload()
        );

        return redirect()
            ->route('admin.finance-entities.capital-contributions.index', $financeEntity)
            ->with('success', 'Modal usaha dicatat.');
    }
}
