<?php

use App\Http\Controllers\ApplicationPortalController;
use App\Http\Controllers\LegacyRetiredController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ApplicationPortalController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('home');

Route::redirect('/portal', '/', 302);

$retired = LegacyRetiredController::class;

Route::get('/apps', $retired)->name('apps.index');
Route::match(['GET', 'POST'], '/apps/select', $retired)->name('apps.select');
Route::get('/dashboard', $retired)->name('dashboard');
Route::get('/dashboard/export/excel', $retired)->name('dashboard.export.excel');
Route::get('/dashboard/export/pdf', $retired)->name('dashboard.export.pdf');
Route::get('/insight', $retired)->name('insight.index');
Route::post('/insight/summary', $retired)->name('insight.summary');
Route::post('/insight/explain-anomalies', $retired)->name('insight.explain_anomalies');
Route::get('/financial-planner', $retired)->name('financial-planner.index');
Route::get('/laba-rugi', $retired)->name('profit-loss.index');

Route::match(['GET', 'HEAD'], '/incomes', $retired)->name('incomes.index');
Route::post('/incomes', $retired)->name('incomes.store');
Route::any('/incomes/{income}', $retired);

Route::match(['GET', 'HEAD'], '/transactions', $retired)->name('transactions.index');
Route::post('/transactions', $retired)->name('transactions.store');
Route::get('/transactions/export/excel', $retired)->name('transactions.export.excel');
Route::get('/transactions/export/pdf', $retired)->name('transactions.export.pdf');
Route::any('/transactions/{transaction}', $retired);

Route::match(['GET', 'HEAD'], '/saldos', $retired)->name('saldos.index');
Route::post('/saldos', $retired)->name('saldos.store');
Route::get('/saldos/export/excel', $retired)->name('saldos.export.excel');
Route::get('/saldos/export/pdf', $retired)->name('saldos.export.pdf');
Route::any('/saldos/{saldo}', $retired);

Route::any('/categories/{category?}', $retired)->name('categories.index');
Route::any('/budgets/{budget?}', $retired)->name('budgets.index');
Route::any('/debts/{debt?}', $retired)->name('debts.index');
Route::any('/savings-goals/{savings_goal?}', $retired)->name('savings-goals.index');
Route::any('/operational-expenses/{operational?}', $retired)->name('operational.index');
Route::any('/recurring-transactions/{recurring?}', $retired)->name('recurring.index');

Route::any('/api/v1/{any}', $retired)->where('any', '.*');
Route::any('/api/dashboard/{any}', $retired)->where('any', '.*');

require __DIR__.'/admin.php';
require __DIR__.'/entity.php';
