<?php

use App\Http\Controllers\Internal\HarvestFinanceEventController;
use App\Http\Controllers\Internal\PlantationIntegrationEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')
    ->middleware(['internal.plantation', 'internal.plantation.hmac'])
    ->group(function () {
        Route::post('plantation/events', [PlantationIntegrationEventController::class, 'store'])
            ->name('internal.plantation-events.store');
        Route::post('plantation-harvest-events', [HarvestFinanceEventController::class, 'store'])
            ->name('internal.plantation-harvest-events.store');
    });
