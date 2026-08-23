<?php

use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\FinanceAccountMovementMigrator;
use App\Services\FinanceAccountService;
use App\Services\RecurringTransactionRunner;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function movementAccount(FinanceEntity $entity, string $name = 'Kas Utama'): FinanceAccount
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
    ]);
}

it('maps existing money movements to the owning entity default account without losing rows', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Map']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Map']);
    $familyAccount = app(FinanceAccountService::class)->ensureDefaultAccount($family);
    $businessAccount = app(FinanceAccountService::class)->ensureDefaultAccount($business);

    $transaction = Transaction::factory()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => null,
        'description' => 'Unmapped trx',
    ]);
    $income = Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => null,
        'category_id' => Category::factory()->create(['finance_entity_id' => $business->id])->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Unmapped income',
        'amount' => 100000,
        'income_date' => now(),
    ]);
    $debt = Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Hutang map',
        'principal_total' => 500000,
        'remaining_balance' => 500000,
    ]);
    $payment = DebtPayment::query()->create([
        'debt_id' => $debt->id,
        'amount' => 25000,
        'paid_on' => now(),
    ]);
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Goal map',
        'target_amount' => 1000000,
    ]);
    $contribution = GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'amount' => 20000,
        'contributed_on' => now(),
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $business->id])->id,
        'amount' => 500000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    $activity = BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'name' => 'Pupuk map',
        'amount' => 15000,
        'activity_date' => now(),
    ]);
    $recurring = RecurringTransaction::query()->create([
        'finance_entity_id' => $family->id,
        'name' => 'Recurring map',
        'amount' => 10000,
        'frequency' => 'monthly',
        'start_date' => now(),
        'next_due' => now(),
        'active' => true,
    ]);

    $before = [
        'transactions' => Transaction::query()->count(),
        'incomes' => Income::query()->count(),
        'debt_payments' => DebtPayment::query()->count(),
        'goal_contributions' => GoalContribution::query()->count(),
        'budget_activities' => BudgetActivity::query()->count(),
        'recurring_transactions' => RecurringTransaction::query()->count(),
    ];

    app(FinanceAccountMovementMigrator::class)->backfill();

    expect(Transaction::query()->count())->toBe($before['transactions'])
        ->and(Income::query()->count())->toBe($before['incomes'])
        ->and(DebtPayment::query()->count())->toBe($before['debt_payments'])
        ->and(GoalContribution::query()->count())->toBe($before['goal_contributions'])
        ->and(BudgetActivity::query()->count())->toBe($before['budget_activities'])
        ->and(RecurringTransaction::query()->count())->toBe($before['recurring_transactions'])
        ->and($transaction->fresh()->finance_account_id)->toBe($familyAccount->id)
        ->and($payment->fresh()->finance_account_id)->toBe($familyAccount->id)
        ->and($contribution->fresh()->finance_account_id)->toBe($familyAccount->id)
        ->and($recurring->fresh()->finance_account_id)->toBe($familyAccount->id)
        ->and($income->fresh()->finance_account_id)->toBe($businessAccount->id)
        ->and($activity->fresh()->finance_account_id)->toBe($businessAccount->id)
        ->and($transaction->fresh()->finance_account_id)->not->toBe($businessAccount->id)
        ->and($income->fresh()->finance_account_id)->not->toBe($familyAccount->id);

    app(FinanceAccountMovementMigrator::class)->backfill();

    expect($transaction->fresh()->finance_account_id)->toBe($familyAccount->id);
});

it('stores a FAMILY transaction only on an account owned by the route entity', function () {
    [$entityA, $entityB] = familyPair();
    $accountA = movementAccount($entityA, 'Kas A');
    $accountB = movementAccount($entityB, 'Kas B');
    grantEntityAccess($entityA);

    $this->post(route('entity.transactions.store', $entityA), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja kas A',
        'finance_account_id' => $accountB->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.transactions.store', $entityA), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja kas A',
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'description' => 'Belanja kas A',
        'finance_entity_id' => $entityA->id,
        'finance_account_id' => $accountA->id,
    ]);
    expect(Transaction::query()->where('finance_account_id', $accountB->id)->count())->toBe(0);
});

it('stores a BUSINESS income only on an account owned by the route entity', function () {
    [$entityA, $entityB] = businessPair();
    $accountA = movementAccount($entityA, 'Kas Usaha A');
    $accountB = movementAccount($entityB, 'Kas Usaha B');
    $categoryA = Category::factory()->create([
        'finance_entity_id' => $entityA->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    grantEntityAccess($entityA);

    $this->post(route('entity.incomes.store', $entityA), [
        'source' => 'Panen',
        'amount' => '300000',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
        'finance_account_id' => $accountB->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.incomes.store', $entityA), [
        'source' => 'Panen',
        'amount' => '300000',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('incomes', [
        'source' => 'Panen',
        'finance_entity_id' => $entityA->id,
        'finance_account_id' => $accountA->id,
    ]);
});

it('isolates debt payments and goal contributions to the parent entity account', function () {
    [$entityA, $entityB] = familyPair();
    $accountA = movementAccount($entityA, 'Kas A');
    $accountB = movementAccount($entityB, 'Kas B');
    $debt = Debt::query()->create([
        'finance_entity_id' => $entityA->id,
        'title' => 'Hutang A',
        'principal_total' => 400000,
        'remaining_balance' => 400000,
    ]);
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $entityA->id,
        'title' => 'Goal A',
        'target_amount' => 1000000,
    ]);
    grantEntityAccess($entityA);

    $this->post(route('entity.debts.payments.store', [$entityA, $debt]), [
        'amount' => '10000',
        'paid_on' => now()->toDateString(),
        'finance_account_id' => $accountB->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.debts.payments.store', [$entityA, $debt]), [
        'amount' => '10000',
        'paid_on' => now()->toDateString(),
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $this->post(route('entity.savings-goals.contributions.store', [$entityA, $goal]), [
        'amount' => '15000',
        'contributed_on' => now()->toDateString(),
        'finance_account_id' => $accountB->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.savings-goals.contributions.store', [$entityA, $goal]), [
        'amount' => '15000',
        'contributed_on' => now()->toDateString(),
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('debt_payments', [
        'debt_id' => $debt->id,
        'finance_account_id' => $accountA->id,
    ]);
    $this->assertDatabaseHas('goal_contributions', [
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $accountA->id,
    ]);
    expect(DebtPayment::query()->where('finance_account_id', $accountB->id)->count())->toBe(0)
        ->and(GoalContribution::query()->where('finance_account_id', $accountB->id)->count())->toBe(0);
});

it('isolates budget activities to the business entity account', function () {
    [$entityA, $entityB] = businessPair();
    $accountA = movementAccount($entityA, 'Kas Usaha A');
    $accountB = movementAccount($entityB, 'Kas Usaha B');
    $budget = Budget::query()->create([
        'finance_entity_id' => $entityA->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityA->id])->id,
        'amount' => 1000000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    grantEntityAccess($entityA);

    $this->post(route('entity.operational.store', $entityA), [
        'budget_id' => $budget->id,
        'name' => 'Pupuk',
        'amount' => '20000',
        'activity_date' => now()->toDateString(),
        'finance_account_id' => $accountB->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.operational.store', $entityA), [
        'budget_id' => $budget->id,
        'name' => 'Pupuk',
        'amount' => '20000',
        'activity_date' => now()->toDateString(),
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('budget_activities', [
        'budget_id' => $budget->id,
        'name' => 'Pupuk',
        'finance_account_id' => $accountA->id,
    ]);
});

it('rejects an inactive account on a new transaction', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $active = movementAccount($entity, 'Kas Aktif');
    $inactive = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Lama',
        'type' => FinanceAccountType::CASH,
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Tidak boleh',
        'finance_account_id' => $inactive->id,
    ])->assertSessionHasErrors('finance_account_id');

    expect(Transaction::query()->where('description', 'Tidak boleh')->count())->toBe(0)
        ->and($active->fresh()->is_active)->toBeTrue();
});

it('copies the recurring account onto the posted transaction and rejects a foreign account', function () {
    [$entityA, $entityB] = familyPair();
    $accountA = movementAccount($entityA, 'Kas Recurring A');
    $accountB = movementAccount($entityB, 'Kas Recurring B');
    grantEntityAccess($entityA);

    $this->post(route('entity.recurring.store', $entityA), [
        'name' => 'BPJS',
        'amount' => '150000',
        'frequency' => 'monthly',
        'start_date' => now()->toDateString(),
        'finance_account_id' => $accountB->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.recurring.store', $entityA), [
        'name' => 'BPJS',
        'amount' => '150000',
        'frequency' => 'monthly',
        'start_date' => now()->subMonth()->toDateString(),
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $recurring = RecurringTransaction::query()->where('name', 'BPJS')->first();
    $recurring->update(['next_due' => now()->toDateString(), 'active' => true]);

    expect(app(RecurringTransactionRunner::class)->runDue())->toBeGreaterThan(0);

    $this->assertDatabaseHas('transactions', [
        'description' => '[Otomatis] BPJS',
        'finance_entity_id' => $entityA->id,
        'finance_account_id' => $accountA->id,
    ]);
    expect(Transaction::query()->where('finance_account_id', $accountB->id)->count())->toBe(0);
});

it('skips recurring posting when the stored account is inactive and does not switch account', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = movementAccount($entity, 'Kas Recurring');
    $other = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Pengganti',
        'type' => FinanceAccountType::CASH,
    ]);
    $recurring = RecurringTransaction::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'name' => 'Listrik',
        'amount' => 80000,
        'frequency' => 'monthly',
        'start_date' => now()->subMonth(),
        'next_due' => now()->toDateString(),
        'active' => true,
    ]);
    $due = $recurring->next_due->toDateString();

    app(FinanceAccountService::class)->deactivate($account);

    expect(app(RecurringTransactionRunner::class)->runDue())->toBe(0)
        ->and($recurring->fresh()->next_due->toDateString())->toBe($due)
        ->and(Transaction::query()->where('description', '[Otomatis] Listrik')->count())->toBe(0)
        ->and($recurring->fresh()->finance_account_id)->toBe($account->id)
        ->and($recurring->fresh()->finance_account_id)->not->toBe($other->id);
});

it('uses the entity default account when a private or legacy request omits finance_account_id', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Fallback']);
    $default = app(FinanceAccountService::class)->ensureDefaultAccount($entity);
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '12000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Tanpa account di request',
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'description' => 'Tanpa account di request',
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $default->id,
    ]);

    $this->post(route('transactions.store'), [
        'total' => '15000',
        'date' => now()->toDateString(),
        'description' => 'Legacy tanpa account',
    ])->assertRedirect(route('home'));

    $this->assertDatabaseMissing('transactions', [
        'description' => 'Legacy tanpa account',
    ]);
});

it('fails the account audit when a financial record has no account', function () {
    $entity = FinanceEntity::factory()->family()->create();
    app(FinanceAccountService::class)->ensureDefaultAccount($entity);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => null,
        'description' => 'Tanpa account',
    ]);

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Financial records without account')
        ->assertFailed();

    expect(Transaction::query()->where('description', 'Tanpa account')->value('finance_account_id'))->toBeNull();
});
