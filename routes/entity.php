<?php

use App\Http\Controllers\Entity\EntityAccountController;
use App\Http\Controllers\Entity\EntityBudgetController;
use App\Http\Controllers\Entity\EntityCapitalContributionController;
use App\Http\Controllers\Entity\EntityCategoryController;
use App\Http\Controllers\Entity\EntityDebtController;
use App\Http\Controllers\Entity\EntityIncomeController;
use App\Http\Controllers\Entity\EntityInsightController;
use App\Http\Controllers\Entity\EntityOperationalExpenseController;
use App\Http\Controllers\Entity\EntityOwnerWithdrawalController;
use App\Http\Controllers\Entity\EntityProfitDistributionController;
use App\Http\Controllers\Entity\EntityProfitLossController;
use App\Http\Controllers\Entity\EntityReceivableController;
use App\Http\Controllers\Entity\EntityRecurringTransactionController;
use App\Http\Controllers\Entity\EntityReportController;
use App\Http\Controllers\Entity\EntitySavingsGoalController;
use App\Http\Controllers\Entity\EntityTransactionController;
use App\Http\Controllers\Entity\EntityTransferController;
use App\Http\Controllers\EntityAccessController;
use App\Http\Controllers\EntityDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/access/{token}', [EntityAccessController::class, 'show'])
    ->middleware('throttle:30,1')
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('access.show');

Route::middleware('entity.access')->prefix('e/{financeEntity}')->name('entity.')->scopeBindings()->group(function () {
    Route::get('dashboard', [EntityDashboardController::class, 'show'])->name('dashboard');
    Route::get('reports', [EntityReportController::class, 'index'])->name('reports.index');
    Route::get('reports/excel', [EntityReportController::class, 'excel'])->name('reports.excel');
    Route::get('reports/pdf', [EntityReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('insight', [EntityInsightController::class, 'index'])->name('insight.index');
    Route::post('insight/summary', [EntityInsightController::class, 'summary'])->name('insight.summary');
    Route::post('ai/chat', [EntityInsightController::class, 'chat'])->name('ai.chat');

    Route::get('accounts', [EntityAccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [EntityAccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [EntityAccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}/edit', [EntityAccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [EntityAccountController::class, 'update'])->name('accounts.update');
    Route::post('accounts/{account}/activate', [EntityAccountController::class, 'activate'])->name('accounts.activate');
    Route::post('accounts/{account}/deactivate', [EntityAccountController::class, 'deactivate'])->name('accounts.deactivate');
    Route::post('accounts/{account}/set-default', [EntityAccountController::class, 'setDefault'])->name('accounts.set-default');
    Route::delete('accounts/{account}', [EntityAccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('transfers', [EntityTransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers/create', [EntityTransferController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [EntityTransferController::class, 'store'])->name('transfers.store');

    Route::resource('incomes', EntityIncomeController::class)->except(['show']);

    Route::get('capital-contributions', [EntityCapitalContributionController::class, 'index'])->name('capital-contributions.index');
    Route::get('owner-withdrawals', [EntityOwnerWithdrawalController::class, 'index'])->name('owner-withdrawals.index');
    Route::get('profit-distributions', [EntityProfitDistributionController::class, 'index'])->name('profit-distributions.index');

    Route::get('receivables', [EntityReceivableController::class, 'index'])->name('receivables.index');
    Route::get('receivables/create', [EntityReceivableController::class, 'create'])->name('receivables.create');
    Route::post('receivables', [EntityReceivableController::class, 'store'])->name('receivables.store');
    Route::get('receivables/{receivable}', [EntityReceivableController::class, 'show'])->name('receivables.show');
    Route::get('receivables/{receivable}/edit', [EntityReceivableController::class, 'edit'])->name('receivables.edit');
    Route::put('receivables/{receivable}', [EntityReceivableController::class, 'update'])->name('receivables.update');
    Route::post('receivables/{receivable}/payments', [EntityReceivableController::class, 'storePayment'])->name('receivables.payments.store');

    Route::resource('categories', EntityCategoryController::class)->except(['show']);
    Route::resource('recurring-transactions', EntityRecurringTransactionController::class)
        ->parameters(['recurring-transactions' => 'recurringTransaction'])
        ->names('recurring')
        ->except(['show']);

    Route::middleware('entity.type:FAMILY')->group(function () {
        Route::resource('transactions', EntityTransactionController::class)->except(['show']);
        Route::resource('debts', EntityDebtController::class);
        Route::post('debts/{debt}/payments', [EntityDebtController::class, 'storePayment'])->name('debts.payments.store');
        Route::resource('savings-goals', EntitySavingsGoalController::class)->parameters(['savings-goals' => 'savings_goal']);
        Route::post('savings-goals/{savings_goal}/contributions', [EntitySavingsGoalController::class, 'storeContribution'])
            ->name('savings-goals.contributions.store');
        Route::get('capital-contributions/create', [EntityCapitalContributionController::class, 'create'])->name('capital-contributions.create');
        Route::post('capital-contributions', [EntityCapitalContributionController::class, 'store'])->name('capital-contributions.store');
    });

    Route::middleware('entity.type:BUSINESS')->group(function () {
        Route::get('budgets/operating/{plantationOperatingBudget}/edit', [EntityBudgetController::class, 'editOperating'])
            ->name('budgets.operating.edit');
        Route::put('budgets/operating/{plantationOperatingBudget}', [EntityBudgetController::class, 'updateOperating'])
            ->name('budgets.operating.update');
        Route::post('budgets/operating/{plantationOperatingBudget}/sync', [EntityBudgetController::class, 'syncOperating'])
            ->name('budgets.operating.sync');
        Route::resource('budgets', EntityBudgetController::class);
        Route::post('budgets/{budget}/activities', [EntityBudgetController::class, 'storeActivity'])->name('budgets.activities.store');
        Route::get('operational-expenses', [EntityOperationalExpenseController::class, 'index'])->name('operational.index');
        Route::get('operational-expenses/create', [EntityOperationalExpenseController::class, 'create'])->name('operational.create');
        Route::post('operational-expenses', [EntityOperationalExpenseController::class, 'store'])->name('operational.store');
        Route::get('profit-loss', [EntityProfitLossController::class, 'index'])->name('profit-loss.index');
        Route::get('owner-withdrawals/create', [EntityOwnerWithdrawalController::class, 'create'])->name('owner-withdrawals.create');
        Route::post('owner-withdrawals', [EntityOwnerWithdrawalController::class, 'store'])->name('owner-withdrawals.store');
        Route::get('profit-distributions/create', [EntityProfitDistributionController::class, 'create'])->name('profit-distributions.create');
        Route::post('profit-distributions', [EntityProfitDistributionController::class, 'store'])->name('profit-distributions.store');
    });
});
