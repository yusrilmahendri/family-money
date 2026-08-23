<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinanceEntityAccessTokenRequest;
use App\Http\Requests\Admin\UpdateFinanceEntityAccessTokenRequest;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceEntityAccessTokenController extends Controller
{
    public function __construct(private readonly FinanceEntityAccessTokenService $tokens) {}

    public function index(FinanceEntity $financeEntity): View
    {
        $links = $financeEntity->accessTokens()
            ->latest()
            ->get();

        return view('admin.access-links.index', [
            'title' => 'Access Links',
            'entity' => $financeEntity,
            'links' => $links,
        ]);
    }

    public function store(StoreFinanceEntityAccessTokenRequest $request, FinanceEntity $financeEntity): View
    {
        [$token, $plain] = $this->tokens->issue(
            $financeEntity,
            $request->validated('label'),
            $request->filled('expires_at') ? $request->date('expires_at') : null
        );

        return view('admin.access-links.created', [
            'title' => 'Private Link Created',
            'entity' => $financeEntity,
            'accessToken' => $token,
            'plainToken' => $plain,
            'accessUrl' => url('/access/'.$plain),
        ]);
    }

    public function edit(FinanceEntity $financeEntity, FinanceEntityAccessToken $accessToken): View
    {
        $this->ensureBelongsToEntity($financeEntity, $accessToken);

        return view('admin.access-links.edit', [
            'title' => 'Edit Access Link',
            'entity' => $financeEntity,
            'accessToken' => $accessToken,
        ]);
    }

    public function update(
        UpdateFinanceEntityAccessTokenRequest $request,
        FinanceEntity $financeEntity,
        FinanceEntityAccessToken $accessToken
    ): RedirectResponse {
        $this->ensureBelongsToEntity($financeEntity, $accessToken);

        $this->tokens->updateMeta($accessToken, $request->validated());

        return redirect()
            ->route('admin.finance-entities.access-links.index', $financeEntity)
            ->with('success', 'Access link diperbarui.');
    }

    public function revoke(FinanceEntity $financeEntity, FinanceEntityAccessToken $accessToken): RedirectResponse
    {
        $this->ensureBelongsToEntity($financeEntity, $accessToken);

        $this->tokens->revoke($accessToken);

        return redirect()
            ->route('admin.finance-entities.access-links.index', $financeEntity)
            ->with('success', 'Access link direvoke.');
    }

    public function activate(FinanceEntity $financeEntity, FinanceEntityAccessToken $accessToken): RedirectResponse
    {
        $this->ensureBelongsToEntity($financeEntity, $accessToken);

        $this->tokens->activate($accessToken);

        return redirect()
            ->route('admin.finance-entities.access-links.index', $financeEntity)
            ->with('success', 'Access link diaktifkan kembali.');
    }

    public function regenerate(FinanceEntity $financeEntity, FinanceEntityAccessToken $accessToken): View
    {
        $this->ensureBelongsToEntity($financeEntity, $accessToken);

        [$token, $plain] = $this->tokens->regenerate($accessToken);

        return view('admin.access-links.created', [
            'title' => 'Private Link Regenerated',
            'entity' => $financeEntity,
            'accessToken' => $token,
            'plainToken' => $plain,
            'accessUrl' => url('/access/'.$plain),
        ]);
    }

    private function ensureBelongsToEntity(FinanceEntity $entity, FinanceEntityAccessToken $token): void
    {
        abort_unless((int) $token->finance_entity_id === (int) $entity->id, 404);
    }
}
