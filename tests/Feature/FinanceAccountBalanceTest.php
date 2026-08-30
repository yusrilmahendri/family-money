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

it('excludes an inactive account from the operating entity total', function () {
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
        ->and(balanceService()->getAccountBalance($inactive->fresh()))->toBe(40_000.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe(100_000.0)
        ->and(balanceService()->getActiveAccountsTotal($entity))->toBe(100_000.0)
        ->and(balanceService()->summary($entity)['all_total'])->toBe(140_000.0)
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
        ->assertSee('Hanya Kas/Rekening aktif')
        ->assertDontSee('Rp 7.000.000');

    $this->get(route('entity.accounts.index', $entity))
        ->assertOk()
        ->assertSee('Kas Rumah')
        ->assertSee('Rp 1.000.000')
        ->assertDontSee('Rp 1.250.000')
        ->assertSee('Default')
        ->assertSee('Aktif')
        ->assertSee('Total Saldo hanya menghitung Kas/Rekening aktif. Rekening nonaktif tetap disimpan untuk histori.');
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

function movementSnapshot(FinanceEntity $entity): array
{
    return [
        'tx_count' => Transaction::query()->where('finance_entity_id', $entity->id)->count(),
        'tx_sum' => (float) Transaction::query()->where('finance_entity_id', $entity->id)->sum('amount'),
        'income_count' => Income::query()->where('finance_entity_id', $entity->id)->count(),
        'income_sum' => (float) Income::query()->where('finance_entity_id', $entity->id)->sum('amount'),
        'openings' => $entity->accounts()->orderBy('id')->pluck('opening_balance')->map(fn ($value) => (float) $value)->all(),
    ];
}

it('keeps inactive history while operating total uses only active accounts', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $active = cashAccount($entity, 'Kas Aktif', 2_876_982);
    $inactive = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    $legacyExpense = Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $inactive->id,
        'amount' => 112_243_120,
        'description' => 'Histori Kas Lama',
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);

    expect(balanceService()->getAccountBalance($active->fresh()))->toBe(2_876_982.0)
        ->and(balanceService()->getAccountBalance($inactive->fresh()))->toBe(-112_243_120.0)
        ->and(balanceService()->getActiveAccountsTotal($entity))->toBe(2_876_982.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe(2_876_982.0)
        ->and(balanceService()->summary($entity)['all_total'])->toBe(2_876_982.0 - 112_243_120.0)
        ->and(Transaction::query()->whereKey($legacyExpense->id)->exists())->toBeTrue()
        ->and((float) $legacyExpense->fresh()->amount)->toBe(112_243_120.0);
});

it('drops an account from the operating total on deactivate and restores it on reactivate without new movements', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $a = cashAccount($entity, 'Account A', 3_000_000);
    $b = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Account B',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 2_000_000,
    ]);

    expect(balanceService()->balanceForEntity($entity))->toBe(5_000_000.0);

    $beforeDeactivate = movementSnapshot($entity);
    app(FinanceAccountService::class)->deactivate($b);

    expect(balanceService()->balanceForEntity($entity))->toBe(3_000_000.0)
        ->and(balanceService()->getAccountBalance($b->fresh()))->toBe(2_000_000.0)
        ->and(movementSnapshot($entity))->toBe($beforeDeactivate)
        ->and((float) $a->fresh()->opening_balance)->toBe(3_000_000.0)
        ->and((float) $b->fresh()->opening_balance)->toBe(2_000_000.0);

    $beforeActivate = movementSnapshot($entity);
    app(FinanceAccountService::class)->activate($b);

    expect(balanceService()->balanceForEntity($entity))->toBe(5_000_000.0)
        ->and(movementSnapshot($entity))->toBe($beforeActivate);

    app(FinanceAccountService::class)->deactivate($b);

    expect(balanceService()->balanceForEntity($entity))->toBe(3_000_000.0)
        ->and(Transaction::query()->where('finance_entity_id', $entity->id)->count())->toBe(0)
        ->and(Income::query()->where('finance_entity_id', $entity->id)->count())->toBe(0);
});

it('uses the active operating total on dashboard, kas rekening, and reports while keeping inactive history', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Operasional']);
    cashAccount($entity, 'Kas Aktif', 2_876_982);
    $inactive = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $inactive->id,
        'amount' => 112_243_120,
        'description' => 'Histori Kas Lama',
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);
    balanceGrantAccess($entity);

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Total Saldo')
        ->assertSee('Rp 2.876.982')
        ->assertSee('Hanya Kas/Rekening aktif')
        ->assertDontSee('Rp -109.366.138')
        ->assertDontSee('-Rp 109.366.138');

    $this->get(route('entity.accounts.index', $entity))
        ->assertOk()
        ->assertSee('Total Saldo: ')
        ->assertSee('Rp 2.876.982')
        ->assertSee('Kas Aktif')
        ->assertSee('Kas Utama Keluarga')
        ->assertSee('-Rp 112.243.120')
        ->assertSee('Nonaktif')
        ->assertSee('Total Saldo hanya menghitung Kas/Rekening aktif. Rekening nonaktif tetap disimpan untuk histori.')
        ->assertDontSee('Rp -109.366.138')
        ->assertDontSee('-Rp 109.366.138');

    $report = app(\App\Services\EntityReportService::class)->report($entity);

    expect($report['balance_total'])->toBe(2_876_982.0)
        ->and(collect($report['movements'])->pluck('description')->all())->toContain('Histori Kas Lama')
        ->and($report['family']['pengeluaran'])->toBe(112_243_120.0);

    $this->get(route('entity.reports.index', $entity))
        ->assertOk()
        ->assertSee('Rp 2.876.982')
        ->assertSee('Histori Kas Lama')
        ->assertSee('mutasi historis tetap mencakup rekening nonaktif');
});

it('does not treat an inactive account as the entity default', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $first = cashAccount($entity, 'Kas Default Lama', 10_000);
    $second = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Pengganti',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 20_000,
    ]);

    expect($entity->defaultAccount()?->id)->toBe($first->id);

    app(FinanceAccountService::class)->deactivate($first);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($entity->fresh()->defaultAccount()?->id)->toBe($second->id)
        ->and(app(FinanceAccountService::class)->ensureDefaultAccount($entity)->id)->toBe($second->id);

    expect(fn () => app(FinanceAccountService::class)->setDefault($first->fresh()))
        ->toThrow(InvalidArgumentException::class, 'Hanya account aktif yang dapat dijadikan default.');

    app(FinanceAccountService::class)->deactivate($second);

    expect($entity->fresh()->defaultAccount())->toBeNull();
    expect(fn () => app(FinanceAccountService::class)->ensureDefaultAccount($entity->fresh()))
        ->toThrow(InvalidArgumentException::class, 'Entity belum memiliki akun default yang aktif.');
});

it('keeps operating totals isolated between entities when one account is inactive', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Isolasi A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Isolasi B']);
    cashAccount($entityA, 'Kas A Aktif', 100_000);
    $inactiveA = app(FinanceAccountService::class)->create($entityA, [
        'name' => 'Kas A Lama',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 40_000,
    ]);
    cashAccount($entityB, 'Kas B Aktif', 9_000_000);
    $inactiveB = app(FinanceAccountService::class)->create($entityB, [
        'name' => 'Kas B Lama',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 500_000,
    ]);
    app(FinanceAccountService::class)->deactivate($inactiveA);
    app(FinanceAccountService::class)->deactivate($inactiveB);

    expect(balanceService()->balanceForEntity($entityA))->toBe(100_000.0)
        ->and(balanceService()->balanceForEntity($entityB))->toBe(9_000_000.0)
        ->and(balanceService()->getAccountBalance($inactiveA->fresh()))->toBe(40_000.0)
        ->and(balanceService()->getAccountBalance($inactiveB->fresh()))->toBe(500_000.0)
        ->and(balanceService()->summary($entityA)['all_total'])->toBe(140_000.0)
        ->and(balanceService()->summary($entityB)['all_total'])->toBe(9_500_000.0);
});
