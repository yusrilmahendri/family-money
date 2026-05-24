<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\FinancialPlannerController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\OperationalExpenseController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\SaldoController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

// API Routes
Route::get('/api/v1/saldos', [SaldoController::class, 'data'])->name('saldos.data');
Route::get('/api/v1/saldos/category/{id}', [SaldoController::class, 'getByCategoryId'])->name('saldos.byCategory');
Route::get('/api/v1/saldos/filter', [SaldoController::class, 'getFilteredSaldo'])->name('saldos.filter');
Route::get('/api/v1/transactions', [TransactionsController::class, 'data'])->name('transactions.data');
Route::get('/api/v1/categories', [CategoryController::class, 'data'])->name('categories.data');
Route::get('/api/dashboard/summary', [DashboardController::class, 'filterSummary']);
Route::get('/api/v1/budgets', [BudgetController::class, 'data'])->name('budgets.data');
Route::get('/api/v1/budgets/category-info/{category}', [BudgetController::class, 'categoryInfo'])->name('budgets.category_info');

// Export Routes
Route::get('/dashboard/export/excel', [DashboardController::class, 'exportExcel'])->name('dashboard.export.excel');
Route::get('/dashboard/export/pdf', [DashboardController::class, 'exportPdf'])->name('dashboard.export.pdf');
Route::get('/saldos/export/excel', [SaldoController::class, 'exportExcel'])->name('saldos.export.excel');
Route::get('/saldos/export/pdf', [SaldoController::class, 'exportPdf'])->name('saldos.export.pdf');
Route::get('/transactions/export/excel', [TransactionsController::class, 'exportExcel'])->name('transactions.export.excel');
Route::get('/transactions/export/pdf', [TransactionsController::class, 'exportPdf'])->name('transactions.export.pdf');

Route::resource('saldos', SaldoController::class);
Route::resource('transactions', TransactionsController::class);
Route::resource('categories', CategoryController::class);

Route::get('/financial-planner', [FinancialPlannerController::class, 'index'])->name('financial-planner.index');

Route::resource('budgets', BudgetController::class);
Route::post('budgets/{budget}/activities', [BudgetController::class, 'storeActivity'])->name('budgets.activities.store');
Route::put('budgets/{budget}/activities/{activity}', [BudgetController::class, 'updateActivity'])->name('budgets.activities.update');
Route::delete('budgets/{budget}/activities/{activity}', [BudgetController::class, 'destroyActivity'])->name('budgets.activities.destroy');
Route::resource('debts', DebtController::class);
Route::post('debts/{debt}/payments', [DebtController::class, 'storePayment'])->name('debts.payments.store');

Route::resource('savings-goals', SavingsGoalController::class)->parameters(['savings-goals' => 'savings_goal']);
Route::post('savings-goals/{savings_goal}/contributions', [SavingsGoalController::class, 'storeContribution'])
    ->name('savings-goals.contributions.store');

// Pemasukan Usaha
Route::get('/api/v1/incomes', [IncomeController::class, 'data'])->name('incomes.data');
Route::resource('incomes', IncomeController::class)->except(['show']);

// Biaya Operasional (alias dari Aktivitas Anggaran, untuk akses cepat)
Route::get('/api/v1/operational-expenses', [OperationalExpenseController::class, 'data'])->name('operational.data');
Route::get('/api/v1/operational-expenses/budget-info/{budget}', [OperationalExpenseController::class, 'budgetInfo'])->name('operational.budget_info');
Route::resource('operational-expenses', OperationalExpenseController::class)
    ->parameters(['operational-expenses' => 'operational'])
    ->names('operational')
    ->except(['show']);

// Laporan Laba/Rugi
Route::get('/laba-rugi', [ProfitLossController::class, 'index'])->name('profit-loss.index');

// Recurring Transactions
Route::get('/api/v1/recurring-transactions', [RecurringTransactionController::class, 'data'])->name('recurring.data');
Route::post('/recurring-transactions/{recurring}/post', [RecurringTransactionController::class, 'postNow'])->name('recurring.post');
Route::resource('recurring-transactions', RecurringTransactionController::class)->parameters([
    'recurring-transactions' => 'recurring',
])->except(['show']);

// AI: Chatbot & Insight
Route::post('/api/v1/ai/chat', [AiChatController::class, 'ask'])->name('ai.chat');
Route::get('/insight', [InsightController::class, 'index'])->name('insight.index');
Route::post('/insight/summary', [InsightController::class, 'generateSummary'])->name('insight.summary');
Route::post('/insight/explain-anomalies', [InsightController::class, 'explainAnomalies'])->name('insight.explain_anomalies');
