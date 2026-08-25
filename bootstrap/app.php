<?php

// Composer "files" autoload is not regenerated on production git pull.
// Load helpers here so rupiah() exists even with a stale autoload_files.php.
require_once __DIR__.'/../app/helpers.php';

use App\Http\Middleware\EnsureFinanceEntityAccess;
use App\Http\Middleware\EnsureFinanceEntityType;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForgetSensitiveAccessUrl;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('finance:recurring-run')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            ForgetSensitiveAccessUrl::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'entity.access' => EnsureFinanceEntityAccess::class,
            'entity.type' => EnsureFinanceEntityType::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
