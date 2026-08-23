<?php

use App\Enums\FinanceEntityType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\FinanceEntityOwnershipMigrator;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ownershipMigrator(): FinanceEntityOwnershipMigrator
{
    return new FinanceEntityOwnershipMigrator;
}

it('maps PRIBADI transactions to the default FAMILY entity', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $transaction = Transaction::query()->create([
        'finance_entity_id' => null,
        'context' => FinanceContext::PRIBADI,
        'amount' => 25000,
        'transaction_date' => now(),
        'description' => 'Belanja keluarga',
    ]);

    $migrator->backfill();

    expect((int) $transaction->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI]);
});

it('maps USAHA_KEBUN transactions to the default BUSINESS entity', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $transaction = Transaction::query()->create([
        'finance_entity_id' => null,
        'context' => FinanceContext::USAHA_KEBUN,
        'amount' => 75000,
        'transaction_date' => now(),
        'description' => 'Biaya kebun',
    ]);

    $migrator->backfill();

    expect((int) $transaction->fresh()->finance_entity_id)->toBe($ids[FinanceContext::USAHA_KEBUN]);
});

it('maps PRIBADI categories to the default FAMILY entity', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $category = Category::query()->create([
        'name' => 'Makanan legacy',
        'context' => FinanceContext::PRIBADI,
        'finance_entity_id' => null,
    ]);

    $migrator->backfill();

    expect((int) $category->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI]);
});

it('maps USAHA_KEBUN categories to the default BUSINESS entity', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $category = Category::query()->create([
        'name' => 'Pupuk legacy',
        'context' => FinanceContext::USAHA_KEBUN,
        'finance_entity_id' => null,
    ]);

    $migrator->backfill();

    expect((int) $category->fresh()->finance_entity_id)->toBe($ids[FinanceContext::USAHA_KEBUN]);
});

it('maps incomes from their legacy context', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $familyIncome = Income::query()->create([
        'finance_entity_id' => null,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Transfer keluarga',
        'amount' => 100000,
        'income_date' => now(),
    ]);
    $businessIncome = Income::query()->create([
        'finance_entity_id' => null,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => 500000,
        'income_date' => now(),
    ]);

    $migrator->backfill();

    expect((int) $familyIncome->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI])
        ->and((int) $businessIncome->fresh()->finance_entity_id)->toBe($ids[FinanceContext::USAHA_KEBUN]);
});

it('maps budgets through category ownership, otherwise default BUSINESS', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $businessCategory = Category::query()->create([
        'name' => 'Operasional kebun',
        'context' => FinanceContext::USAHA_KEBUN,
        'finance_entity_id' => null,
    ]);
    $viaCategory = Budget::query()->create([
        'finance_entity_id' => null,
        'category_id' => $businessCategory->id,
        'amount' => 2000000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    $noCategory = Budget::query()->create([
        'finance_entity_id' => null,
        'category_id' => null,
        'amount' => 1500000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);

    $migrator->backfill();

    expect((int) $viaCategory->fresh()->finance_entity_id)->toBe($ids[FinanceContext::USAHA_KEBUN])
        ->and((int) $noCategory->fresh()->finance_entity_id)->toBe($ids[FinanceContext::USAHA_KEBUN]);
});

it('maps debts to the default FAMILY entity because the feature is personal-only', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $debt = Debt::query()->create([
        'finance_entity_id' => null,
        'title' => 'Cicilan motor',
        'principal_total' => 8000000,
        'remaining_balance' => 8000000,
    ]);

    $migrator->backfill();

    expect((int) $debt->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI]);
});

it('maps savings goals to the default FAMILY entity because the feature is personal-only', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => null,
        'title' => 'Liburan',
        'target_amount' => 5000000,
    ]);

    $migrator->backfill();

    expect((int) $goal->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI]);
});

it('maps recurring transactions through category or default FAMILY', function () {
    $migrator = ownershipMigrator();
    $ids = $migrator->ensureDefaultEntities();

    $category = Category::query()->create([
        'name' => 'Tagihan rumah',
        'context' => FinanceContext::PRIBADI,
        'finance_entity_id' => null,
    ]);
    $withCategory = RecurringTransaction::query()->create([
        'finance_entity_id' => null,
        'category_id' => $category->id,
        'name' => 'Listrik',
        'amount' => 200000,
        'frequency' => 'monthly',
        'start_date' => now(),
        'next_due' => now(),
        'active' => true,
    ]);
    $withoutCategory = RecurringTransaction::query()->create([
        'finance_entity_id' => null,
        'name' => 'BPJS',
        'amount' => 150000,
        'frequency' => 'monthly',
        'start_date' => now(),
        'next_due' => now(),
        'active' => true,
    ]);

    $migrator->backfill();

    expect((int) $withCategory->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI])
        ->and((int) $withoutCategory->fresh()->finance_entity_id)->toBe($ids[FinanceContext::PRIBADI]);
});

it('does not create duplicate default entities when run again', function () {
    $migrator = ownershipMigrator();
    $migrator->ensureDefaultEntities();
    $migrator->ensureDefaultEntities();
    $migrator->backfill();

    expect(FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_PRIBADI)->count())->toBe(1)
        ->and(FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN)->count())->toBe(1)
        ->and(FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_PRIBADI)->first()->type)->toBe(FinanceEntityType::FAMILY)
        ->and(FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN)->first()->type)->toBe(FinanceEntityType::BUSINESS);
});

it('keeps existing record counts after backfill', function () {
    $migrator = ownershipMigrator();

    Transaction::query()->create([
        'finance_entity_id' => null,
        'context' => FinanceContext::PRIBADI,
        'amount' => 10000,
        'transaction_date' => now(),
        'description' => 'Keep me',
    ]);
    Category::query()->create([
        'name' => 'Keep category',
        'context' => FinanceContext::PRIBADI,
        'finance_entity_id' => null,
    ]);

    $before = [
        'transactions' => Transaction::query()->count(),
        'categories' => Category::query()->count(),
    ];

    $migrator->backfill();

    expect(Transaction::query()->count())->toBe($before['transactions'])
        ->and(Category::query()->count())->toBe($before['categories'])
        ->and(Transaction::query()->where('description', 'Keep me')->exists())->toBeTrue();
});

it('does not map unknown legacy contexts', function () {
    $migrator = ownershipMigrator();

    $transaction = Transaction::query()->create([
        'finance_entity_id' => null,
        'context' => 'KONTEKS_LAIN',
        'amount' => 10000,
        'transaction_date' => now(),
        'description' => 'Unknown context',
    ]);

    $migrator->backfill();

    expect($transaction->fresh()->finance_entity_id)->toBeNull()
        ->and($migrator->unknownContextCount('transactions'))->toBe(1)
        ->and($migrator->hasCriticalInconsistencies())->toBeTrue();
});
