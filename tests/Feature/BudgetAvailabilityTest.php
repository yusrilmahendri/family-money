<?php

use App\Enums\FinanceAccountType;
use App\Enums\PlantationOperatingBudgetStatus;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\PlantationOperatingBudget;
use App\Models\Transaction;
use App\Services\BudgetAvailabilityService;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Services\OwnerWithdrawalService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function availabilityService(): BudgetAvailabilityService
{
    return app(BudgetAvailabilityService::class);
}

function availabilityBalances(): FinanceAccountBalanceService
{
    return app(FinanceAccountBalanceService::class);
}

function availabilityCash(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function availabilityGrant(FinanceEntity $entity): void
{
    [, $plain] = app(\App\Services\FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function availabilityCategory(FinanceEntity $entity, string $name = 'Operasional'): Category
{
    return Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'name' => $name,
    ]);
}

function availabilityCategoryBudget(FinanceEntity $entity, float $amount, ?Category $category = null): Budget
{
    return $entity->budgets()->create([
        'category_id' => ($category ?? availabilityCategory($entity))->id,
        'amount' => $amount,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
}

function availabilityRealize(Budget $budget, $account, float $amount, mixed $date = null): BudgetActivity
{
    return $budget->activities()->create([
        'finance_account_id' => $account->id,
        'name' => 'Realisasi',
        'amount' => $amount,
        'activity_date' => $date ?? now(),
    ]);
}

it('reserves category budget against available cash without changing cash', function () {
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tersedia']);
    availabilityCash($business, 'Kas Usaha', 100_000_000);
    availabilityCategoryBudget($business, 20_000_000);

    $summary = availabilityService()->summary($business);

    expect($summary['cash_balance'])->toBe(100_000_000.0)
        ->and($summary['planned_amount'])->toBe(20_000_000.0)
        ->and($summary['realized_amount'])->toBe(0.0)
        ->and($summary['reserved_remaining'])->toBe(20_000_000.0)
        ->and($summary['available_balance'])->toBe(80_000_000.0)
        ->and(availabilityBalances()->balanceForEntity($business))->toBe(100_000_000.0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(Income::query()->count())->toBe(0);
});

it('does not double-count realization against available balance', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = availabilityCash($business, 'Kas Usaha', 100_000_000);
    $budget = availabilityCategoryBudget($business, 20_000_000);
    availabilityRealize($budget, $account, 5_000_000);

    $summary = availabilityService()->summary($business);

    expect($summary['cash_balance'])->toBe(95_000_000.0)
        ->and($summary['planned_amount'])->toBe(20_000_000.0)
        ->and($summary['realized_amount'])->toBe(5_000_000.0)
        ->and($summary['reserved_remaining'])->toBe(15_000_000.0)
        ->and($summary['available_balance'])->toBe(80_000_000.0);
});

it('floors reserved remaining at zero when budget is over-realized', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = availabilityCash($business, 'Kas Usaha', 100_000_000);
    $budget = availabilityCategoryBudget($business, 20_000_000);
    availabilityRealize($budget, $account, 25_000_000);

    $summary = availabilityService()->summary($business);

    expect($summary['cash_balance'])->toBe(75_000_000.0)
        ->and($summary['reserved_remaining'])->toBe(0.0)
        ->and($summary['available_balance'])->toBe(75_000_000.0);
});

it('rejects a category budget larger than available cash', function () {
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Usaha', 100_000_000);
    availabilityCategoryBudget($business, 20_000_000);
    $category = $business->categories()->first();
    availabilityGrant($business);

    $this->post(route('entity.budgets.store', $business), [
        'amount' => '81000000',
        'periode' => now()->toDateString(),
        'category_id' => $category->id,
        'mode' => 'category',
    ])->assertSessionHasErrors('amount');

    expect($business->budgets()->count())->toBe(1)
        ->and(availabilityBalances()->balanceForEntity($business))->toBe(100_000_000.0);
});

it('lets an update reuse the budget remaining reservation', function () {
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Usaha', 78_906_500);
    $budget = availabilityCategoryBudget($business, 10_000_000);
    availabilityGrant($business);

    $this->put(route('entity.budgets.update', [$business, $budget]), [
        'amount' => '15000000',
        'periode' => now()->toDateString(),
        'category_id' => $budget->category_id,
    ])->assertRedirect(route('entity.budgets.index', $business));

    $summary = availabilityService()->summary($business->fresh());

    expect((float) $budget->fresh()->amount)->toBe(15_000_000.0)
        ->and($summary['reserved_remaining'])->toBe(15_000_000.0)
        ->and($summary['available_balance'])->toBe(63_906_500.0)
        ->and($summary['cash_balance'])->toBe(78_906_500.0);

    $this->put(route('entity.budgets.update', [$business, $budget]), [
        'amount' => '80000000',
        'periode' => now()->toDateString(),
        'category_id' => $budget->category_id,
    ])->assertSessionHasErrors('amount');
});

it('keeps BUSINESS A reservation isolated from BUSINESS B', function () {
    $businessA = FinanceEntity::factory()->business()->create(['name' => 'Usaha A']);
    $businessB = FinanceEntity::factory()->business()->create(['name' => 'Usaha B']);
    availabilityCash($businessA, 'Kas A', 100_000_000);
    availabilityCash($businessB, 'Kas B', 50_000_000);
    availabilityCategoryBudget($businessA, 20_000_000);

    $summaryA = availabilityService()->summary($businessA);
    $summaryB = availabilityService()->summary($businessB);

    expect($summaryA['reserved_remaining'])->toBe(20_000_000.0)
        ->and($summaryA['available_balance'])->toBe(80_000_000.0)
        ->and($summaryB['reserved_remaining'])->toBe(0.0)
        ->and($summaryB['available_balance'])->toBe(50_000_000.0)
        ->and($summaryB['cash_balance'])->toBe(50_000_000.0);
});

it('uses only ACTIVE account cash for available balance', function () {
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Aktif', 100_000_000);
    $inactive = app(FinanceAccountService::class)->create($business, [
        'name' => 'Kas Lama',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 50_000_000,
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);
    availabilityCategoryBudget($business, 20_000_000);

    $summary = availabilityService()->summary($business);

    expect(availabilityBalances()->balanceForEntity($business))->toBe(100_000_000.0)
        ->and($summary['cash_balance'])->toBe(100_000_000.0)
        ->and($summary['available_balance'])->toBe(80_000_000.0);
});

it('shows cash reserved and available on the business dashboard and anggaran page', function () {
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Dashboard Tersedia']);
    availabilityCash($business, 'Kas Usaha', 78_906_500);
    availabilityCategoryBudget($business, 10_000_000);
    availabilityGrant($business);

    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Saldo Kas')
        ->assertSee('Dana Dialokasikan')
        ->assertSee('Saldo Tersedia')
        ->assertSee('Rp 78.906.500')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Rp 68.906.500')
        ->assertSee('Saldo aktual Kas/Rekening aktif')
        ->assertSee('Sisa dana yang masih dicadangkan untuk anggaran')
        ->assertSee('Dana yang belum terikat anggaran');

    $this->get(route('entity.budgets.index', $business))
        ->assertOk()
        ->assertSee('Saldo Kas')
        ->assertSee('Dialokasikan')
        ->assertSee('Tersedia')
        ->assertSee('Rp 78.906.500')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Rp 68.906.500')
        ->assertSee('Anggaran tidak mengurangi saldo kas aktual');
});

it('does not treat plantation operating pagu and category budgets as a combined reservation', function () {
    Http::preventStrayRequests();
    fakeEntityPlantationBudgetHttp();
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Kebun', 100_000_000);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));
    fundBusinessCash($business->fresh(), 100_000_000);

    app(\App\Services\PlantationOperatingBudgetService::class)->create($business->fresh(), [
        'name' => 'Anggaran Kebun September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 10_000_000,
    ]);
    availabilityCategoryBudget($business->fresh(), 10_000_000);

    $summary = availabilityService()->summary($business->fresh());

    expect($summary['uses_operating_pagu'])->toBeTrue()
        ->and($summary['planned_amount'])->toBe(10_000_000.0)
        ->and($summary['reserved_remaining'])->toBe(10_000_000.0)
        ->and($summary['cash_balance'])->toBe(100_000_000.0)
        ->and($summary['available_balance'])->toBe(90_000_000.0);
});

it('reduces plantation reserved remaining when realization falls inside the pagu period', function () {
    Http::preventStrayRequests();
    fakeEntityPlantationBudgetHttp();
    $business = FinanceEntity::factory()->business()->create();
    $account = availabilityCash($business, 'Kas Kebun', 78_906_500);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));
    $business = $business->fresh();

    app(\App\Services\PlantationOperatingBudgetService::class)->create($business, [
        'name' => 'Anggaran Kebun September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 10_000_000,
    ]);

    $before = availabilityService()->summary($business->fresh());
    expect($before['cash_balance'])->toBe(78_906_500.0)
        ->and($before['reserved_remaining'])->toBe(10_000_000.0)
        ->and($before['available_balance'])->toBe(68_906_500.0);

    $categoryBudget = availabilityCategoryBudget($business->fresh(), 10_000_000);
    availabilityRealize($categoryBudget, $account, 3_000_000, '2026-09-10');

    $after = availabilityService()->summary($business->fresh());

    expect($after['cash_balance'])->toBe(75_906_500.0)
        ->and($after['planned_amount'])->toBe(10_000_000.0)
        ->and($after['realized_amount'])->toBe(3_000_000.0)
        ->and($after['reserved_remaining'])->toBe(7_000_000.0)
        ->and($after['available_balance'])->toBe(68_906_500.0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('rejects a plantation operating budget larger than available cash and does not post cash', function () {
    Http::preventStrayRequests();
    fakeEntityPlantationBudgetHttp();
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Kebun', 78_906_500);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));
    availabilityGrant($business->fresh());

    app(\App\Services\PlantationOperatingBudgetService::class)->create($business->fresh(), [
        'name' => 'Anggaran Kebun September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 10_000_000,
    ]);

    $this->post(route('entity.budgets.store', $business->fresh()), [
        'name' => 'Anggaran Kedua',
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
        'allocated_amount' => '70000000',
    ])->assertSessionHasErrors('allocated_amount');

    expect(PlantationOperatingBudget::query()->count())->toBe(1)
        ->and(availabilityBalances()->balanceForEntity($business->fresh()))->toBe(78_906_500.0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(BudgetActivity::query()->count())->toBe(0);
});

it('still treats SYNC_ERROR and expired-period plantation budgets as reserved', function () {
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Kebun', 100_000_000);

    PlantationOperatingBudget::query()->create([
        'finance_entity_id' => $business->id,
        'name' => 'Draft cadangan',
        'period_start' => now()->subMonths(2)->toDateString(),
        'period_end' => now()->subMonth()->toDateString(),
        'allocated_amount' => 8_000_000,
        'status' => PlantationOperatingBudgetStatus::SYNC_ERROR,
    ]);

    portalActivatePlantation($business);
    $summary = availabilityService()->summary($business->fresh());

    expect($summary['uses_operating_pagu'])->toBeTrue()
        ->and($summary['planned_amount'])->toBe(8_000_000.0)
        ->and($summary['reserved_remaining'])->toBe(8_000_000.0)
        ->and($summary['available_balance'])->toBe(92_000_000.0);
});

it('keeps prive as cash movement without turning it into expense', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = availabilityCash($business, 'Kas Usaha', 100_000_000);
    $destination = availabilityCash($family, 'Kas Family', 0);
    availabilityCategoryBudget($business, 20_000_000);

    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 10_000_000,
        'transaction_date' => now()->toDateString(),
    ]);

    $summary = availabilityService()->summary($business->fresh());

    expect(OwnerWithdrawal::query()->count())->toBe(1)
        ->and(availabilityBalances()->balance($source->fresh()))->toBe(90_000_000.0)
        ->and(availabilityBalances()->balance($destination->fresh()))->toBe(10_000_000.0)
        ->and((float) $business->incomes()->sum('amount'))->toBe(0.0)
        ->and($summary['cash_balance'])->toBe(90_000_000.0)
        ->and($summary['reserved_remaining'])->toBe(20_000_000.0)
        ->and($summary['available_balance'])->toBe(70_000_000.0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('lets the service reject an over-allocation without inserting a plantation row', function () {
    Http::preventStrayRequests();
    fakeEntityPlantationBudgetHttp();
    $business = FinanceEntity::factory()->business()->create();
    availabilityCash($business, 'Kas Kebun', 10_000_000);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    expect(fn () => app(\App\Services\PlantationOperatingBudgetService::class)->create($business->fresh(), [
        'name' => 'Terlalu besar',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 70_000_000,
    ]))->toThrow(ValidationException::class);

    expect(PlantationOperatingBudget::query()->count())->toBe(0)
        ->and(availabilityBalances()->balanceForEntity($business->fresh()))->toBe(10_000_000.0);
});
