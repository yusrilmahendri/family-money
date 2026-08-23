<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private grant URLs include the plaintext token. Laravel stores the current
 * GET URL as the session previous URL after the request. Strip that so the
 * token is not persisted in the session store or access logs of later pages.
 */
class ForgetSensitiveAccessUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasSession()) {
            return $response;
        }

        $session = $request->session();
        $previous = $session->previousUrl();

        if (is_string($previous) && $this->containsAccessToken($previous)) {
            $session->setPreviousUrl(url('/'));
        }

        $intended = $session->get('url.intended');

        if (is_string($intended) && $this->containsAccessToken($intended)) {
            $session->forget('url.intended');
        }

        return $response;
    }

    private function containsAccessToken(string $url): bool
    {
        return (bool) preg_match('#/access/[0-9a-fA-F]{64}#', $url);
    }
}
