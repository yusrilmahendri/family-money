<?php

namespace App\Http\Controllers;

use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class EntityAccessController extends Controller
{
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

        return redirect()
            ->route('entity.dashboard', $accessToken->financeEntity)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
