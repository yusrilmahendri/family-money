<?php

use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Services\FinanceAccountService;
use App\Services\OwnerWithdrawalService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('moves cash from BUSINESS to FAMILY without changing income expense profit or budget', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Prive']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Prive']);
    $source = cashAccount($business, 'Kas Kebun A', 0);
    $destination = cashAccount($family, 'BCA Keluarga A', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $source->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => 100_000_000,
        'income_date' => now(),
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => 80_000_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $source->id,
        'name' => 'Pupuk',
        'amount' => 60_000_000,
        'activity_date' => now(),
    ]);

    expect(balanceService()->balance($source->fresh()))->toBe(40_000_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0)
        ->and((float) $business->incomes()->sum('amount') - (float) BudgetActivity::query()
            ->where('budget_id', $budget->id)
            ->sum('amount'))->toBe(40_000_000.0);

    grantEntityAccess($business);
    grantEntityAccess($family);

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Prive pemilik',
        'family_entity_id' => $family->id,
    ])->assertSessionHasErrors('family_entity_id');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Prive pemilik',
    ])->assertRedirect(route('entity.owner-withdrawals.index', $business));

    expect(OwnerWithdrawal::query()->count())->toBe(1)
        ->and(balanceService()->balance($source->fresh()))->toBe(30_000_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(10_000_000.0)
        ->and(balanceService()->breakdown($source->fresh())['withdrawal_out'])->toBe(10_000_000.0)
        ->and(balanceService()->breakdown($destination->fresh())['withdrawal_in'])->toBe(10_000_000.0)
        ->and(balanceService()->breakdown($source->fresh())['expense_outflow'])->toBe(60_000_000.0)
        ->and((float) $business->incomes()->sum('amount'))->toBe(100_000_000.0)
        ->and((float) BudgetActivity::query()->where('budget_id', $budget->id)->sum('amount'))->toBe(60_000_000.0)
        ->and((float) $business->incomes()->sum('amount') - (float) BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $business->id))
            ->sum('amount'))->toBe(40_000_000.0);

    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Pemasukan')
        ->assertSee('Rp 100.000.000')
        ->assertSee('Biaya operasional')
        ->assertSee('Rp 60.000.000')
        ->assertSee('Laba / Rugi')
        ->assertSee('Rp 40.000.000')
        ->assertSee('Realisasi anggaran')
        ->assertSee('Prive / Owner Withdrawal')
        ->assertSee('Rp 10.000.000');

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Penerimaan dari Prive Usaha')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Pengeluaran')
        ->assertSee('Rp 0');

    $this->get(route('entity.profit-loss.index', $business))
        ->assertOk()
        ->assertSee('Laba: Rp 40.000.000')
        ->assertSee('Prive: Rp 10.000.000')
        ->assertSee('tidak masuk laba');
});

it('rejects a private user who lacks destination FAMILY capability', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Terkunci']);
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Usaha', 5_000_000);
    $destination = cashAccount($family, 'Kas Family', 0);

    grantEntityAccess($business);

    $this->get(route('entity.owner-withdrawals.create', $business))
        ->assertOk()
        ->assertDontSee('Keluarga Terkunci');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '100000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('family_public_id');

    expect(OwnerWithdrawal::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(5_000_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0);
});

it('rejects invalid source destination account entity and amount combinations', function () {
    $family = FinanceEntity::factory()->family()->create();
    $otherFamily = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $otherBusiness = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Sumber Usaha', 100_000);
    $destination = cashAccount($family, 'Kas Family', 0);
    $familyDest = cashAccount($otherFamily, 'Kas Family Lain', 0);
    $foreignSource = cashAccount($otherBusiness, 'Kas Usaha Lain', 100_000);
    $inactiveDest = app(FinanceAccountService::class)->create($family, [
        'name' => 'Kas Lama Family',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    app(FinanceAccountService::class)->deactivate($inactiveDest);
    $inactiveSource = app(FinanceAccountService::class)->create($business, [
        'name' => 'Kas Lama Usaha',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 50_000,
    ]);
    app(FinanceAccountService::class)->deactivate($inactiveSource);

    grantEntityAccess($business);
    grantEntityAccess($family);
    grantEntityAccess($otherFamily);

    $this->post(route('entity.owner-withdrawals.store', $family), [
        'source_account_id' => $destination->id,
        'family_public_id' => $business->public_id,
        'destination_account_id' => $source->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertNotFound();

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $otherBusiness->public_id,
        'destination_account_id' => $foreignSource->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('family_public_id');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $familyDest->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $inactiveDest->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $inactiveSource->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('source_account_id');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '150000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '0',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $family->update(['is_active' => false]);
    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('family_public_id');

    expect(OwnerWithdrawal::query()->count())->toBe(0)
        ->and(Route::has('entity.owner-withdrawals.edit'))->toBeFalse()
        ->and(Route::has('entity.owner-withdrawals.destroy'))->toBeFalse();
});

it('allows an admin to create a withdrawal without a private token', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Family Admin Prive']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Admin Prive']);
    $source = cashAccount($business, 'Kas Admin Usaha', 2_000_000);
    $destination = cashAccount($family, 'BCA Admin', 100_000);

    actingAdmin()
        ->get(route('admin.finance-entities.owner-withdrawals.index', $business))
        ->assertOk()
        ->assertSee('Prive / Owner Withdrawal');

    actingAdmin()
        ->post(route('admin.finance-entities.owner-withdrawals.store', $business), [
            'source_account_id' => $source->id,
            'family_public_id' => $family->public_id,
            'destination_account_id' => $destination->id,
            'amount' => '750000',
            'transaction_date' => now()->toDateString(),
            'description' => 'Prive admin',
        ])
        ->assertRedirect(route('admin.finance-entities.owner-withdrawals.index', $business));

    expect(balanceService()->balance($source->fresh()))->toBe(1_250_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(850_000.0)
        ->and((float) $business->incomes()->sum('amount'))->toBe(0.0)
        ->and((float) BudgetActivity::query()
            ->whereHas('budget', fn ($query) => $query->where('finance_entity_id', $business->id))
            ->sum('amount'))->toBe(0.0);
});

it('rolls back a withdrawal when the wrapping transaction fails', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Atomic Usaha', 400_000);
    $destination = cashAccount($family, 'Kas Atomic Family', 0);

    expect(function () use ($family, $business, $source, $destination): void {
        DB::transaction(function () use ($family, $business, $source, $destination): void {
            app(OwnerWithdrawalService::class)->create($business, $family, [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => 80_000,
                'transaction_date' => now()->toDateString(),
            ]);

            throw new RuntimeException('force rollback');
        });
    })->toThrow(RuntimeException::class);

    expect(OwnerWithdrawal::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(400_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0);
});

it('rejects service-level withdrawal from a FAMILY source or to a BUSINESS destination', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Kas Family Svc', 50_000);
    $businessAccount = cashAccount($business, 'Kas Usaha Svc', 50_000);

    expect(fn () => app(OwnerWithdrawalService::class)->create($family, $business, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(OwnerWithdrawalService::class)->create($business, $business, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 10_000,
        'transaction_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(OwnerWithdrawal::query()->count())->toBe(0);
});

it('detects invalid withdrawal records in the read-only account audit', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Audit Family Prive', 0);
    $businessAccount = cashAccount($business, 'Audit Usaha Prive', 100_000);

    $business->ownerWithdrawalsGiven()->create([
        'source_account_id' => $familyAccount->id,
        'family_entity_id' => $family->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 10_000,
        'transaction_date' => now(),
        'description' => 'Account mismatch',
    ]);

    DB::table('owner_withdrawals')->insert([
        'public_id' => (string) Str::ulid(),
        'business_entity_id' => $family->id,
        'source_account_id' => $familyAccount->id,
        'family_entity_id' => $business->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 5_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Wrong types',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('owner_withdrawals')->insert([
        'public_id' => (string) Str::ulid(),
        'business_entity_id' => $business->id,
        'source_account_id' => $businessAccount->id,
        'family_entity_id' => $business->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 0,
        'transaction_date' => now()->toDateString(),
        'description' => 'Same entity zero',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = OwnerWithdrawal::query()->count();

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Owner Withdrawal Audit')
        ->expectsOutputToContain('Source is not BUSINESS')
        ->expectsOutputToContain('Destination is not FAMILY')
        ->expectsOutputToContain('Orphan withdrawal records')
        ->assertFailed();

    $audit = app(OwnerWithdrawalService::class)->audit();

    expect(OwnerWithdrawal::query()->count())->toBe($before)
        ->and($audit['source_not_business'])->toBeGreaterThan(0)
        ->and($audit['destination_not_family'])->toBeGreaterThan(0)
        ->and($audit['account_entity_mismatch'])->toBeGreaterThan(0)
        ->and($audit['non_positive_amount'])->toBeGreaterThan(0)
        ->and($audit['same_source_and_destination'])->toBeGreaterThan(0)
        ->and($audit)->toHaveKeys(['orphan_withdrawals', 'invalid_account_relation']);
});
