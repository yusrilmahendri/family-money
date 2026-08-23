<?php

use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\FinanceEntity;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\Saldo;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function balanceService(): FinanceAccountBalanceService
{
    return app(FinanceAccountBalanceService::class);
}

function cashAccount(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function balanceGrantAccess(FinanceEntity $entity): void
{
    [, $plain] = app(\App\Services\FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

it('includes opening balance and applies income inflow and cash outflows', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Rumah', 2_000_000);

    expect(balanceService()->balance($account))->toBe(2_000_000.0);

    Income::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entity->id])->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Setoran',
        'amount' => 500_000,
        'income_date' => now(),
    ]);
    expect(balanceService()->balance($account->fresh()))->toBe(2_500_000.0);

    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 100_000,
    ]);
    expect(balanceService()->balance($account->fresh()))->toBe(2_400_000.0);

    $debt = Debt::query()->create([
        'finance_entity_id' => $entity->id,
        'title' => 'Hutang',
        'principal_total' => 300_000,
        'remaining_balance' => 300_000,
    ]);
    DebtPayment::query()->create([
        'debt_id' => $debt->id,
        'finance_account_id' => $account->id,
        'amount' => 50_000,
        'paid_on' => now(),
    ]);
    expect(balanceService()->balance($account->fresh()))->toBe(2_350_000.0);

    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $entity->id,
        'title' => 'Goal',
        'target_amount' => 1_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $account->id,
        'amount' => 25_000,
        'contributed_on' => now(),
    ]);
    expect(balanceService()->balance($account->fresh()))->toBe(2_325_000.0);

    $budget = Budget::query()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entity->id])->id,
        'amount' => 800_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    expect(balanceService()->balance($account->fresh()))->toBe(2_325_000.0);

    BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $account->id,
        'name' => 'Biaya',
        'amount' => 75_000,
        'activity_date' => now(),
    ]);
    expect(balanceService()->balance($account->fresh()))->toBe(2_250_000.0);
});

it('keeps account and entity balances isolated', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    $kasA = cashAccount($entityA, 'Kas A', 1_000_000);
    $bcaA = app(FinanceAccountService::class)->create($entityA, [
        'name' => 'BCA A',
        'type' => FinanceAccountType::BANK,
        'opening_balance' => 3_000_000,
    ]);
    $kasB = cashAccount($entityB, 'Kas B', 9_000_000);

    Transaction::factory()->create([
        'finance_entity_id' => $entityA->id,
        'finance_account_id' => $kasA->id,
        'amount' => 200_000,
        'description' => 'Keluar A',
    ]);
    Income::query()->create([
        'finance_entity_id' => $entityB->id,
        'finance_account_id' => $kasB->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityB->id])->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Masuk B',
        'amount' => 400_000,
        'income_date' => now(),
    ]);

    expect(balanceService()->balance($kasA->fresh()))->toBe(800_000.0)
        ->and(balanceService()->balance($bcaA->fresh()))->toBe(3_000_000.0)
        ->and(balanceService()->balance($kasB->fresh()))->toBe(9_400_000.0)
        ->and(balanceService()->balanceForEntity($entityA))->toBe(3_800_000.0)
        ->and(balanceService()->balanceForEntity($entityB))->toBe(9_400_000.0);
});

it('still counts an inactive account in entity totals', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $active = cashAccount($entity, 'Kas Aktif', 100_000);
    $inactive = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Lama',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 50_000,
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $inactive->id,
        'amount' => 10_000,
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);

    expect($inactive->fresh()->is_active)->toBeFalse()
        ->and(balanceService()->balance($inactive->fresh()))->toBe(40_000.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe(140_000.0)
        ->and(balanceService()->balance($active->fresh()))->toBe(100_000.0);
});

it('does not double-count Income to Saldo sync or manual saldos', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = cashAccount($entity, 'Kas Usaha', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    balanceGrantAccess($entity);

    $this->post(route('entity.incomes.store', $entity), [
        'source' => 'Panen',
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

    expect(Saldo::query()->whereNotNull('income_id')->count())->toBe(0)
        ->and(balanceService()->balance($account->fresh()))->toBe(250_000.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe(250_000.0);
});

it('shows account-based total saldo on the entity dashboard and account list', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Saldo']);
    $account = cashAccount($entity, 'Kas Rumah', 1_250_000);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 250_000,
    ]);
    balanceGrantAccess($entity);

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Total Saldo')
        ->assertSee('Rp 1.000.000')
        ->assertDontSee('Rp 7.000.000');

    $this->get(route('entity.accounts.index', $entity))
        ->assertOk()
        ->assertSee('Kas Rumah')
        ->assertSee('Rp 1.000.000')
        ->assertDontSee('Rp 1.250.000')
        ->assertSee('Default')
        ->assertSee('Aktif');
});

it('keeps the balance audit command read-only', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Audit', 10_000);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 1_000,
        'description' => 'Audit tetap',
    ]);

    $this->artisan('finance:balance-audit')
        ->expectsOutputToContain('Finance Account Balance Audit')
        ->expectsOutputToContain('Opening Balance')
        ->expectsOutputToContain('Legacy Global Balance')
        ->expectsOutputToContain('New Account-Based Balance')
        ->expectsOutputToContain('Sources of difference')
        ->expectsOutputToContain('No data was written.')
        ->expectsOutputToContain('Cross-entity event reconciliation')
        ->expectsOutputToContain('Account formula and cross-entity amounts are consistent.')
        ->assertSuccessful();

    expect($account->fresh()->opening_balance)->toBe('10000.00')
        ->and(Transaction::query()->where('description', 'Audit tetap')->count())->toBe(1)
        ->and((float) Transaction::query()->where('description', 'Audit tetap')->value('amount'))->toBe(1000.0);
});
