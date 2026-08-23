<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceAccountRequest;
use App\Http\Requests\UpdateFinanceAccountRequest;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Services\FinanceAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class FinanceAccountController extends Controller
{
    public function __construct(private readonly FinanceAccountService $accounts) {}

    public function index(FinanceEntity $financeEntity): View
    {
        return view('admin.accounts.index', [
            'title' => 'Kas & Rekening',
            'entity' => $financeEntity,
            'accounts' => $financeEntity->accounts()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('admin.accounts.create', [
            'title' => 'Tambah Account',
            'entity' => $financeEntity,
            'types' => FinanceAccountType::cases(),
        ]);
    }

    public function store(StoreFinanceAccountRequest $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $this->accounts->create($financeEntity, $request->validated());

        return redirect()
            ->route('admin.finance-entities.accounts.index', $financeEntity)
            ->with('success', 'Account disimpan.');
    }

    public function edit(FinanceEntity $financeEntity, FinanceAccount $account): View
    {
        $this->owned($financeEntity, $account);

        return view('admin.accounts.edit', [
            'title' => 'Edit Account',
            'entity' => $financeEntity,
            'account' => $account,
            'types' => FinanceAccountType::cases(),
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
                ->route('admin.finance-entities.accounts.index', $financeEntity)
                ->with('danger', $exception->getMessage());
        }

        return redirect()
            ->route('admin.finance-entities.accounts.index', $financeEntity)
            ->with('success', 'Account diperbarui.');
    }

    public function activate(FinanceEntity $financeEntity, FinanceAccount $account): RedirectResponse
    {
        $this->owned($financeEntity, $account);
        $this->accounts->activate($account);

        return redirect()
            ->route('admin.finance-entities.accounts.index', $financeEntity)
            ->with('success', 'Account diaktifkan.');
    }

    public function deactivate(FinanceEntity $financeEntity, FinanceAccount $account): RedirectResponse
    {
        $this->owned($financeEntity, $account);
        $this->accounts->deactivate($account);

        return redirect()
            ->route('admin.finance-entities.accounts.index', $financeEntity)
            ->with('success', 'Account dinonaktifkan.');
    }

    public function setDefault(FinanceEntity $financeEntity, FinanceAccount $account): RedirectResponse
    {
        $this->owned($financeEntity, $account);

        try {
            $this->accounts->setDefault($account);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.finance-entities.accounts.index', $financeEntity)
                ->with('danger', $exception->getMessage());
        }

        return redirect()
            ->route('admin.finance-entities.accounts.index', $financeEntity)
            ->with('success', 'Account default diperbarui.');
    }

    private function owned(FinanceEntity $entity, FinanceAccount $account): void
    {
        abort_unless((int) $account->finance_entity_id === (int) $entity->id, 404);
    }
}
