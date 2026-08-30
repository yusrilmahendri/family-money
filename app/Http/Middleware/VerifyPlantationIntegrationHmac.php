<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPlantationIntegrationHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.plantation.hmac_secret');
        if ($secret === '') {
            return $next($request);
        }

        $timestamp = (string) $request->header('X-Integration-Timestamp', '');
        $signature = (string) $request->header('X-Integration-Signature', '');

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
