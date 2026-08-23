<?php

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\RecurringTransactionRunner;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantEntityAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function familyPair(): array
{
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);

    return [$entityA, $entityB];
}

function businessPair(): array
{
    $entityA = FinanceEntity::factory()->business()->create(['name' => 'Usaha A']);
    $entityB = FinanceEntity::factory()->business()->create(['name' => 'Usaha B']);

    return [$entityA, $entityB];
}

it('shows only the route entity transactions and hides the other entity', function () {
    [$entityA, $entityB] = familyPair();
    Transaction::factory()->create([
        'finance_entity_id' => $entityA->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityA->id])->id,
        'description' => 'Transaksi Keluarga A',
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entityB->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityB->id])->id,
        'description' => 'Transaksi Keluarga B',
    ]);

    grantEntityAccess($entityA);

    $this->get(route('entity.transactions.index', $entityA))
        ->assertOk()
        ->assertSee('Transaksi Keluarga A')
        ->assertDontSee('Transaksi Keluarga B');
});

it('creates a transaction owned by the route entity and ignores a forged finance_entity_id', function () {
    [$entityA, $entityB] = familyPair();
    grantEntityAccess($entityA);

    $this->post(route('entity.transactions.store', $entityA), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja A',
        'finance_entity_id' => $entityB->id,
    ])->assertSessionHasErrors('finance_entity_id');

    $this->post(route('entity.transactions.store', $entityA), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja A',
    ])->assertRedirect(route('entity.transactions.index', $entityA));

    $this->assertDatabaseHas('transactions', [
        'description' => 'Belanja A',
        'finance_entity_id' => $entityA->id,
        'context' => FinanceContext::PRIBADI,
    ]);
    expect(Transaction::query()->where('finance_entity_id', $entityB->id)->count())->toBe(0);
});

it('returns 404 when editing updating or deleting another entity transaction', function () {
    [$entityA, $entityB] = familyPair();
    $foreign = Transaction::factory()->create([
        'finance_entity_id' => $entityB->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityB->id])->id,
        'description' => 'Milik B',
    ]);
    grantEntityAccess($entityA);
    grantEntityAccess($entityB);

    $this->get(route('entity.transactions.edit', [$entityA, $foreign]))->assertNotFound();
    $this->put(route('entity.transactions.update', [$entityA, $foreign]), [
        'amount' => '1000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Hacked',
    ])->assertNotFound();
    $this->delete(route('entity.transactions.destroy', [$entityA, $foreign]))->assertNotFound();

    expect($foreign->fresh()->description)->toBe('Milik B');
});

it('isolates incomes the same way as transactions', function () {
    [$entityA, $entityB] = businessPair();
    $categoryA = Category::factory()->create([
        'finance_entity_id' => $entityA->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $categoryB = Category::factory()->create([
        'finance_entity_id' => $entityB->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $incomeA = Income::query()->create([
        'finance_entity_id' => $entityA->id,
        'category_id' => $categoryA->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen A',
        'amount' => 100000,
        'income_date' => now(),
    ]);
    $incomeB = Income::query()->create([
        'finance_entity_id' => $entityB->id,
        'category_id' => $categoryB->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen B',
        'amount' => 200000,
        'income_date' => now(),
    ]);

    grantEntityAccess($entityA);
    grantEntityAccess($entityB);

    $this->get(route('entity.incomes.index', $entityA))
        ->assertOk()
        ->assertSee('Panen A')
        ->assertDontSee('Panen B');

    $this->post(route('entity.incomes.store', $entityA), [
        'source' => 'Panen baru',
        'amount' => '300000',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
        'finance_entity_id' => $entityB->id,
    ])->assertSessionHasErrors('finance_entity_id');

    $this->post(route('entity.incomes.store', $entityA), [
        'source' => 'Panen baru',
        'amount' => '300000',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('incomes', [
        'source' => 'Panen baru',
        'finance_entity_id' => $entityA->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);

    $this->get(route('entity.incomes.edit', [$entityA, $incomeB]))->assertNotFound();
    $this->put(route('entity.incomes.update', [$entityA, $incomeB]), [
        'source' => 'Hacked',
        'amount' => '1',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
    ])->assertNotFound();
    $this->delete(route('entity.incomes.destroy', [$entityA, $incomeB]))->assertNotFound();
    expect($incomeB->fresh()->source)->toBe('Panen B');
    expect($incomeA->fresh()->source)->toBe('Panen A');
});

it('isolates budgets debts savings goals and recurring transactions', function () {
    [$familyA, $familyB] = familyPair();
    [$businessA, $businessB] = businessPair();

    $budgetCategoryA = Category::factory()->create(['finance_entity_id' => $businessA->id, 'context' => FinanceContext::USAHA_KEBUN]);
    $budgetCategoryB = Category::factory()->create(['finance_entity_id' => $businessB->id, 'context' => FinanceContext::USAHA_KEBUN]);
    $budgetB = Budget::query()->create([
        'finance_entity_id' => $businessB->id,
        'category_id' => $budgetCategoryB->id,
        'amount' => 1000000,
        'amount_saldo' => 0,
        'periode' => now(),
        'description' => 'Anggaran B',
    ]);
    $debtB = Debt::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'Hutang B',
        'principal_total' => 500000,
        'remaining_balance' => 500000,
    ]);
    $goalB = SavingsGoal::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'Goal B',
        'target_amount' => 1000000,
    ]);
    $recurringB = RecurringTransaction::query()->create([
        'finance_entity_id' => $familyB->id,
        'name' => 'Recurring B',
        'amount' => 50000,
        'frequency' => 'monthly',
        'start_date' => now(),
        'next_due' => now(),
        'active' => true,
    ]);

    grantEntityAccess($familyA);
    grantEntityAccess($familyB);
    grantEntityAccess($businessA);
    grantEntityAccess($businessB);

    $this->post(route('entity.debts.store', $familyA), [
        'title' => 'Hutang A',
        'principal_total' => '400000',
        'finance_entity_id' => $familyB->id,
    ])->assertSessionHasErrors('finance_entity_id');
    $this->post(route('entity.debts.store', $familyA), [
        'title' => 'Hutang A',
        'principal_total' => '400000',
    ])->assertRedirect();
    $this->assertDatabaseHas('debts', ['title' => 'Hutang A', 'finance_entity_id' => $familyA->id]);
    $this->get(route('entity.debts.edit', [$familyA, $debtB]))->assertNotFound();
    $this->delete(route('entity.debts.destroy', [$familyA, $debtB]))->assertNotFound();

    $this->post(route('entity.savings-goals.store', $familyA), [
        'title' => 'Goal A',
        'target_amount' => '2000000',
    ])->assertRedirect();
    $this->assertDatabaseHas('savings_goals', ['title' => 'Goal A', 'finance_entity_id' => $familyA->id]);
    $this->get(route('entity.savings-goals.edit', [$familyA, $goalB]))->assertNotFound();
    $this->delete(route('entity.savings-goals.destroy', [$familyA, $goalB]))->assertNotFound();

    $this->post(route('entity.recurring.store', $familyA), [
        'name' => 'Recurring A',
        'amount' => '75000',
        'frequency' => 'monthly',
        'start_date' => now()->toDateString(),
    ])->assertRedirect();
    $this->assertDatabaseHas('recurring_transactions', ['name' => 'Recurring A', 'finance_entity_id' => $familyA->id]);
    $this->get(route('entity.recurring.edit', [$familyA, $recurringB]))->assertNotFound();
    $this->delete(route('entity.recurring.destroy', [$familyA, $recurringB]))->assertNotFound();

    $this->post(route('entity.budgets.store', $businessA), [
        'amount' => '2500000',
        'periode' => now()->toDateString(),
        'category_id' => $budgetCategoryA->id,
    ])->assertRedirect();
    $this->assertDatabaseHas('budgets', ['category_id' => $budgetCategoryA->id, 'finance_entity_id' => $businessA->id]);
    $this->get(route('entity.budgets.edit', [$businessA, $budgetB]))->assertNotFound();
    $this->delete(route('entity.budgets.destroy', [$businessA, $budgetB]))->assertNotFound();
    expect($debtB->fresh())->not->toBeNull()
        ->and($goalB->fresh())->not->toBeNull()
        ->and($recurringB->fresh())->not->toBeNull()
        ->and($budgetB->fresh())->not->toBeNull();
});

it('rejects child resources that belong to another entity', function () {
    [$familyA, $familyB] = familyPair();
    [$businessA, $businessB] = businessPair();

    $debtB = Debt::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'Hutang B',
        'principal_total' => 500000,
        'remaining_balance' => 500000,
    ]);
    $goalB = SavingsGoal::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'Goal B',
        'target_amount' => 1000000,
    ]);
    $budgetB = Budget::query()->create([
        'finance_entity_id' => $businessB->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $businessB->id])->id,
        'amount' => 1000000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);

    grantEntityAccess($familyA);
    grantEntityAccess($familyB);
    grantEntityAccess($businessA);
    grantEntityAccess($businessB);

    $this->post(route('entity.debts.payments.store', [$familyA, $debtB]), [
        'amount' => '10000',
        'paid_on' => now()->toDateString(),
    ])->assertNotFound();
    $this->post(route('entity.savings-goals.contributions.store', [$familyA, $goalB]), [
        'amount' => '10000',
        'contributed_on' => now()->toDateString(),
    ])->assertNotFound();
    $this->post(route('entity.budgets.activities.store', [$businessA, $budgetB]), [
        'name' => 'Pupuk',
        'amount' => '10000',
        'activity_date' => now()->toDateString(),
    ])->assertNotFound();

    expect(Debt::query()->find($debtB->id)->payments()->count())->toBe(0)
        ->and(SavingsGoal::query()->find($goalB->id)->contributions()->count())->toBe(0)
        ->and(BudgetActivity::query()->where('budget_id', $budgetB->id)->count())->toBe(0);
});

it('rejects a category that belongs to another entity', function () {
    [$familyA, $familyB] = familyPair();
    [$businessA, $businessB] = businessPair();
    $categoryB = Category::factory()->create(['finance_entity_id' => $familyB->id]);
    $incomeCategoryB = Category::factory()->create([
        'finance_entity_id' => $businessB->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);

    grantEntityAccess($familyA);
    grantEntityAccess($businessA);

    $this->post(route('entity.transactions.store', $familyA), [
        'amount' => '15000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Salah kategori',
        'category_id' => $categoryB->id,
    ])->assertSessionHasErrors('category_id');

    $this->post(route('entity.incomes.store', $businessA), [
        'source' => 'Salah kategori',
        'amount' => '15000',
        'income_date' => now()->toDateString(),
        'category_id' => $incomeCategoryB->id,
    ])->assertSessionHasErrors('category_id');
});

it('keeps multi-tab requests scoped to the route entity rather than session', function () {
    [$entityA, $entityB] = familyPair();
    Transaction::factory()->create([
        'finance_entity_id' => $entityA->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityA->id])->id,
        'description' => 'Hanya A',
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entityB->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entityB->id])->id,
        'description' => 'Hanya B',
    ]);

    grantEntityAccess($entityA);
    grantEntityAccess($entityB);

    $this->get(route('entity.transactions.index', $entityA))
        ->assertOk()
        ->assertSee('Hanya A')
        ->assertDontSee('Hanya B');

    $this->get(route('entity.transactions.index', $entityB))
        ->assertOk()
        ->assertSee('Hanya B')
        ->assertDontSee('Hanya A');

    $this->get(route('entity.transactions.index', $entityA))
        ->assertOk()
        ->assertSee('Hanya A')
        ->assertDontSee('Hanya B');
});

it('blocks family routes on a business entity and the reverse', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    grantEntityAccess($family);
    grantEntityAccess($business);

    $this->get(route('entity.incomes.index', $family))->assertNotFound();
    $this->get(route('entity.transactions.index', $business))->assertNotFound();
});

it('copies finance_entity_id and consistent context when the recurring runner posts', function () {
    $entity = FinanceEntity::factory()->family()->create();
    RecurringTransaction::query()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'BPJS otomatis',
        'amount' => 150000,
        'frequency' => 'monthly',
        'start_date' => now()->subMonth(),
        'next_due' => now()->toDateString(),
        'active' => true,
    ]);

    expect(app(RecurringTransactionRunner::class)->runDue())->toBeGreaterThan(0);

    $this->assertDatabaseHas('transactions', [
        'description' => '[Otomatis] BPJS otomatis',
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::PRIBADI,
    ]);
});

it('does not remove financial data when an entity is deactivated', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $transaction = Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entity->id])->id,
        'description' => 'Tetap ada',
    ]);

    $entity->update(['is_active' => false]);

    expect($transaction->fresh())->not->toBeNull()
        ->and($entity->fresh()->is_active)->toBeFalse();
});
