<?php

use App\Enums\FinanceAccountType;
use App\Models\BudgetActivity;
use App\Models\FinanceEntity;
use App\Models\ProfitDistribution;
use App\Services\BusinessCapitalContributionService;
use App\Services\FinanceAccountService;
use App\Services\FinanceTransferService;
use App\Services\OwnerWithdrawalService;
use App\Services\ProfitDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function distributionService(): ProfitDistributionService
{
    return app(ProfitDistributionService::class);
}

it('moves cash from BUSINESS to FAMILY without changing profit', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Laba']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Laba']);
    $source = cashAccount($business, 'Kas Kebun A', 0);
    $destination = cashAccount($family, 'BCA Keluarga A', 0);

    businessIncome($business, 100_000_000, now(), $source);
    businessExpense($business, 60_000_000, now(), $source);

    expect(profitService()->calculate($business)['profit'])->toBe(40_000_000.0)
        ->and(balanceService()->balance($source->fresh()))->toBe(40_000_000.0);

    [$from, $to] = profitService()->currentMonthBounds();
    grantEntityAccess($business);
    grantEntityAccess($family);

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
        'description' => 'Bagi laba',
        'family_entity_id' => $family->id,
    ])->assertSessionHasErrors('family_entity_id');

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
        'description' => 'Bagi laba',
    ])->assertRedirect(route('entity.profit-distributions.index', $business));

    $availability = distributionService()->availability($business, $from, $to);
    $profit = profitService()->calculate($business);

    expect(ProfitDistribution::query()->count())->toBe(1)
        ->and(balanceService()->balance($source->fresh()))->toBe(30_000_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(10_000_000.0)
        ->and($profit['profit'])->toBe(40_000_000.0)
        ->and($profit['operational_expense'])->toBe(60_000_000.0)
        ->and($availability['distributed'])->toBe(10_000_000.0)
        ->and($availability['available'])->toBe(30_000_000.0)
        ->and(profitService()->summary($business)['distributed_profit'])->toBe(10_000_000.0)
        ->and(profitService()->summary($business)['undistributed_profit'])->toBe(30_000_000.0);

    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Laba / Rugi')
        ->assertSee('Rp 40.000.000')
        ->assertSee('Distributed Profit')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Undistributed Profit')
        ->assertSee('Rp 30.000.000');

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Profit Distribution Received')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Pengeluaran')
        ->assertSee('Rp 0');

    $this->get(route('entity.profit-loss.index', $business))
        ->assertOk()
        ->assertSee('Laba: Rp 40.000.000')
        ->assertSee('Distributed Profit: Rp 10.000.000')
        ->assertSee('Undistributed Profit: Rp 30.000.000')
        ->assertSee('tidak masuk laba');
});

it('rejects a private user who lacks destination FAMILY capability', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Laba Terkunci']);
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Usaha Laba', 5_000_000);
    $destination = cashAccount($family, 'Kas Family Laba', 0);
    businessIncome($business, 200_000, now(), $source);

    grantEntityAccess($business);
    [$from, $to] = profitService()->currentMonthBounds();

    $this->get(route('entity.profit-distributions.create', $business))
        ->assertOk()
        ->assertDontSee('Keluarga Laba Terkunci');

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '100000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
    ])->assertSessionHasErrors('family_public_id');

    expect(ProfitDistribution::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(5_200_000.0);
});

it('rejects over-distribution insufficient balance wrong types and forged accounts', function () {
    $family = FinanceEntity::factory()->family()->create();
    $otherFamily = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $otherBusiness = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Sumber Laba', 0);
    $thin = app(FinanceAccountService::class)->create($business, [
        'name' => 'Kas Tipis',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 5_000,
    ]);
    $destination = cashAccount($family, 'Kas Family Laba', 0);
    $familyDest = cashAccount($otherFamily, 'Kas Family Lain Laba', 0);
    $foreignSource = cashAccount($otherBusiness, 'Kas Usaha Lain Laba', 50_000);

    businessIncome($business, 80_000, now(), $source);
    businessExpense($business, 20_000, now(), $source);

    grantEntityAccess($business);
    grantEntityAccess($family);
    grantEntityAccess($otherFamily);
    [$from, $to] = profitService()->currentMonthBounds();

    expect(distributionService()->availability($business, $from, $to)['available'])->toBe(60_000.0);

    $this->post(route('entity.profit-distributions.store', $family), [
        'source_account_id' => $destination->id,
        'family_public_id' => $business->public_id,
        'destination_account_id' => $source->id,
        'amount' => '10000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
    ])->assertNotFound();

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $otherBusiness->public_id,
        'destination_account_id' => $foreignSource->id,
        'amount' => '10000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
    ])->assertSessionHasErrors('family_public_id');

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $familyDest->id,
        'amount' => '10000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '70000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $thin->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
    ])->assertSessionHasErrors('amount');

    expect(ProfitDistribution::query()->count())->toBe(0)
        ->and(Route::has('entity.profit-distributions.edit'))->toBeFalse()
        ->and(Route::has('entity.profit-distributions.destroy'))->toBeFalse();
});

it('keeps available profit independent from capital prive and transfer', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Tersedia', 0);
    $other = app(FinanceAccountService::class)->create($business, [
        'name' => 'Kas Geser',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    $familyAccount = cashAccount($family, 'BCA Tersedia', 2_000_000);

    businessIncome($business, 200_000, now(), $source);
    businessExpense($business, 50_000, now(), $source);
    [$from, $to] = profitService()->currentMonthBounds();

    $before = distributionService()->availability($business, $from, $to);
    expect($before['available'])->toBe(150_000.0)
        ->and($before['profit'])->toBe(150_000.0);

    app(FinanceTransferService::class)->create($business, [
        'source_account_id' => $source->id,
        'destination_account_id' => $other->id,
        'amount' => 20_000,
        'transaction_date' => now()->toDateString(),
    ]);
    app(BusinessCapitalContributionService::class)->create($family, $business, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $source->id,
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
    ]);
    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $source->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 30_000,
        'transaction_date' => now()->toDateString(),
    ]);

    $after = distributionService()->availability($business, $from, $to);

    expect($after['profit'])->toBe(150_000.0)
        ->and($after['available'])->toBe(150_000.0)
        ->and(profitService()->calculate($business)['profit'])->toBe(150_000.0);
});

it('allows an admin to create a distribution without a private token', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Family Admin Laba']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Admin Laba']);
    $source = cashAccount($business, 'Kas Admin Laba', 0);
    $destination = cashAccount($family, 'BCA Admin Laba', 100_000);
    businessIncome($business, 90_000, now(), $source);
    businessExpense($business, 10_000, now(), $source);
    [$from, $to] = profitService()->currentMonthBounds();

    actingAdmin()
        ->get(route('admin.finance-entities.profit-distributions.index', $business))
        ->assertOk()
        ->assertSee('Pembagian Laba');

    actingAdmin()
        ->post(route('admin.finance-entities.profit-distributions.store', $business), [
            'source_account_id' => $source->id,
            'family_public_id' => $family->public_id,
            'destination_account_id' => $destination->id,
            'amount' => '25000',
            'distribution_date' => now()->toDateString(),
            'period_start' => $from,
            'period_end' => $to,
            'description' => 'Bagi laba admin',
        ])
        ->assertRedirect(route('admin.finance-entities.profit-distributions.index', $business));

    expect(balanceService()->balance($source->fresh()))->toBe(55_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(125_000.0)
        ->and(profitService()->calculate($business)['profit'])->toBe(80_000.0)
        ->and((float) BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $business->id))
            ->sum('amount'))->toBe(10_000.0);
});

it('rolls back a distribution when the wrapping transaction fails', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Atomic Laba', 0);
    $destination = cashAccount($family, 'Kas Atomic Family Laba', 0);
    businessIncome($business, 50_000, now(), $source);

    expect(function () use ($family, $business, $source, $destination): void {
        DB::transaction(function () use ($family, $business, $source, $destination): void {
            app(ProfitDistributionService::class)->create($business, $family, [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => 10_000,
                'distribution_date' => now()->toDateString(),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ]);

            throw new RuntimeException('force rollback');
        });
    })->toThrow(RuntimeException::class);

    expect(ProfitDistribution::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(50_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0);
});

it('rejects service-level distribution from a FAMILY source or to a BUSINESS destination', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Kas Family Dist', 50_000);
    $businessAccount = cashAccount($business, 'Kas Usaha Dist', 50_000);
    businessIncome($business, 40_000, now(), $businessAccount);

    expect(fn () => app(ProfitDistributionService::class)->create($family, $business, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 10_000,
        'distribution_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(ProfitDistributionService::class)->create($business, $business, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 10_000,
        'distribution_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(ProfitDistribution::query()->count())->toBe(0);
});

it('detects invalid distribution records in the read-only account audit', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Audit Family Laba', 0);
    $businessAccount = cashAccount($business, 'Audit Usaha Laba', 100_000);

    $business->profitDistributionsGiven()->create([
        'source_account_id' => $familyAccount->id,
        'family_entity_id' => $family->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 10_000,
        'distribution_date' => now(),
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'description' => 'Account mismatch',
    ]);

    DB::table('profit_distributions')->insert([
        'public_id' => (string) Str::ulid(),
        'business_entity_id' => $family->id,
        'source_account_id' => $familyAccount->id,
        'family_entity_id' => $business->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 5_000,
        'distribution_date' => now()->toDateString(),
        'period_start' => now()->endOfMonth()->toDateString(),
        'period_end' => now()->startOfMonth()->toDateString(),
        'description' => 'Wrong types and period',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('profit_distributions')->insert([
        'public_id' => (string) Str::ulid(),
        'business_entity_id' => $business->id,
        'source_account_id' => $businessAccount->id,
        'family_entity_id' => $business->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 0,
        'distribution_date' => now()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'description' => 'Same entity zero',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = ProfitDistribution::query()->count();

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Profit Distribution Audit')
        ->expectsOutputToContain('Source is not BUSINESS')
        ->expectsOutputToContain('Destination is not FAMILY')
        ->expectsOutputToContain('Exceeds period profit')
        ->expectsOutputToContain('Orphan distribution records')
        ->assertFailed();

    $audit = app(ProfitDistributionService::class)->audit();

    expect(ProfitDistribution::query()->count())->toBe($before)
        ->and($audit['source_not_business'])->toBeGreaterThan(0)
        ->and($audit['destination_not_family'])->toBeGreaterThan(0)
        ->and($audit['account_entity_mismatch'])->toBeGreaterThan(0)
        ->and($audit['non_positive_amount'])->toBeGreaterThan(0)
        ->and($audit['same_source_and_destination'])->toBeGreaterThan(0)
        ->and($audit['invalid_period'])->toBeGreaterThan(0)
        ->and($audit['exceeds_period_profit'])->toBeGreaterThan(0)
        ->and($audit)->toHaveKeys(['orphan_distributions', 'invalid_account_relation', 'exceeds_all_time_profit']);
});
