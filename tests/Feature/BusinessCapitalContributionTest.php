<?php

use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Transaction;
use App\Services\BusinessCapitalContributionService;
use App\Services\FinanceAccountService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('moves cash from FAMILY to BUSINESS without changing income expense or profit', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Modal']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Modal']);
    $source = cashAccount($family, 'BCA Keluarga A', 20_000_000);
    $destination = cashAccount($business, 'Kas Kebun A', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $destination->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => 500_000,
        'income_date' => now(),
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => 1_000_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $destination->id,
        'name' => 'Pupuk',
        'amount' => 100_000,
        'activity_date' => now(),
    ]);

    expect(balanceService()->balance($source->fresh()))->toBe(20_000_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(400_000.0);

    grantEntityAccess($family);
    grantEntityAccess($business);

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '20000000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Setor modal',
        'business_entity_id' => $business->id,
    ])->assertSessionHasErrors('business_entity_id');

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '20000000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Setor modal',
    ])->assertRedirect(route('entity.capital-contributions.index', $family));

    expect(BusinessCapitalContribution::query()->count())->toBe(1)
        ->and(balanceService()->balance($source->fresh()))->toBe(0.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(20_400_000.0)
        ->and((float) $business->incomes()->sum('amount'))->toBe(500_000.0)
        ->and((float) Transaction::query()->where('finance_entity_id', $family->id)->sum('amount'))->toBe(0.0)
        ->and((float) BudgetActivity::query()->where('budget_id', $budget->id)->sum('amount'))->toBe(100_000.0);

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Modal ke Usaha')
        ->assertSee('Rp 20.000.000')
        ->assertSee('Pengeluaran')
        ->assertSee('Rp 0');

    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Total Modal Masuk')
        ->assertSee('Rp 20.000.000')
        ->assertSee('Pemasukan')
        ->assertSee('Rp 500.000')
        ->assertSee('Laba / Rugi')
        ->assertSee('Rp 400.000');

    $this->get(route('entity.profit-loss.index', $business))
        ->assertOk()
        ->assertSee('Laba: Rp 400.000')
        ->assertSee('Modal masuk: Rp 20.000.000')
        ->assertSee('tidak masuk laba');
});

it('rejects a private user who lacks destination BUSINESS capability', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Terkunci']);
    $source = cashAccount($family, 'Kas Family', 5_000_000);
    $destination = cashAccount($business, 'Kas Usaha', 0);

    grantEntityAccess($family);

    $this->get(route('entity.capital-contributions.create', $family))
        ->assertOk()
        ->assertDontSee('Usaha Terkunci');

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '100000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('business_public_id');

    expect(BusinessCapitalContribution::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(5_000_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0);
});

it('rejects invalid source destination account entity and amount combinations', function () {
    $family = FinanceEntity::factory()->family()->create();
    $otherFamily = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $otherBusiness = FinanceEntity::factory()->business()->create();
    $source = cashAccount($family, 'Kas Sumber', 100_000);
    $familyDest = cashAccount($otherFamily, 'Kas Family Lain', 0);
    $destination = cashAccount($business, 'Kas Usaha', 0);
    $foreignDest = cashAccount($otherBusiness, 'Kas Usaha Lain', 0);
    $inactiveDest = app(FinanceAccountService::class)->create($business, [
        'name' => 'Kas Lama Usaha',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    app(FinanceAccountService::class)->deactivate($inactiveDest);

    grantEntityAccess($family);
    grantEntityAccess($business);
    grantEntityAccess($otherFamily);

    $this->post(route('entity.capital-contributions.store', $business), [
        'source_account_id' => $destination->id,
        'business_public_id' => $family->public_id,
        'destination_account_id' => $source->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertNotFound();

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $otherFamily->public_id,
        'destination_account_id' => $familyDest->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('business_public_id');

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $foreignDest->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $inactiveDest->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '150000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '0',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $business->update(['is_active' => false]);
    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('business_public_id');

    expect(BusinessCapitalContribution::query()->count())->toBe(0)
        ->and(Route::has('entity.capital-contributions.edit'))->toBeFalse()
        ->and(Route::has('entity.capital-contributions.destroy'))->toBeFalse();
});

it('allows an admin to create capital without a private token', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Family Admin Modal']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Admin Modal']);
    $source = cashAccount($family, 'BCA Admin', 2_000_000);
    $destination = cashAccount($business, 'Kas Admin Usaha', 100_000);

    actingAdmin()
        ->get(route('admin.finance-entities.capital-contributions.index', $family))
        ->assertOk()
        ->assertSee('Modal ke Usaha');

    actingAdmin()
        ->post(route('admin.finance-entities.capital-contributions.store', $family), [
            'source_account_id' => $source->id,
            'business_public_id' => $business->public_id,
            'destination_account_id' => $destination->id,
            'amount' => '750000',
            'transaction_date' => now()->toDateString(),
            'description' => 'Modal admin',
        ])
        ->assertRedirect(route('admin.finance-entities.capital-contributions.index', $family));

    expect(balanceService()->balance($source->fresh()))->toBe(1_250_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(850_000.0)
        ->and((float) $business->incomes()->sum('amount'))->toBe(0.0);
});

it('rolls back capital when the wrapping transaction fails', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($family, 'Kas Atomic Family', 400_000);
    $destination = cashAccount($business, 'Kas Atomic Usaha', 0);

    expect(function () use ($family, $business, $source, $destination): void {
        DB::transaction(function () use ($family, $business, $source, $destination): void {
            app(BusinessCapitalContributionService::class)->create($family, $business, [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => 80_000,
                'transaction_date' => now()->toDateString(),
            ]);

            throw new RuntimeException('force rollback');
        });
    })->toThrow(RuntimeException::class);

    expect(BusinessCapitalContribution::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(400_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0);
});

it('rejects service-level capital from a BUSINESS source or to a FAMILY destination', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Kas Family Svc', 50_000);
    $businessAccount = cashAccount($business, 'Kas Usaha Svc', 50_000);

    expect(fn () => app(BusinessCapitalContributionService::class)->create($business, $family, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(BusinessCapitalContributionService::class)->create($family, $family, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(BusinessCapitalContribution::query()->count())->toBe(0);
});

it('detects invalid capital records in the read-only account audit', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Audit Family', 100_000);
    $businessAccount = cashAccount($business, 'Audit Usaha', 0);

    $family->capitalContributionsGiven()->create([
        'source_account_id' => $businessAccount->id,
        'business_entity_id' => $business->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 10_000,
        'transaction_date' => now(),
        'description' => 'Account mismatch',
    ]);

    DB::table('business_capital_contributions')->insert([
        'public_id' => (string) Str::ulid(),
        'source_entity_id' => $business->id,
        'source_account_id' => $businessAccount->id,
        'business_entity_id' => $family->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 5_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Wrong types',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('business_capital_contributions')->insert([
        'public_id' => (string) Str::ulid(),
        'source_entity_id' => $family->id,
        'source_account_id' => $familyAccount->id,
        'business_entity_id' => $family->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 0,
        'transaction_date' => now()->toDateString(),
        'description' => 'Same entity zero',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = BusinessCapitalContribution::query()->count();

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Business Capital Audit')
        ->expectsOutputToContain('Source is not FAMILY')
        ->expectsOutputToContain('Destination is not BUSINESS')
        ->expectsOutputToContain('Orphan capital records')
        ->assertFailed();

    $audit = app(BusinessCapitalContributionService::class)->audit();

    expect(BusinessCapitalContribution::query()->count())->toBe($before)
        ->and($audit['source_not_family'])->toBeGreaterThan(0)
        ->and($audit['destination_not_business'])->toBeGreaterThan(0)
        ->and($audit['account_entity_mismatch'])->toBeGreaterThan(0)
        ->and($audit['non_positive_amount'])->toBeGreaterThan(0)
        ->and($audit['same_source_and_destination'])->toBeGreaterThan(0)
        ->and($audit)->toHaveKeys(['orphan_contributions', 'invalid_account_relation']);
});
