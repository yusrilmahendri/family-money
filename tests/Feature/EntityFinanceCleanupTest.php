<?php

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Saldo;
use App\Models\Transaction;
use App\Services\SaldoGlobalService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not create a saldos row when entity Income is stored', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = cashAccount($entity, 'Kas Usaha', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    grantEntityAccess($entity);

    $this->post(route('entity.incomes.store', $entity), [
        'source' => 'Panen entity',
        'amount' => '250000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('incomes', [
        'source' => 'Panen entity',
        'finance_entity_id' => $entity->id,
    ]);
    expect(Saldo::query()->count())->toBe(0)
        ->and(Saldo::query()->whereNotNull('income_id')->count())->toBe(0)
        ->and(balanceService()->balance($account->fresh()))->toBe(250_000.0);
});

it('adds Income to account balance and subtracts Transaction and BudgetActivity only', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = cashAccount($entity, 'Kas Operasi', 5_000_000);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    grantEntityAccess($entity);

    $this->post(route('entity.incomes.store', $entity), [
        'source' => 'Penjualan',
        'amount' => '500000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect();
    expect(balanceService()->balance($account->fresh()))->toBe(5_500_000.0);

    $this->post(route('entity.budgets.store', $entity), [
        'amount' => '10000000',
        'periode' => now()->toDateString(),
        'category_id' => $category->id,
        'description' => 'Pupuk',
    ])->assertRedirect();

    $budget = Budget::query()->where('finance_entity_id', $entity->id)->first();
    expect(balanceService()->balance($account->fresh()))->toBe(5_500_000.0)
        ->and($budget->plannedAmount())->toBe(10_000_000.0)
        ->and($budget->realizedAmount())->toBe(0.0)
        ->and($budget->remainingAmount())->toBe(10_000_000.0);

    $this->put(route('entity.budgets.update', [$entity, $budget]), [
        'amount' => '12000000',
        'periode' => now()->toDateString(),
        'category_id' => $category->id,
        'description' => 'Pupuk revisi',
    ])->assertRedirect();
    expect(balanceService()->balance($account->fresh()))->toBe(5_500_000.0)
        ->and($budget->fresh()->plannedAmount())->toBe(12_000_000.0);

    $this->post(route('entity.operational.store', $entity), [
        'budget_id' => $budget->id,
        'name' => 'Beli pupuk',
        'amount' => '4000000',
        'activity_date' => now()->toDateString(),
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    $budget->refresh();
    expect(balanceService()->balance($account->fresh()))->toBe(1_500_000.0)
        ->and($budget->realizedAmount())->toBe(4_000_000.0)
        ->and($budget->remainingAmount())->toBe(8_000_000.0)
        ->and($budget->varianceAmount())->toBe(8_000_000.0);
});

it('reduces FAMILY account balance when a Transaction is created', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Rumah', 2_000_000);
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '150000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja',
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    expect(balanceService()->balance($account->fresh()))->toBe(1_850_000.0);
});

it('computes profit from Income minus actual operational expense and ignores opening and planned budget', function () {
    $entity = FinanceEntity::factory()->business()->create(['name' => 'Kebun Laba']);
    $account = cashAccount($entity, 'Kas Laba', 1_000_000);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'Sawit',
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    grantEntityAccess($entity);

    Income::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => 500_000,
        'income_date' => now(),
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => $category->id,
        'amount' => 10_000_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $account->id,
        'name' => 'Pupuk',
        'amount' => 200_000,
        'activity_date' => now(),
    ]);

    expect(balanceService()->balance($account->fresh()))->toBe(1_300_000.0);

    $this->get(route('entity.profit-loss.index', $entity))
        ->assertOk()
        ->assertSee('Pemasukan: Rp 500.000')
        ->assertSee('Biaya operasional: Rp 200.000')
        ->assertSee('Laba: Rp 300.000')
        ->assertSee('Saldo awal kas tidak masuk laba')
        ->assertDontSee('Rp 1.000.000')
        ->assertDontSee('Rp 10.000.000');
});

it('keeps entity dashboard and reports independent from SaldoGlobalService', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Dashboard']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Dashboard']);
    $familyAccount = cashAccount($family, 'Kas Rumah', 800_000);
    $businessAccount = cashAccount($business, 'Kas Usaha', 300_000);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);

    Transaction::factory()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $familyAccount->id,
        'amount' => 50_000,
        'description' => 'Belanja dashboard',
    ]);
    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $businessAccount->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen dashboard',
        'amount' => 120_000,
        'income_date' => now(),
    ]);
    Saldo::query()->create([
        'category_id' => $category->id,
        'amount' => 9_876_543,
        'description' => 'Legacy global noise',
        'periode_saldo' => now(),
    ]);

    grantEntityAccess($family);
    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Total Saldo')
        ->assertSee('Pemasukan')
        ->assertSee('Pengeluaran')
        ->assertSee('Rp 750.000')
        ->assertDontSee('9.876.543')
        ->assertDontSee('SaldoGlobalService');

    grantEntityAccess($business);
    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Total Saldo')
        ->assertSee('Rp 420.000')
        ->assertSee('Laba / Rugi')
        ->assertSee('Realisasi anggaran')
        ->assertSee('Sisa anggaran')
        ->assertDontSee('9.876.543');

    $this->get(route('entity.profit-loss.index', $business))
        ->assertOk()
        ->assertSee('Laba: Rp 120.000')
        ->assertDontSee('9.876.543');

    $this->get(route('entity.budgets.index', $business))->assertOk()->assertDontSee('9.876.543');
    $this->get(route('entity.operational.index', $business))->assertOk()->assertDontSee('9.876.543');
    $this->get(route('entity.incomes.index', $business))
        ->assertOk()
        ->assertSee('Panen dashboard')
        ->assertDontSee('9.876.543');

    expect(app(SaldoGlobalService::class)->getSaldoGlobal())->not->toBe(750_000.0);
});

it('does not double-count entity Income against saldos or account balance', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = cashAccount($entity, 'Kas Anti Dobel', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    grantEntityAccess($entity);

    $this->post(route('entity.incomes.store', $entity), [
        'source' => 'Sekali saja',
        'amount' => '250000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    Saldo::query()->create([
        'category_id' => $category->id,
        'amount' => 7_000_000,
        'description' => 'Manual legacy',
        'periode_saldo' => now(),
    ]);

    expect((float) Income::query()->sum('amount'))->toBe(250_000.0)
        ->and((float) Saldo::query()->whereNotNull('income_id')->sum('amount'))->toBe(0.0)
        ->and((float) Saldo::query()->sum('amount'))->toBe(7_000_000.0)
        ->and(balanceService()->balance($account->fresh()))->toBe(250_000.0)
        ->and(app(SaldoGlobalService::class)->getTotalIncome())->toBe(7_250_000.0);
});

it('does not write saldos from the retired legacy income route', function () {
    $this->post(route('incomes.store'), [
        'source' => 'Legacy panen',
        'amount' => '150000',
        'income_date' => now()->toDateString(),
    ])->assertRedirect(route('home'));

    expect(Income::query()->where('source', 'Legacy panen')->exists())->toBeFalse()
        ->and(Saldo::query()->whereNotNull('income_id')->count())->toBe(0);
});

it('rejects zero amounts on entity Income Transaction Budget and BudgetActivity', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = cashAccount($family, 'Kas Nol', 100_000);
    $businessAccount = cashAccount($business, 'Kas Nol Usaha', 100_000);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => 1_000_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);

    grantEntityAccess($family);
    $this->post(route('entity.transactions.store', $family), [
        'amount' => '0',
        'transaction_date' => now()->toDateString(),
        'description' => 'Nol',
        'finance_account_id' => $familyAccount->id,
    ])->assertSessionHasErrors('amount');

    grantEntityAccess($business);
    $this->post(route('entity.incomes.store', $business), [
        'source' => 'Nol',
        'amount' => '0',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $businessAccount->id,
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.budgets.store', $business), [
        'amount' => '0',
        'periode' => now()->toDateString(),
        'category_id' => $category->id,
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.operational.store', $business), [
        'budget_id' => $budget->id,
        'name' => 'Nol',
        'amount' => '0',
        'activity_date' => now()->toDateString(),
        'finance_account_id' => $businessAccount->id,
    ])->assertSessionHasErrors('amount');
});
