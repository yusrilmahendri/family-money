<?php

namespace App\Http\Controllers\Entity;

use App\Enums\FinanceAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceAccountRequest;
use App\Http\Requests\UpdateFinanceAccountRequest;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class EntityAccountController extends Controller
{
    public function __construct(
        private readonly FinanceAccountService $accounts,
        private readonly FinanceAccountBalanceService $balances,
    ) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $summary = $this->balances->summary($financeEntity);

        return view('entity.accounts.index', [
            'entity' => $financeEntity,
            'accounts' => $summary['accounts'],
            'totalSaldo' => $summary['total'],
            'title' => 'Kas & Rekening',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.accounts.create', [
            'entity' => $financeEntity,
            'types' => FinanceAccountType::cases(),
            'title' => 'Tambah Kas / Rekening',
        ]);
    }

    public function store(StoreFinanceAccountRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $this->accounts->create($financeEntity, $request->validated());

        return redirect()
            ->route('entity.accounts.index', $financeEntity)
            ->with('success', 'Kas / rekening disimpan.');
    }

    public function edit(FinanceEntity $financeEntity, FinanceAccount $account): View
    {
        $this->owned($financeEntity, $account);

        return view('entity.accounts.edit', [
            'entity' => $financeEntity,
            'account' => $account,
            'types' => FinanceAccountType::cases(),
            'title' => 'Edit Kas / Rekening',
        ]);
    }

    public function update(
        UpdateFinanceAccountRequest $request,
        FinanceEntity $financeEntity,
        FinanceAccount $account
    ): RedirectResponse {
        $this->owned($financeEntity, $account);

        try {
            $this->accounts->update($account, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('entity.accounts.index', $financeEntity)
                ->with('danger', $exception->getMessage());
        }

        return redirect()
            ->route('entity.accounts.index', $financeEntity)
            ->with('success', 'Kas / rekening diperbarui.');
    }

    public function activate(FinanceEntity $financeEntity, FinanceAccount $account): RedirectResponse
    {
        $this->owned($financeEntity, $account);
        $this->accounts->activate($account);

        return redirect()
            ->route('entity.accounts.index', $financeEntity)
            ->with('success', 'Account diaktifkan.');
    }

    public function deactivate(FinanceEntity $financeEntity, FinanceAccount $account): RedirectResponse
    {
        $this->owned($financeEntity, $account);
        $this->accounts->deactivate($account);

        return redirect()
            ->route('entity.accounts.index', $financeEntity)
            ->with('success', 'Account dinonaktifkan.');
    }

    public function setDefault(FinanceEntity $financeEntity, FinanceAccount $account): RedirectResponse
    {
        $this->owned($financeEntity, $account);

        try {
            $this->accounts->setDefault($account);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('entity.accounts.index', $financeEntity)
                ->with('danger', $exception->getMessage());
        }

        return redirect()
            ->route('entity.accounts.index', $financeEntity)
            ->with('success', 'Account default diperbarui.');
    }

    private function owned(FinanceEntity $entity, FinanceAccount $account): void
    {
        abort_unless((int) $account->finance_entity_id === (int) $entity->id, 404);
    }
}
