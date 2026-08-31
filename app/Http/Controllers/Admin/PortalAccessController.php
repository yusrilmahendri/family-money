<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePortalAccessRequest;
use App\Http\Requests\Admin\UpdatePortalAccessRequest;
use App\Models\PortalAccessToken;
use App\Services\PortalAccessTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PortalAccessController extends Controller
{
    public function __construct(private readonly PortalAccessTokenService $portalAccess) {}

    public function index(): View
    {
        $links = PortalAccessToken::query()
            ->with(['grants.financeEntity'])
            ->latest()
            ->get();

        return view('admin.portal-access.index', [
            'title' => 'Portal Access',
            'links' => $links,
            'resources' => $this->portalAccess->availableResources(),
        ]);
    }

    public function store(StorePortalAccessRequest $request): View|RedirectResponse
    {
        try {
            [$token, $plain] = $this->portalAccess->issue(
                $request->validated('name'),
                $request->validated('grants'),
                $request->filled('expires_at') ? $request->date('expires_at') : null
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.portal-access.index')
                ->withInput()
                ->withErrors(['grants' => $e->getMessage()]);
        }

        return view('admin.portal-access.created', [
            'title' => 'Portal Access Created',
            'accessToken' => $token,
            'plainToken' => $plain,
            'accessUrl' => url('/access/'.$plain),
        ]);
    }

    public function edit(PortalAccessToken $portalAccessToken): View
    {
        $portalAccessToken->load(['grants.financeEntity']);

        return view('admin.portal-access.edit', [
            'title' => 'Edit Portal Access',
            'accessToken' => $portalAccessToken,
            'resources' => $this->portalAccess->availableResources(),
        ]);
    }

    public function update(UpdatePortalAccessRequest $request, PortalAccessToken $portalAccessToken): RedirectResponse
    {
        try {
            $this->portalAccess->update($portalAccessToken, [
                'name' => $request->validated('name'),
                'expires_at' => $request->filled('expires_at') ? $request->date('expires_at') : null,
                'grants' => $request->validated('grants'),
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.portal-access.edit', $portalAccessToken)
                ->withInput()
                ->withErrors(['grants' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.portal-access.index')
            ->with('success', 'Portal access diperbarui.');
    }

    public function revoke(PortalAccessToken $portalAccessToken): RedirectResponse
    {
        $this->portalAccess->revoke($portalAccessToken);

        return redirect()
            ->route('admin.portal-access.index')
            ->with('success', 'Portal access direvoke.');
    }

    public function activate(PortalAccessToken $portalAccessToken): RedirectResponse
    {
        $this->portalAccess->activate($portalAccessToken);

        return redirect()
            ->route('admin.portal-access.index')
            ->with('success', 'Portal access diaktifkan kembali.');
    }

    public function regenerate(PortalAccessToken $portalAccessToken): View
    {
        [$token, $plain] = $this->portalAccess->regenerate($portalAccessToken);

        return view('admin.portal-access.created', [
            'title' => 'Portal Access Regenerated',
            'accessToken' => $token,
            'plainToken' => $plain,
            'accessUrl' => url('/access/'.$plain),
        ]);
    }

    public function destroy(PortalAccessToken $portalAccessToken): RedirectResponse
    {
        $this->portalAccess->delete($portalAccessToken);

        return redirect()
            ->route('admin.portal-access.index')
            ->with('success', 'Portal access dihapus permanen.');
    }
}
