<?php

use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Services\BusinessCapitalContributionService;
use App\Services\BusinessProfitService;
use App\Services\FinanceAccountService;
use App\Services\FinanceTransferService;
use App\Services\OwnerWithdrawalService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function profitService(): BusinessProfitService
{
    return app(BusinessProfitService::class);
}

function businessIncome(FinanceEntity $business, float $amount, mixed $date = null, $account = null, ?Category $category = null): Income
{
    $account ??= $business->defaultAccount() ?? cashAccount($business, 'Kas Profit '.fake()->unique()->numerify('####'));
    $category ??= Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);

    return Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => $amount,
        'income_date' => $date ?? now(),
    ]);
}

function businessExpense(FinanceEntity $business, float $amount, mixed $date = null, $account = null, ?Category $category = null, float $planned = 0): BudgetActivity
{
    $account ??= $business->defaultAccount() ?? cashAccount($business, 'Kas Biaya '.fake()->unique()->numerify('####'));
    $category ??= Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => $planned,
        'amount_saldo' => 0,
        'periode' => $date ?? now(),
    ]);

    return BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $account->id,
        'name' => 'Operasional',
        'amount' => $amount,
        'activity_date' => $date ?? now(),
    ]);
}

it('computes profit as income minus operational expense only', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = cashAccount($business, 'Kas Laba', 5_000_000);

    businessIncome($business, 100_000, now(), $account);
    businessExpense($business, 40_000, now(), $account, planned: 9_000_000);

    $result = profitService()->calculate($business);

    expect($result['revenue'])->toBe(100_000.0)
        ->and($result['operational_expense'])->toBe(40_000.0)
        ->and($result['profit'])->toBe(60_000.0);
});

it('ignores opening balance transfers capital prive and budget amount', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Usaha Profit', 8_000_000);
    $destination = app(FinanceAccountService::class)->create($business, [
        'name' => 'BRI Usaha Profit',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 2_000_000,
    ]);
    $familyAccount = cashAccount($family, 'BCA Family Profit', 3_000_000);

    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    businessIncome($business, 200_000, now(), $source, $category);
    businessExpense($business, 50_000, now(), $source, $category, planned: 1_000_000);

    $before = profitService()->calculate($business);
    expect($before['profit'])->toBe(150_000.0);

    app(FinanceTransferService::class)->create($business, [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 500_000,
        'transaction_date' => now()->toDateString(),
    ]);
    app(BusinessCapitalContributionService::class)->create($family, $business, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $source->id,
        'amount' => 1_000_000,
        'transaction_date' => now()->toDateString(),
    ]);
    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $source->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 250_000,
        'transaction_date' => now()->toDateString(),
    ]);

    $after = profitService()->calculate($business);
    $summary = profitService()->summary($business);

    expect($after['revenue'])->toBe(200_000.0)
        ->and($after['operational_expense'])->toBe(50_000.0)
        ->and($after['profit'])->toBe(150_000.0)
        ->and($summary['capital_in'])->toBe(1_000_000.0)
        ->and($summary['withdrawal_out'])->toBe(250_000.0)
        ->and(balanceService()->balance($source->fresh()))->not->toBe($after['profit']);
});

it('keeps BUSINESS profit isolated from another BUSINESS', function () {
    $a = FinanceEntity::factory()->business()->create(['name' => 'Usaha A Profit']);
    $b = FinanceEntity::factory()->business()->create(['name' => 'Usaha B Profit']);
    $accountA = cashAccount($a, 'Kas A', 0);
    $accountB = cashAccount($b, 'Kas B', 0);

    businessIncome($a, 80_000, now(), $accountA);
    businessExpense($a, 10_000, now(), $accountA);
    businessIncome($b, 500_000, now(), $accountB);
    businessExpense($b, 100_000, now(), $accountB);

    expect(profitService()->calculate($a)['profit'])->toBe(70_000.0)
        ->and(profitService()->calculate($b)['profit'])->toBe(400_000.0);
});

it('filters current month and custom inclusive periods', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = cashAccount($business, 'Kas Periode', 0);

    businessIncome($business, 30_000, now()->subMonth()->startOfMonth()->addDays(5), $account);
    businessExpense($business, 5_000, now()->subMonth()->startOfMonth()->addDays(6), $account);
    businessIncome($business, 90_000, now()->startOfMonth(), $account);
    businessExpense($business, 20_000, now()->endOfMonth(), $account);
    businessIncome($business, 15_000, now()->addMonth()->startOfMonth(), $account);

    $month = profitService()->currentMonth($business);
    expect($month['revenue'])->toBe(90_000.0)
        ->and($month['operational_expense'])->toBe(20_000.0)
        ->and($month['profit'])->toBe(70_000.0)
        ->and($month['from'])->toBe(now()->startOfMonth()->toDateString())
        ->and($month['to'])->toBe(now()->endOfMonth()->toDateString());

    $custom = profitService()->calculate(
        $business,
        now()->subMonth()->startOfMonth(),
        now()->subMonth()->endOfMonth()
    );
    expect($custom['revenue'])->toBe(30_000.0)
        ->and($custom['operational_expense'])->toBe(5_000.0)
        ->and($custom['profit'])->toBe(25_000.0);

    $all = profitService()->calculate($business);
    expect($all['revenue'])->toBe(135_000.0)
        ->and($all['profit'])->toBe(110_000.0);
});

it('computes a negative loss without treating it as expense or revenue', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = cashAccount($business, 'Kas Rugi', 0);
    businessIncome($business, 10_000, now(), $account);
    businessExpense($business, 40_000, now(), $account);

    $result = profitService()->calculate($business);
    expect($result['profit'])->toBe(-30_000.0)
        ->and(profitService()->summary($business)['is_loss'])->toBeTrue();

    grantEntityAccess($business);
    $this->get(route('entity.profit-loss.index', $business))
        ->assertOk()
        ->assertSee('Rugi: Rp 30.000')
        ->assertSee('Laba: Rp -30.000');
});

it('keeps dashboard and profit-loss numbers consistent for the same period', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = cashAccount($business, 'Kas Konsisten', 0);
    businessIncome($business, 70_000, now()->subMonth(), $account);
    businessExpense($business, 10_000, now()->subMonth(), $account);
    businessIncome($business, 50_000, now(), $account);
    businessExpense($business, 15_000, now(), $account);

    $lifetime = profitService()->calculate($business);
    $month = profitService()->currentMonth($business);

    grantEntityAccess($business);

    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Pemasukan')
        ->assertSee('Rp '.number_format($lifetime['revenue'], 0, ',', '.'))
        ->assertSee('Biaya operasional')
        ->assertSee('Rp '.number_format($lifetime['operational_expense'], 0, ',', '.'))
        ->assertSee('Laba / Rugi')
        ->assertSee('Rp '.number_format($lifetime['profit'], 0, ',', '.'))
        ->assertSee('Laba bulan ini')
        ->assertSee('Rp '.number_format($month['profit'], 0, ',', '.'));

    $this->get(route('entity.profit-loss.index', $business))
        ->assertOk()
        ->assertSee('Periode: Semua waktu')
        ->assertSee('Pemasukan: Rp '.number_format($lifetime['revenue'], 0, ',', '.'))
        ->assertSee('Biaya operasional: Rp '.number_format($lifetime['operational_expense'], 0, ',', '.'))
        ->assertSee('Laba: Rp '.number_format($lifetime['profit'], 0, ',', '.'));

    $this->get(route('entity.profit-loss.index', [
        'financeEntity' => $business,
        'period' => 'month',
    ]))
        ->assertOk()
        ->assertSee('Periode: '.$month['from'].' – '.$month['to'])
        ->assertSee('Pemasukan: Rp '.number_format($month['revenue'], 0, ',', '.'))
        ->assertSee('Biaya operasional: Rp '.number_format($month['operational_expense'], 0, ',', '.'))
        ->assertSee('Laba: Rp '.number_format($month['profit'], 0, ',', '.'));

    $from = now()->subMonth()->startOfMonth()->toDateString();
    $to = now()->subMonth()->endOfMonth()->toDateString();
    $custom = profitService()->calculate($business, $from, $to);

    $this->get(route('entity.profit-loss.index', [
        'financeEntity' => $business,
        'from' => $from,
        'to' => $to,
    ]))
        ->assertOk()
        ->assertSee('Periode: '.$from.' – '.$to)
        ->assertSee('Pemasukan: Rp '.number_format($custom['revenue'], 0, ',', '.'))
        ->assertSee('Laba: Rp '.number_format($custom['profit'], 0, ',', '.'));
});

it('rejects an invalid custom period and a FAMILY entity', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    cashAccount($business, 'Kas Validasi', 0);

    expect(fn () => profitService()->calculate($family))->toThrow(ValidationException::class);
    expect(fn () => profitService()->calculate($business, '2026-08-10', '2026-08-01'))
        ->toThrow(ValidationException::class);

    grantEntityAccess($business);
    $this->get(route('entity.profit-loss.index', [
        'financeEntity' => $business,
        'from' => '2026-08-10',
        'to' => '2026-08-01',
    ]))->assertSessionHasErrors('to');

    $this->get(route('entity.profit-loss.index', [
        'financeEntity' => $business,
        'from' => 'bukan-tanggal',
        'to' => '2026-08-01',
    ]))->assertSessionHasErrors('from');

    $this->get(route('entity.profit-loss.index', $family))->assertNotFound();
});
