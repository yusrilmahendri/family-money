<?php

use App\Models\Transaction;
use App\Services\FinanceEntityOwnershipMigrator;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes the read-only ownership audit when every record is mapped', function () {
    $this->artisan('finance:entity-audit')
        ->expectsOutputToContain('Finance Entity Ownership Audit')
        ->expectsOutputToContain('Ownership mapping is consistent.')
        ->assertSuccessful();

    \Illuminate\Support\Facades\Artisan::call('finance:entity-audit');
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($output)
        ->toContain('Transactions:')
        ->toContain('Categories:')
        ->toContain('Incomes:')
        ->toContain('Budgets:')
        ->toContain('Debts:')
        ->toContain('Savings Goals:')
        ->toContain('Recurring Transactions:');
});

it('fails the ownership audit when a legacy context is unknown', function () {
    Transaction::query()->create([
        'finance_entity_id' => null,
        'context' => 'KONTEKS_LAIN',
        'amount' => 10000,
        'transaction_date' => now(),
        'description' => 'Unmapped',
    ]);

    expect((new FinanceEntityOwnershipMigrator)->hasCriticalInconsistencies())->toBeTrue();

    $this->artisan('finance:entity-audit')
        ->expectsOutputToContain('legacy context mismatch')
        ->assertFailed();
});

it('does not change data when the audit command runs', function () {
    $transaction = Transaction::query()->create([
        'finance_entity_id' => null,
        'context' => FinanceContext::PRIBADI,
        'amount' => 10000,
        'transaction_date' => now(),
        'description' => 'Audit must not backfill',
    ]);

    $this->artisan('finance:entity-audit')->assertFailed();

    expect($transaction->fresh()->finance_entity_id)->toBeNull();
});
