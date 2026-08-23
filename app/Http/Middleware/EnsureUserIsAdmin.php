<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects /admin/* after authentication.
 *
 * This is NOT the private-link / entity-access gate (Task 3).
 * Family and business users will not use this middleware.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->isAdmin()) {
            abort(403, 'Akses Admin Panel ditolak.');
        }

        return $next($request);
    }
}
