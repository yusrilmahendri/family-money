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
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\BusinessCapitalContributionService;
use App\Services\BusinessProfitService;
use App\Services\EntityReportService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\FinanceTransferService;
use App\Services\OwnerWithdrawalService;
use App\Services\ProfitDistributionService;
use App\Services\ReceivableService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trendCash(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function trendGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function trendIncome(FinanceEntity $entity, float $amount, mixed $date, $account, string $source = 'Pemasukan Tren'): Income
{
    return Income::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $entity->id,
            'context' => $entity->isBusiness() ? FinanceContext::USAHA_KEBUN : FinanceContext::PRIBADI,
        ])->id,
        'context' => $entity->isBusiness() ? FinanceContext::USAHA_KEBUN : FinanceContext::PRIBADI,
        'source' => $source,
        'amount' => $amount,
        'income_date' => $date,
    ]);
}

function trendRows(FinanceEntity $entity, int $year): array
{
    return app(EntityReportService::class)->monthlyCashFlow($entity, $year);
}

function trendMonth(array $rows, int $month): array
{
    return collect($rows)->firstWhere('month', $month);
}

it('returns twelve months and zeros empty months', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = trendCash($entity, 'Kas Tren Kosong', 5_000_000);

    $rows = trendRows($entity, 2026);

    expect($rows)->toHaveCount(12)
        ->and(array_column($rows, 'month'))->toBe(range(1, 12));

    foreach ($rows as $row) {
        expect($row['income'])->toBe(0.0)
            ->and($row['expense'])->toBe(0.0)
            ->and($row['net'])->toBe(0.0);
    }

    expect((float) $account->opening_balance)->toBe(5_000_000.0);
});

it('aggregates FAMILY cash flow for the selected year only and isolates Entity B', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tren A']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tren B']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tren']);
    $kasA = trendCash($familyA, 'Kas A', 8_000_000);
    $bankA = trendCash($familyA, 'Bank A', 0);
    $kasB = trendCash($familyB, 'Kas B', 0);
    $kasUsaha = trendCash($business, 'Kas Usaha', 0);
    $march = now()->setDate(2026, 3, 15);
    $otherYear = now()->setDate(2025, 3, 15);

    trendIncome($familyA, 1_000_000, $march, $kasA, 'Gaji Maret');
    trendIncome($familyA, 250_000, $otherYear, $kasA, 'Gaji 2025');
    trendIncome($familyB, 9_999_000, $march, $kasB, 'Gaji B');

    Transaction::factory()->create([
        'finance_entity_id' => $familyA->id,
        'finance_account_id' => $kasA->id,
        'amount' => 400_000,
        'transaction_date' => $march,
        'description' => 'Belanja Maret',
    ]);

    $debt = Debt::query()->create([
        'finance_entity_id' => $familyA->id,
        'title' => 'Hutang Tren',
        'principal_total' => 2_000_000,
        'remaining_balance' => 2_000_000,
    ]);
    DebtPayment::query()->create([
        'debt_id' => $debt->id,
        'finance_account_id' => $kasA->id,
        'amount' => 50_000,
        'paid_on' => $march,
    ]);

    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $familyA->id,
        'title' => 'Tabungan Tren',
        'target_amount' => 1_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $kasA->id,
        'amount' => 25_000,
        'contributed_on' => $march,
    ]);

    app(FinanceTransferService::class)->create($familyA, [
        'source_account_id' => $kasA->id,
        'destination_account_id' => $bankA->id,
        'amount' => 200_000,
        'transaction_date' => $march,
        'description' => 'Geser internal',
    ]);

    $receivable = app(ReceivableService::class)->create($familyA, [
        'party_name' => 'Piutang Tren',
        'principal_amount' => 3_000_000,
        'receivable_date' => $march,
    ]);
    app(ReceivableService::class)->recordPayment($receivable, [
        'finance_account_id' => $kasA->id,
        'amount' => 100_000,
        'payment_date' => $march,
    ]);

    app(BusinessCapitalContributionService::class)->create($familyA, $business, [
        'source_account_id' => $kasA->id,
        'destination_account_id' => $kasUsaha->id,
        'amount' => 500_000,
        'transaction_date' => $march,
        'description' => 'Modal Maret',
    ]);

    $rows = trendRows($familyA, 2026);
    $marchRow = trendMonth($rows, 3);
    $yearFlows = app(EntityReportService::class)->report($familyA, '2026-01-01', '2026-12-31')['cash_flow'];

    expect($rows)->toHaveCount(12)
        ->and($marchRow['income'])->toBe(1_100_000.0)
        ->and($marchRow['expense'])->toBe(975_000.0)
        ->and($marchRow['net'])->toBe(125_000.0)
        ->and(trendMonth($rows, 1)['income'])->toBe(0.0)
        ->and(array_sum(array_column($rows, 'income')))->toBe($yearFlows['cash_in'] - $yearFlows['transfer_in'])
        ->and(array_sum(array_column($rows, 'expense')))->toBe($yearFlows['cash_out'] - $yearFlows['transfer_out']);

    $otherYearRows = trendRows($familyA, 2025);
    expect(trendMonth($otherYearRows, 3)['income'])->toBe(250_000.0)
        ->and(array_sum(array_column($otherYearRows, 'income')))->toBe(250_000.0)
        ->and(array_sum(array_column($otherYearRows, 'expense')))->toBe(0.0);

    $rowsB = trendRows($familyB, 2026);
    expect(trendMonth($rowsB, 3)['income'])->toBe(9_999_000.0)
        ->and(json_encode($rows))->not->toContain('Keluarga Tren B')
        ->and(array_sum(array_column($rows, 'income')))->not->toBe(9_999_000.0);
});

it('uses BUSINESS cash flow not revenue or profit', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $kasFamily = trendCash($family, 'Kas Family Tren', 2_000_000);
    $kasUsaha = trendCash($business, 'Kas Usaha Tren', 0);
    $kasUsaha2 = trendCash($business, 'Kas Usaha 2', 0);
    $march = now()->setDate(2026, 3, 20);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);

    trendIncome($business, 2_000_000, $march, $kasUsaha, 'Panen Tren');

    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => 5_000_000,
        'amount_saldo' => 0,
        'periode' => $march,
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $kasUsaha->id,
        'name' => 'Pupuk Tren',
        'amount' => 800_000,
        'activity_date' => $march,
    ]);

    app(FinanceTransferService::class)->create($business, [
        'source_account_id' => $kasUsaha->id,
        'destination_account_id' => $kasUsaha2->id,
        'amount' => 100_000,
        'transaction_date' => $march,
        'description' => 'Geser usaha',
    ]);

    app(BusinessCapitalContributionService::class)->create($family, $business, [
        'source_account_id' => $kasFamily->id,
        'destination_account_id' => $kasUsaha->id,
        'amount' => 1_000_000,
        'transaction_date' => $march,
        'description' => 'Modal masuk',
    ]);
    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $kasUsaha->id,
        'destination_account_id' => $kasFamily->id,
        'amount' => 200_000,
        'transaction_date' => $march,
        'description' => 'Prive',
    ]);
    app(ProfitDistributionService::class)->create($business, $family, [
        'source_account_id' => $kasUsaha->id,
        'destination_account_id' => $kasFamily->id,
        'amount' => 150_000,
        'distribution_date' => $march,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'description' => 'Bagi',
    ]);

    $rows = trendRows($business, 2026);
    $marchRow = trendMonth($rows, 3);
    $profit = app(BusinessProfitService::class)->summary($business, '2026-01-01', '2026-12-31');

    expect($marchRow['income'])->toBe(3_000_000.0)
        ->and($marchRow['expense'])->toBe(1_150_000.0)
        ->and($marchRow['net'])->toBe(1_850_000.0)
        ->and($profit['profit'])->toBe(1_200_000.0)
        ->and($marchRow['income'])->not->toBe($profit['revenue'])
        ->and($marchRow['net'])->not->toBe($profit['profit']);
});

it('renders the annual chart and applies an independent year filter', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Chart Tahun']);
    $account = trendCash($entity, 'Kas Chart Tahun', 0);
    trendIncome($entity, 777_000, now()->setDate(2026, 8, 10), $account, 'Gaji 2026');
    trendIncome($entity, 111_000, now()->setDate(2025, 8, 10), $account, 'Gaji 2025');
    trendGrant($entity);

    $current = $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Tren Keuangan Bulanan — 2026')
        ->assertSee('chart_year')
        ->assertSee(now()->locale('id')->translatedFormat('F Y'));

    preg_match('/var monthly = (\[.*?\]);/', $current->getContent(), $currentMatch);
    expect(json_decode($currentMatch[1] ?? '[]', true))->not->toBeEmpty()
        ->and((float) array_sum(array_column(json_decode($currentMatch[1], true), 'income')))->toBe(777_000.0);

    $filtered = $this->get(route('entity.dashboard', ['financeEntity' => $entity, 'chart_year' => 2025]))
        ->assertOk()
        ->assertSee('Tren Keuangan Bulanan — 2025')
        ->assertSee(now()->locale('id')->translatedFormat('F Y'));

    preg_match('/var monthly = (\[.*?\]);/', $filtered->getContent(), $filteredMatch);
    expect(json_decode($filteredMatch[1] ?? '[]', true))->not->toBeEmpty()
        ->and((float) array_sum(array_column(json_decode($filteredMatch[1], true), 'income')))->toBe(111_000.0);
});
