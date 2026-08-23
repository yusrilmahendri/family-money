<?php

namespace App\Http\Middleware;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceEntityType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $entity = $request->route('financeEntity');
        $expected = FinanceEntityType::tryFrom(strtoupper($type));

        if (! $entity instanceof FinanceEntity || $expected === null || $entity->type !== $expected) {
            return response()->view('entity.access-invalid', [
                'title' => 'Akses tidak valid',
            ], 404);
        }

        return $next($request);
    }
}
