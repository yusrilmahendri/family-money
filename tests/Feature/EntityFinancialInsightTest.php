<?php

use App\Enums\AnomalyType;
use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\Category;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Services\AiService;
use App\Services\BusinessProfitService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\Insight\EntityAiChatService;
use App\Services\Insight\EntityFinancialInsightService;
use App\Services\Insight\EntityFinancialSummaryService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function insightGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function insightCash(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function insightCategory(FinanceEntity $entity, string $name, string $context = FinanceContext::PRIBADI): Category
{
    return Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'name' => $name,
        'context' => $context,
    ]);
}

function insightFamilyIncome(FinanceEntity $family, $account, float $amount, mixed $date, string $source = 'Gaji'): Income
{
    return Income::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => insightCategory($family, 'Gaji '.$source)->id,
        'context' => FinanceContext::PRIBADI,
        'source' => $source,
        'amount' => $amount,
        'income_date' => $date,
    ]);
}

function insightFamilyExpense(FinanceEntity $family, $account, Category $category, float $amount, mixed $date, string $description = 'Belanja'): Transaction
{
    return Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => $amount,
        'transaction_date' => $date,
        'description' => $description,
    ]);
}

function insightBusinessIncome(FinanceEntity $business, $account, float $amount, mixed $date): Income
{
    return Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $account->id,
        'category_id' => insightCategory($business, 'Panen', FinanceContext::USAHA_KEBUN)->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => $amount,
        'income_date' => $date,
    ]);
}

function insightBusinessExpense(FinanceEntity $business, $account, Category $category, float $amount, mixed $date, float $planned = 0, string $name = 'Operasional'): BudgetActivity
{
    $budget = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => $planned,
        'amount_saldo' => 0,
        'periode' => $date,
    ]);

    return BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $account->id,
        'name' => $name,
        'amount' => $amount,
        'activity_date' => $date,
        'description' => $name,
    ]);
}

function insightMake(FinanceEntity $entity, string $key = 'month'): array
{
    return app(EntityFinancialInsightService::class)->make($entity, ['key' => $key]);
}

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-23 10:00:00', config('app.timezone')));
});

it('builds a FAMILY summary from existing report services', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Ringkas']);
    $account = insightCash($family, 'Kas Ringkas', 1_000_000);
    insightFamilyIncome($family, $account, 150_000, '2026-08-10', 'GajiRingkas');
    insightFamilyExpense($family, $account, insightCategory($family, 'Belanja'), 40_000, '2026-08-12');

    $page = insightMake($family);
    $keys = collect($page['summary']['metrics'])->pluck('key');

    expect($page['summary']['entity']['name'])->toBe('Keluarga Ringkas')
        ->and($keys->all())->toContain('saldo', 'income', 'expense', 'cash_flow', 'debt', 'receivable', 'savings', 'capital', 'prive', 'distribution')
        ->and(collect($page['summary']['metrics'])->firstWhere('key', 'income')['value'])->toBe(150_000.0)
        ->and(collect($page['summary']['metrics'])->firstWhere('key', 'expense')['value'])->toBe(40_000.0)
        ->and($page['summary']['narrative'])->toContain('Rp 150.000')
        ->toContain('Rp 40.000');
});

it('builds a BUSINESS summary from profit and report services', function () {
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Ringkas']);
    $account = insightCash($business, 'Kas Usaha Ringkas', 0);
    $category = insightCategory($business, 'Opex', FinanceContext::USAHA_KEBUN);
    insightBusinessIncome($business, $account, 400_000, '2026-08-05');
    insightBusinessExpense($business, $account, $category, 120_000, '2026-08-06', 200_000);

    $page = insightMake($business);
    $profit = app(BusinessProfitService::class)->calculate($business, '2026-08-01', '2026-08-31');
    $metrics = collect($page['summary']['metrics']);

    expect($metrics->firstWhere('key', 'income')['value'])->toBe($profit['revenue'])
        ->and($metrics->firstWhere('key', 'profit')['value'])->toBe($profit['profit'])
        ->and($metrics->pluck('key')->all())->toContain('budget_planned', 'budget_realized', 'capital', 'prive')
        ->and($page['summary']['narrative'])->toContain('revenue');
});

it('compares the current period to the previous period and skips percent when previous is zero', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Compare', 0);
    insightFamilyIncome($family, $account, 100_000, '2026-07-10');
    insightFamilyIncome($family, $account, 112_500, '2026-08-10');
    insightFamilyExpense($family, $account, insightCategory($family, 'Banding'), 50_000, '2026-08-11');

    $page = insightMake($family);
    $income = collect($page['summary']['metrics'])->firstWhere('key', 'income');
    $capital = collect($page['summary']['metrics'])->firstWhere('key', 'capital');

    expect($income['compare_status'])->toBe('ok')
        ->and($income['change_percent'])->toBe(12.5)
        ->and($income['direction'])->toBe('up')
        ->and($capital['compare_status'])->toBe('no_baseline')
        ->and($capital['change_percent'])->toBeNull();
});

it('keeps FAMILY and BUSINESS summaries isolated between entities', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga InsightA']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga InsightB']);
    $accountA = insightCash($familyA, 'Kas A', 200_000);
    $accountB = insightCash($familyB, 'Kas B', 9_000_000);
    insightFamilyIncome($familyA, $accountA, 80_000, now(), 'GajiA');
    insightFamilyIncome($familyB, $accountB, 777_000, now(), 'GajiB');

    $page = insightMake($familyA);
    $blob = json_encode($page) ?: '';

    expect($blob)->toContain('Keluarga InsightA')
        ->toContain('80000')
        ->not->toContain('Keluarga InsightB')
        ->not->toContain('GajiB')
        ->not->toContain('777000');
});

it('detects an unusual expense against historical category average', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Spike', 10_000_000);
    $category = insightCategory($family, 'Belanja');
    insightFamilyExpense($family, $account, $category, 1_000_000, '2026-05-10');
    insightFamilyExpense($family, $account, $category, 1_000_000, '2026-06-10');
    insightFamilyExpense($family, $account, $category, 1_000_000, '2026-07-10');
    insightFamilyExpense($family, $account, $category, 3_180_000, '2026-08-10');

    $items = collect(insightMake($family)['anomalies']['items']);
    $unusual = $items->firstWhere('type', AnomalyType::UNUSUAL_EXPENSE->value);

    expect($unusual)->not->toBeNull()
        ->and($unusual['amount'])->toBe(3_180_000.0)
        ->and($unusual['deviation_percentage'])->toBe(218.0)
        ->and($unusual['description'])->toContain('Belanja');
});

it('detects a category expense spike versus the previous period', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Cat', 10_000_000);
    $category = insightCategory($family, 'Transport');
    insightFamilyExpense($family, $account, $category, 100_000, '2026-05-02');
    insightFamilyExpense($family, $account, $category, 100_000, '2026-06-02');
    insightFamilyExpense($family, $account, $category, 100_000, '2026-07-02');
    insightFamilyExpense($family, $account, $category, 250_000, '2026-08-02');

    $spike = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::EXPENSE_SPIKE->value);

    expect($spike)->not->toBeNull()
        ->and($spike['amount'])->toBe(250_000.0)
        ->and($spike['title'])->toBe('Lonjakan Pengeluaran');
});

it('detects a significant income drop versus the previous period', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Drop', 0);
    insightFamilyIncome($family, $account, 100_000, '2026-07-08');
    insightFamilyIncome($family, $account, 58_000, '2026-08-08');

    $drop = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::INCOME_DROP->value);

    expect($drop)->not->toBeNull()
        ->and($drop['deviation_percentage'])->toBe(42.0)
        ->and($drop['description'])->toContain('42');
});

it('detects negative cash flow from report net cash', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Negatif', 1_000_000);
    insightFamilyExpense($family, $account, insightCategory($family, 'Darurat'), 80_000, '2026-08-04');

    $flow = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::NEGATIVE_CASH_FLOW->value);

    expect($flow)->not->toBeNull()
        ->and($flow['amount'])->toBeLessThan(0);
});

it('detects a negative entity balance', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Over', 10_000);
    insightFamilyExpense($family, $account, insightCategory($family, 'Besar'), 50_000, '2026-08-04');

    $balance = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::NEGATIVE_BALANCE->value);

    expect($balance)->not->toBeNull()
        ->and($balance['severity'])->toBe('CRITICAL')
        ->and($balance['amount'])->toBeLessThan(0);
});

it('detects overdue receivables from the existing receivable service', function () {
    $family = FinanceEntity::factory()->family()->create();
    insightCash($family, 'Kas Piutang', 0);
    Receivable::factory()->create([
        'finance_entity_id' => $family->id,
        'party_name' => 'PiutangOverdueA',
        'principal_amount' => 1_500_000,
        'remaining_balance' => 1_500_000,
        'due_date' => '2026-08-01',
    ]);

    $item = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::OVERDUE_RECEIVABLE->value);

    expect($item)->not->toBeNull()
        ->and($item['amount'])->toBe(1_500_000.0)
        ->and($item['description'])->toContain('Rp 1.500.000');
});

it('detects overdue family debt when due_day has passed without payment', function () {
    $family = FinanceEntity::factory()->family()->create();
    insightCash($family, 'Kas Hutang', 0);
    Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Cicilan Motor',
        'principal_total' => 2_000_000,
        'remaining_balance' => 800_000,
        'monthly_installment' => 200_000,
        'due_day' => 1,
        'start_date' => '2026-06-01',
    ]);

    $item = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::OVERDUE_DEBT->value);

    expect($item)->not->toBeNull()
        ->and($item['amount'])->toBe(800_000.0);
});

it('detects BUSINESS budget overrun from planned versus realized', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = insightCash($business, 'Kas Overrun', 0);
    $category = insightCategory($business, 'Pupuk', FinanceContext::USAHA_KEBUN);
    insightBusinessExpense($business, $account, $category, 150_000, '2026-08-10', 100_000);

    $item = collect(insightMake($business)['anomalies']['items'])->firstWhere('type', AnomalyType::BUDGET_OVERRUN->value);

    expect($item)->not->toBeNull()
        ->and($item['amount'])->toBe(150_000.0)
        ->and($item['deviation_percentage'])->toBe(50.0);
});

it('flags potential repeated transactions without calling them duplicates', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Repeat', 1_000_000);
    $category = insightCategory($family, 'Kopi');
    insightFamilyExpense($family, $account, $category, 50_000, '2026-08-21', 'Kopi pagi');
    insightFamilyExpense($family, $account, $category, 50_000, '2026-08-22', 'Kopi pagi');

    $item = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::REPEATED_TRANSACTION->value);
    $blob = json_encode($item) ?: '';

    expect($item)->not->toBeNull()
        ->and($item['description'])->toContain('Potensi transaksi berulang yang perlu diperiksa')
        ->and($blob)->not->toContain('duplikasi');
});

it('does not invent unusual-expense anomalies when historical data is insufficient', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = insightCash($family, 'Kas Sedikit', 1_000_000);
    insightFamilyExpense($family, $account, insightCategory($family, 'Baru'), 80_000, '2026-08-10');

    $page = insightMake($family);

    expect($page['anomalies']['notes'])->toContain('Data historis belum cukup untuk mendeteksi pola pengeluaran tidak biasa.')
        ->and(collect($page['anomalies']['items'])->firstWhere('type', AnomalyType::UNUSUAL_EXPENSE->value))->toBeNull();
});

it('does not leak Entity B anomalies into Entity A', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga IsolasiA']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga IsolasiB']);
    $accountA = insightCash($familyA, 'Kas IsolasiA', 500_000);
    $accountB = insightCash($familyB, 'Kas IsolasiB', 20_000_000);
    $categoryB = insightCategory($familyB, 'BelanjaRahasiaB');
    insightFamilyExpense($familyB, $accountB, $categoryB, 1_000_000, '2026-05-10');
    insightFamilyExpense($familyB, $accountB, $categoryB, 1_000_000, '2026-06-10');
    insightFamilyExpense($familyB, $accountB, $categoryB, 1_000_000, '2026-07-10');
    insightFamilyExpense($familyB, $accountB, $categoryB, 4_000_000, '2026-08-10');
    insightFamilyIncome($familyA, $accountA, 20_000, now(), 'GajiIsolasiA');

    $page = insightMake($familyA);
    $blob = json_encode($page['anomalies']) ?: '';

    expect($blob)->not->toContain('BelanjaRahasiaB')
        ->not->toContain('Keluarga IsolasiB')
        ->not->toContain('4000000');
});

it('detects a material capital movement versus the previous period', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $familyAccount = insightCash($family, 'Kas Modal Keluarga', 5_000_000);
    $businessAccount = insightCash($business, 'Kas Modal Usaha', 0);

    BusinessCapitalContribution::factory()->create([
        'source_entity_id' => $family->id,
        'source_account_id' => $familyAccount->id,
        'business_entity_id' => $business->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 100_000,
        'transaction_date' => '2026-07-15',
    ]);
    BusinessCapitalContribution::factory()->create([
        'source_entity_id' => $family->id,
        'source_account_id' => $familyAccount->id,
        'business_entity_id' => $business->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 250_000,
        'transaction_date' => '2026-08-15',
    ]);

    $item = collect(insightMake($family)['anomalies']['items'])->firstWhere('type', AnomalyType::MATERIAL_CAPITAL_PRIVE->value);

    expect($item)->not->toBeNull()
        ->and($item['amount'])->toBe(250_000.0);
});

it('sends backend summary and anomalies to the AI provider, not Entity B data', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga ExplainA']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga ExplainB']);
    $accountA = insightCash($familyA, 'Kas ExplainA', 10_000);
    insightCash($familyB, 'Kas ExplainB', 8_888_000);
    insightFamilyExpense($familyA, $accountA, insightCategory($familyA, 'DaruratA'), 80_000, '2026-08-04');
    insightGrant($familyA);

    $seen = null;
    $ai = Mockery::mock(AiService::class);
    $ai->shouldReceive('isConfigured')->andReturn(true);
    $ai->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$seen) {
        $seen = json_encode($messages) ?: '';

        return ['ok' => true, 'text' => 'Prioritaskan cash flow negatif.'];
    });
    app()->instance(AiService::class, $ai);
    app()->forgetInstance(EntityAiChatService::class);

    $this->postJson(route('entity.ai.chat', $familyA), [
        'message' => EntityFinancialSummaryService::EXPLAIN_PROMPT,
        'period' => 'month',
        'finance_entity_id' => $familyB->id,
    ])->assertOk()->assertJsonPath('success', true);

    expect($seen)->toBeString()
        ->toContain('Keluarga ExplainA')
        ->toContain('NEGATIVE_CASH_FLOW')
        ->toContain('ringkasan')
        ->not->toContain('Keluarga ExplainB')
        ->not->toContain('Kas ExplainB')
        ->not->toContain('token_hash')
        ->not->toContain('"password"');
});

it('renders summary, anomalies, period filters, and dashboard preview', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Halaman']);
    $account = insightCash($family, 'Kas Halaman', 200_000);
    insightFamilyIncome($family, $account, 75_000, '2026-08-03', 'GajiHalaman');
    insightGrant($family);

    $this->get(route('entity.insight.index', $family))
        ->assertOk()
        ->assertSee('Ringkasan Keuangan')
        ->assertSee('Anomali Keuangan')
        ->assertSee('Bulan ini')
        ->assertSee('Analisis dengan AI')
        ->assertSee('Keluarga Halaman')
        ->assertSee('Rp 75.000');

    $this->get(route('entity.insight.index', ['financeEntity' => $family, 'period' => 'last_month']))
        ->assertOk()
        ->assertSee('Ringkasan Keuangan');

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Insight Keuangan')
        ->assertSee('Tinjau Insight')
        ->assertSee('Anomali');
});
