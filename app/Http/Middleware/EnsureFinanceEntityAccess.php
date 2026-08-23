<?php

namespace App\Http\Middleware;

use App\Models\FinanceEntity;
use App\Support\FinanceEntityAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a live private-link capability for the bound FinanceEntity.
 *
 * public_id in the URL is only an identifier. Authorization comes from
 * session capability + a still-valid token record.
 */
class EnsureFinanceEntityAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $entity = $request->route('financeEntity');

        if (! $entity instanceof FinanceEntity || ! FinanceEntityAccess::isAuthorized($entity)) {
            return response()->view('entity.access-invalid', [
                'title' => 'Akses tidak valid',
            ], 404);
        }

        return $next($request);
    }
}
