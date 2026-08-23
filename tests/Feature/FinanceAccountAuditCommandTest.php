<?php

use App\Enums\FinanceAccountType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Services\FinanceAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('passes the read-only account audit when defaults are consistent', function () {
    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Finance Account Audit')
        ->expectsOutputToContain('Finance accounts are consistent.')
        ->assertSuccessful();

    Artisan::call('finance:account-audit');
    $output = Artisan::output();

    expect($output)
        ->toContain('Entities without accounts')
        ->toContain('Multiple defaults')
        ->toContain('Active accounts without default')
        ->toContain('Invalid entity relations')
        ->toContain('Duplicate account names')
        ->toContain('Financial records without account')
        ->toContain('Account entity mismatch')
        ->toContain('Recurring account mismatch')
        ->toContain('Finance Transfer Audit')
        ->toContain('Orphan transfers')
        ->toContain('Source equals destination')
        ->toContain('Business Capital Audit')
        ->toContain('Orphan capital records')
        ->toContain('Owner Withdrawal Audit')
        ->toContain('Orphan withdrawal records')
        ->toContain('Profit Distribution Audit')
        ->toContain('Orphan distribution records')
        ->toContain('Receivable Audit')
        ->toContain('Orphan receivable records')
        ->toContain('Debt Audit')
        ->toContain('Orphan debt records');
});

it('fails the account audit when an entity has no accounts', function () {
    FinanceEntity::factory()->family()->create(['name' => 'Tanpa Account']);

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Entities without accounts')
        ->expectsOutputToContain('Tanpa Account')
        ->assertFailed();
});

it('fails the account audit when an entity has multiple defaults', function () {
    $entity = FinanceEntity::factory()->family()->create();
    FinanceAccount::factory()->default()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'Kas A',
        'type' => FinanceAccountType::CASH,
    ]);
    FinanceAccount::factory()->default()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'Kas B',
        'type' => FinanceAccountType::CASH,
    ]);

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('multiple defaults')
        ->assertFailed();
});

it('fails the account audit when active accounts have no default', function () {
    $entity = FinanceEntity::factory()->family()->create();
    FinanceAccount::factory()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'Kas Aktif',
        'type' => FinanceAccountType::CASH,
        'is_active' => true,
        'is_default' => false,
    ]);

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Active accounts without default')
        ->assertFailed();
});

it('does not change data when the account audit command runs', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Audit Tetap']);

    $this->artisan('finance:account-audit')->assertFailed();

    expect($entity->fresh()->accounts()->count())->toBe(0)
        ->and(app(FinanceAccountService::class)->hasCriticalInconsistencies())->toBeTrue();
});
