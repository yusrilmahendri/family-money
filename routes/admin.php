<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\BusinessCapitalContributionController;
use App\Http\Controllers\Admin\FinanceAccountController;
use App\Http\Controllers\Admin\FinanceEntityAccessTokenController;
use App\Http\Controllers\Admin\FinanceEntityController;
use App\Http\Controllers\Admin\FinanceTransferController;
use App\Http\Controllers\Admin\OwnerWithdrawalController;
use App\Http\Controllers\Admin\PlantationAccessLinkController;
use App\Http\Controllers\Admin\PlantationIntegrationController;
use App\Http\Controllers\Admin\PlantationOperatingBudgetController;
use App\Http\Controllers\Admin\PortalAccessController;
use App\Http\Controllers\Admin\ProfitDistributionController;
use App\Http\Controllers\Admin\ReceivableController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('login', [AdminLoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::redirect('dashboard', '/admin');
        Route::post('logout', [AdminLoginController::class, 'destroy'])->name('logout');
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AdminAuditLogController::class, 'show'])->name('audit-logs.show');

        Route::get('portal-access', [PortalAccessController::class, 'index'])->name('portal-access.index');
        Route::post('portal-access', [PortalAccessController::class, 'store'])->name('portal-access.store');
        Route::get('portal-access/{portalAccessToken}/edit', [PortalAccessController::class, 'edit'])->name('portal-access.edit');
        Route::put('portal-access/{portalAccessToken}', [PortalAccessController::class, 'update'])->name('portal-access.update');
        Route::post('portal-access/{portalAccessToken}/revoke', [PortalAccessController::class, 'revoke'])->name('portal-access.revoke');
        Route::post('portal-access/{portalAccessToken}/activate', [PortalAccessController::class, 'activate'])->name('portal-access.activate');
        Route::post('portal-access/{portalAccessToken}/regenerate', [PortalAccessController::class, 'regenerate'])->name('portal-access.regenerate');
        Route::delete('portal-access/{portalAccessToken}', [PortalAccessController::class, 'destroy'])->name('portal-access.destroy');

        Route::get('plantation-integrations', [PlantationIntegrationController::class, 'index'])->name('plantation-integrations.index');
        Route::get('plantation-integrations/{financeEntity}', [PlantationIntegrationController::class, 'show'])->name('plantation-integrations.show');
        Route::post('plantation-integrations/{financeEntity}/activate', [PlantationIntegrationController::class, 'activate'])->name('plantation-integrations.activate');
        Route::post('plantation-integrations/{financeEntity}/sync', [PlantationIntegrationController::class, 'sync'])->name('plantation-integrations.sync');
        Route::post('plantation-integrations/{financeEntity}/sync-harvest-receivables', [PlantationIntegrationController::class, 'syncHarvestReceivables'])->name('plantation-integrations.sync-harvest-receivables');
        Route::post('plantation-integrations/{financeEntity}/deactivate', [PlantationIntegrationController::class, 'deactivate'])->name('plantation-integrations.deactivate');
        Route::post('plantation-integrations/{financeEntity}/reactivate', [PlantationIntegrationController::class, 'reactivate'])->name('plantation-integrations.reactivate');
        Route::get('plantation-integrations/{financeEntity}/access-links', [PlantationAccessLinkController::class, 'index'])->name('plantation-integrations.access-links.index');
        Route::post('plantation-integrations/{financeEntity}/access-links', [PlantationAccessLinkController::class, 'store'])->name('plantation-integrations.access-links.store');
        Route::post('plantation-integrations/{financeEntity}/access-links/{tokenId}/revoke', [PlantationAccessLinkController::class, 'revoke'])
            ->whereNumber('tokenId')
            ->name('plantation-integrations.access-links.revoke');
        Route::post('plantation-integrations/{financeEntity}/access-links/{tokenId}/activate', [PlantationAccessLinkController::class, 'activate'])
            ->whereNumber('tokenId')
            ->name('plantation-integrations.access-links.activate');
        Route::post('plantation-integrations/{financeEntity}/access-links/{tokenId}/regenerate', [PlantationAccessLinkController::class, 'regenerate'])
            ->whereNumber('tokenId')
            ->name('plantation-integrations.access-links.regenerate');
        Route::delete('plantation-integrations/{financeEntity}/access-links/{tokenId}', [PlantationAccessLinkController::class, 'destroy'])
            ->whereNumber('tokenId')
            ->name('plantation-integrations.access-links.destroy');

        Route::get('plantation-integrations/{financeEntity}/operating-budgets', [PlantationOperatingBudgetController::class, 'index'])
            ->name('plantation-integrations.operating-budgets.index');
        Route::post('plantation-integrations/{financeEntity}/operating-budgets/{operatingBudget}/sync', [PlantationOperatingBudgetController::class, 'sync'])
            ->name('plantation-integrations.operating-budgets.sync');

        Route::get('finance-entities', [FinanceEntityController::class, 'index'])->name('finance-entities.index');
        Route::get('finance-entities/create', [FinanceEntityController::class, 'create'])->name('finance-entities.create');
        Route::post('finance-entities', [FinanceEntityController::class, 'store'])->name('finance-entities.store');
        Route::get('finance-entities/{financeEntity}/edit', [FinanceEntityController::class, 'edit'])->name('finance-entities.edit');
        Route::put('finance-entities/{financeEntity}', [FinanceEntityController::class, 'update'])->name('finance-entities.update');
        Route::delete('finance-entities/{financeEntity}', [FinanceEntityController::class, 'destroy'])->name('finance-entities.destroy');
        Route::post('finance-entities/{financeEntity}/activate', [FinanceEntityController::class, 'activate'])->name('finance-entities.activate');
        Route::post('finance-entities/{financeEntity}/deactivate', [FinanceEntityController::class, 'deactivate'])->name('finance-entities.deactivate');

        Route::get('finance-entities/{financeEntity}/access-links', [FinanceEntityAccessTokenController::class, 'index'])->name('finance-entities.access-links.index');
        Route::post('finance-entities/{financeEntity}/access-links', [FinanceEntityAccessTokenController::class, 'store'])->name('finance-entities.access-links.store');
        Route::get('finance-entities/{financeEntity}/access-links/{accessToken}/edit', [FinanceEntityAccessTokenController::class, 'edit'])->name('finance-entities.access-links.edit');
        Route::put('finance-entities/{financeEntity}/access-links/{accessToken}', [FinanceEntityAccessTokenController::class, 'update'])->name('finance-entities.access-links.update');
        Route::post('finance-entities/{financeEntity}/access-links/{accessToken}/revoke', [FinanceEntityAccessTokenController::class, 'revoke'])->name('finance-entities.access-links.revoke');
        Route::post('finance-entities/{financeEntity}/access-links/{accessToken}/activate', [FinanceEntityAccessTokenController::class, 'activate'])->name('finance-entities.access-links.activate');
        Route::post('finance-entities/{financeEntity}/access-links/{accessToken}/regenerate', [FinanceEntityAccessTokenController::class, 'regenerate'])->name('finance-entities.access-links.regenerate');
        Route::delete('finance-entities/{financeEntity}/access-links/{accessToken}', [FinanceEntityAccessTokenController::class, 'destroy'])
            ->scopeBindings()
            ->name('finance-entities.access-links.destroy');

        Route::prefix('finance-entities/{financeEntity}/accounts')->name('finance-entities.accounts.')->scopeBindings()->group(function () {
            Route::get('/', [FinanceAccountController::class, 'index'])->name('index');
            Route::get('create', [FinanceAccountController::class, 'create'])->name('create');
            Route::post('/', [FinanceAccountController::class, 'store'])->name('store');
            Route::get('{account}/edit', [FinanceAccountController::class, 'edit'])->name('edit');
            Route::put('{account}', [FinanceAccountController::class, 'update'])->name('update');
            Route::post('{account}/activate', [FinanceAccountController::class, 'activate'])->name('activate');
            Route::post('{account}/deactivate', [FinanceAccountController::class, 'deactivate'])->name('deactivate');
            Route::post('{account}/set-default', [FinanceAccountController::class, 'setDefault'])->name('set-default');
        });

        Route::prefix('finance-entities/{financeEntity}/transfers')->name('finance-entities.transfers.')->scopeBindings()->group(function () {
            Route::get('/', [FinanceTransferController::class, 'index'])->name('index');
            Route::get('create', [FinanceTransferController::class, 'create'])->name('create');
            Route::post('/', [FinanceTransferController::class, 'store'])->name('store');
        });

        Route::prefix('finance-entities/{financeEntity}/capital-contributions')->name('finance-entities.capital-contributions.')->scopeBindings()->group(function () {
            Route::get('/', [BusinessCapitalContributionController::class, 'index'])->name('index');
            Route::get('create', [BusinessCapitalContributionController::class, 'create'])->name('create');
            Route::post('/', [BusinessCapitalContributionController::class, 'store'])->name('store');
        });

        Route::prefix('finance-entities/{financeEntity}/owner-withdrawals')->name('finance-entities.owner-withdrawals.')->scopeBindings()->group(function () {
            Route::get('/', [OwnerWithdrawalController::class, 'index'])->name('index');
            Route::get('create', [OwnerWithdrawalController::class, 'create'])->name('create');
            Route::post('/', [OwnerWithdrawalController::class, 'store'])->name('store');
        });

        Route::prefix('finance-entities/{financeEntity}/profit-distributions')->name('finance-entities.profit-distributions.')->scopeBindings()->group(function () {
            Route::get('/', [ProfitDistributionController::class, 'index'])->name('index');
            Route::get('create', [ProfitDistributionController::class, 'create'])->name('create');
            Route::post('/', [ProfitDistributionController::class, 'store'])->name('store');
        });

        Route::prefix('finance-entities/{financeEntity}/receivables')->name('finance-entities.receivables.')->scopeBindings()->group(function () {
            Route::get('/', [ReceivableController::class, 'index'])->name('index');
            Route::get('create', [ReceivableController::class, 'create'])->name('create');
            Route::post('/', [ReceivableController::class, 'store'])->name('store');
            Route::get('{receivable}', [ReceivableController::class, 'show'])->name('show');
            Route::get('{receivable}/edit', [ReceivableController::class, 'edit'])->name('edit');
            Route::put('{receivable}', [ReceivableController::class, 'update'])->name('update');
            Route::post('{receivable}/payments', [ReceivableController::class, 'storePayment'])->name('payments.store');
        });
    });
});
