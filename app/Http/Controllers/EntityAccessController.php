<?php

namespace App\Http\Controllers;

use App\Services\ApplicationPortalService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\PortalAccessTokenService;
use App\Support\FinanceEntityAccess;
use App\Support\PortalAccessSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class EntityAccessController extends Controller
{
    public function __construct(
        private readonly ApplicationPortalService $portal,
        private readonly PortalAccessTokenService $portalAccess,
    ) {}

    public function show(string $token, FinanceEntityAccessTokenService $tokens): RedirectResponse|Response
    {
        $portalToken = $this->portalAccess->findUsableByPlainToken($token);

        if ($portalToken !== null) {
            $this->portalAccess->markUsed($portalToken);
            PortalAccessSession::grant($portalToken);

            return redirect()
                ->route('home')
                ->header('Referrer-Policy', 'no-referrer');
        }

        $accessToken = $tokens->findUsableByPlainToken($token);

        if ($accessToken === null) {
            return response()->view('entity.access-invalid', [
                'title' => 'Akses tidak valid',
            ], 404);
        }

        $tokens->markUsed($accessToken);
        FinanceEntityAccess::grant($accessToken->financeEntity, $accessToken);

        $destinations = $this->portal->destinations();

        if ($destinations->count() === 1) {
            $card = $destinations->first();

            if (is_array($card) && ($card['method'] ?? 'GET') === 'GET' && is_string($card['target_url'] ?? null)) {
                return redirect()
                    ->to($card['target_url'])
                    ->header('Referrer-Policy', 'no-referrer');
            }
        }

        return redirect()
            ->route('home')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
