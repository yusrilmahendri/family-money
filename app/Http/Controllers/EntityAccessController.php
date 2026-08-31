<?php

namespace App\Http\Controllers;

use App\Services\ApplicationPortalService;
use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class EntityAccessController extends Controller
{
    public function __construct(
        private readonly ApplicationPortalService $portal,
    ) {}

    public function show(string $token, FinanceEntityAccessTokenService $tokens): RedirectResponse|Response
    {
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
