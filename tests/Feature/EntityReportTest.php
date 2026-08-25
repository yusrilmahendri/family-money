<?php

use App\Enums\FinanceAccountType;
use App\Exports\EntityReportExport;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\Receivable;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiService;
use App\Services\BusinessCapitalContributionService;
use App\Services\BusinessProfitService;
use App\Services\EntityReportService;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\FinanceTransferService;
use App\Services\Insight\EntityInsightDataService;
use App\Services\OwnerWithdrawalService;
use App\Services\ProfitDistributionService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportCashAccount(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function reportGrantAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function reportBalances(): FinanceAccountBalanceService
{
    return app(FinanceAccountBalanceService::class);
}

function reportProfits(): BusinessProfitService
{
    return app(BusinessProfitService::class);
}

function reportBusinessIncome(FinanceEntity $business, float $amount, mixed $date = null, $account = null, ?Category $category = null): Income
{
    $account ??= $business->defaultAccount() ?? reportCashAccount($business, 'Kas Profit '.fake()->unique()->numerify('####'));
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

function reportBusinessExpense(FinanceEntity $business, float $amount, mixed $date = null, $account = null, ?Category $category = null, float $planned = 0): BudgetActivity
{
    $account ??= $business->defaultAccount() ?? reportCashAccount($business, 'Kas Biaya '.fake()->unique()->numerify('####'));
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

function reportActingAdmin()
{
    return test()->actingAs(User::factory()->admin()->create());
}

function entityReports(): EntityReportService
{
    return app(EntityReportService::class);
}

function entityInsight(): EntityInsightDataService
{
    return app(EntityInsightDataService::class);
}

function familyIncome(FinanceEntity $family, float $amount, mixed $date, $account, string $source = 'Gaji'): Income
{
    return Income::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $family->id,
            'context' => FinanceContext::PRIBADI,
        ])->id,
        'context' => FinanceContext::PRIBADI,
        'source' => $source,
        'amount' => $amount,
        'income_date' => $date,
    ]);
}

function spreadsheetText(string $path): string
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $text = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_ends_with($name, '.xml')) {
            $text .= (string) $zip->getFromIndex($i);
        }
    }
    $zip->close();

    return $text;
}

function downloadedSpreadsheetText($response): string
{
    $file = $response->baseResponse->getFile();

    return spreadsheetText($file->getPathname());
}

it('keeps FAMILY reports scoped to the route entity', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AlphaReport']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga BravoReport']);
    $accountA = reportCashAccount($familyA, 'Kas Alpha', 1_000_000);
    $accountB = reportCashAccount($familyB, 'Kas Bravo', 9_000_000);

    familyIncome($familyA, 250_000, now(), $accountA, 'GajiAlphaOnly');
    Transaction::factory()->create([
        'finance_entity_id' => $familyA->id,
        'finance_account_id' => $accountA->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $familyA->id])->id,
        'amount' => 40_000,
        'description' => 'BelanjaAlphaOnly',
        'transaction_date' => now(),
    ]);
    Debt::query()->create([
        'finance_entity_id' => $familyA->id,
        'title' => 'HutangAlphaOnly',
        'principal_total' => 300_000,
        'remaining_balance' => 180_000,
    ]);
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $familyA->id,
        'title' => 'TabunganAlphaOnly',
        'target_amount' => 1_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $accountA->id,
        'amount' => 70_000,
        'contributed_on' => now(),
    ]);
    Receivable::factory()->create([
        'finance_entity_id' => $familyA->id,
        'party_name' => 'PiutangAlphaOnly',
        'principal_amount' => 500_000,
        'remaining_balance' => 500_000,
    ]);

    familyIncome($familyB, 888_888, now(), $accountB, 'GajiBravoOnly');
    Transaction::factory()->create([
        'finance_entity_id' => $familyB->id,
        'finance_account_id' => $accountB->id,
        'amount' => 77_777,
        'description' => 'BelanjaBravoOnly',
        'transaction_date' => now(),
    ]);
    Debt::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'HutangBravoOnly',
        'principal_total' => 2_000_000,
        'remaining_balance' => 1_500_000,
    ]);

    $report = entityReports()->report($familyA);

    expect($report['entity_name'])->toBe('Keluarga AlphaReport')
        ->and($report['balance_total'])->toBe(1_140_000.0)
        ->and($report['family']['pemasukan'])->toBe(250_000.0)
        ->and($report['family']['pengeluaran'])->toBe(110_000.0)
        ->and($report['family']['hutang_outstanding'])->toBe(180_000.0)
        ->and($report['family']['tabungan'])->toBe(70_000.0)
        ->and($report['piutang_outstanding'])->toBe(500_000.0)
        ->and($report['family']['pemasukan'])->not->toBe(888_888.0)
        ->and(collect($report['movements'])->pluck('description')->all())
        ->not->toContain('GajiBravoOnly')
        ->not->toContain('BelanjaBravoOnly');

    reportGrantAccess($familyA);
    $this->get(route('entity.reports.index', $familyA))
        ->assertOk()
        ->assertSee('Keluarga AlphaReport')
        ->assertSee(rupiah(1_140_000))
        ->assertSee('Pemasukan:')
        ->assertSee('Pengeluaran:')
        ->assertSee('Hutang outstanding')
        ->assertSee('Tabungan')
        ->assertSee('Modal ke usaha')
        ->assertSee('Penerimaan prive')
        ->assertSee('Profit distribution received')
        ->assertSee('Cash in:')
        ->assertDontSee('Keluarga BravoReport')
        ->assertDontSee('GajiBravoOnly')
        ->assertDontSee('BelanjaBravoOnly')
        ->assertDontSee('dashboard/export')
        ->assertDontSee(route('insight.index'));
});

it('keeps BUSINESS reports scoped to the route entity', function () {
    $businessA = FinanceEntity::factory()->business()->create(['name' => 'Usaha AlphaReport']);
    $businessB = FinanceEntity::factory()->business()->create(['name' => 'Usaha BravoReport']);
    $accountA = reportCashAccount($businessA, 'Kas Usaha Alpha', 0);
    $accountB = reportCashAccount($businessB, 'Kas Usaha Bravo', 0);

    reportBusinessIncome($businessA, 400_000, now(), $accountA);
    reportBusinessExpense($businessA, 120_000, now(), $accountA, planned: 800_000);
    reportBusinessIncome($businessB, 999_999, now(), $accountB);
    reportBusinessExpense($businessB, 111_111, now(), $accountB, planned: 2_000_000);

    $report = entityReports()->report($businessA);

    expect($report['entity_name'])->toBe('Usaha AlphaReport')
        ->and($report['balance_total'])->toBe(280_000.0)
        ->and($report['business']['revenue'])->toBe(400_000.0)
        ->and($report['business']['operational_expense'])->toBe(120_000.0)
        ->and($report['business']['profit'])->toBe(280_000.0)
        ->and($report['business']['budget_planned'])->toBe(800_000.0)
        ->and($report['business']['budget_realized'])->toBe(120_000.0)
        ->and($report['business']['revenue'])->not->toBe(999_999.0);

    reportGrantAccess($businessA);
    $this->get(route('entity.reports.index', $businessA))
        ->assertOk()
        ->assertSee('Usaha AlphaReport')
        ->assertSee('Revenue:')
        ->assertSee('Operational expense:')
        ->assertSee('Laba / Rugi:')
        ->assertSee('Anggaran planned')
        ->assertSee('Modal diterima')
        ->assertSee('Profit distributed')
        ->assertSee(rupiah(280_000))
        ->assertDontSee('Usaha BravoReport')
        ->assertDontSee(rupiah(999_999));
});

it('matches dashboard saldo and profit with the same-period report', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Konsisten']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Konsisten']);
    $familyAccount = reportCashAccount($family, 'Kas Rumah', 800_000);
    $businessAccount = reportCashAccount($business, 'Kas Usaha', 0);

    Transaction::factory()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $familyAccount->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $family->id])->id,
        'amount' => 50_000,
        'transaction_date' => now(),
    ]);
    reportBusinessIncome($business, 500_000, now(), $businessAccount);
    reportBusinessExpense($business, 180_000, now(), $businessAccount, planned: 1_000_000);

    $familyReport = entityReports()->report($family);
    $familyDash = entityReports()->dashboardMetrics($family);
    $businessReport = entityReports()->report($business);
    $businessDash = entityReports()->dashboardMetrics($business);
    $profit = reportProfits()->summary($business);

    expect($familyDash['totalSaldo'])->toBe($familyReport['balance_total'])
        ->and($familyDash['totalSaldo'])->toBe(reportBalances()->balanceForEntity($family))
        ->and($familyDash['metrics']['pemasukan'])->toBe($familyReport['family']['pemasukan'])
        ->and($familyDash['metrics']['pengeluaran'])->toBe($familyReport['family']['pengeluaran'])
        ->and($businessDash['totalSaldo'])->toBe($businessReport['balance_total'])
        ->and($businessDash['metrics']['laba'])->toBe($businessReport['business']['profit'])
        ->and($businessReport['business']['profit'])->toBe($profit['profit'])
        ->and($businessDash['metrics']['laba'])->toBe(320_000.0);

    reportGrantAccess($family);
    $this->get(route('entity.dashboard', $family))->assertOk()->assertSee(rupiah(750_000));
    $this->get(route('entity.reports.index', $family))->assertOk()->assertSee(rupiah(750_000));

    reportGrantAccess($business);
    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee(rupiah(320_000))
        ->assertSee('Laba / Rugi');
    $this->get(route('entity.reports.index', $business))->assertOk()->assertSee(rupiah(320_000));
    $this->get(route('entity.profit-loss.index', $business))->assertOk()->assertSee('Laba: Rp 320.000');
});

it('uses domain dates for period filters and keeps month and custom range consistent', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = reportCashAccount($business, 'Kas Periode', 0);

    $lastMonth = reportBusinessIncome($business, 111_111, now()->subMonth()->startOfMonth()->addDays(2), $account);
    $lastMonth->forceFill(['created_at' => now()])->save();
    $thisMonth = reportBusinessIncome($business, 222_222, now(), $account);
    $thisMonth->forceFill(['created_at' => now()->subYear()])->save();

    [$from, $to] = reportProfits()->currentMonthBounds();
    $month = entityReports()->report($business, $from, $to);
    $custom = entityReports()->report($business, $from, $to);
    $all = entityReports()->report($business);

    expect($month['business']['revenue'])->toBe(222_222.0)
        ->and($custom['business']['revenue'])->toBe($month['business']['revenue'])
        ->and($custom['business']['profit'])->toBe($month['business']['profit'])
        ->and($all['business']['revenue'])->toBe(333_333.0);

    reportGrantAccess($business);
    $this->get(route('entity.reports.index', ['financeEntity' => $business, 'period' => 'month']))
        ->assertOk()
        ->assertSee(rupiah(222_222))
        ->assertDontSee(rupiah(111_111));
    $this->get(route('entity.reports.index', [
        'financeEntity' => $business,
        'from' => $from,
        'to' => $to,
    ]))->assertOk()->assertSee(rupiah(222_222));
});

it('does not treat transfers capital prive distribution or planned budget as income or expense', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Semantik']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Semantik']);
    $familyKas = reportCashAccount($family, 'Kas Keluarga', 20_000_000);
    $familyBank = app(FinanceAccountService::class)->create($family, [
        'name' => 'BCA Keluarga',
        'type' => FinanceAccountType::BANK,
        'opening_balance' => 0,
    ]);
    $businessKas = reportCashAccount($business, 'Kas Usaha', 0);

    reportBusinessIncome($business, 10_000_000, now(), $businessKas);
    reportBusinessExpense($business, 2_000_000, now(), $businessKas, planned: 5_000_000);

    app(FinanceTransferService::class)->create($family, [
        'source_account_id' => $familyKas->id,
        'destination_account_id' => $familyBank->id,
        'amount' => 1_500_000,
        'transaction_date' => now(),
        'description' => 'Geser kas',
    ]);
    app(BusinessCapitalContributionService::class)->create($family, $business, [
        'source_account_id' => $familyKas->id,
        'destination_account_id' => $businessKas->id,
        'amount' => 3_000_000,
        'transaction_date' => now(),
        'description' => 'Setor modal',
    ]);
    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $businessKas->id,
        'destination_account_id' => $familyKas->id,
        'amount' => 400_000,
        'transaction_date' => now(),
        'description' => 'Prive',
    ]);
    [$from, $to] = reportProfits()->currentMonthBounds();
    app(ProfitDistributionService::class)->create($business, $family, [
        'source_account_id' => $businessKas->id,
        'destination_account_id' => $familyKas->id,
        'amount' => 500_000,
        'distribution_date' => now(),
        'period_start' => $from,
        'period_end' => $to,
        'description' => 'Bagi laba',
    ]);

    Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $business->id,
            'context' => FinanceContext::USAHA_KEBUN,
        ])->id,
        'amount' => 7_777_777,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);

    $familyReport = entityReports()->report($family);
    $businessReport = entityReports()->report($business);
    $profit = reportProfits()->summary($business);

    expect($familyReport['family']['pemasukan'])->toBe(0.0)
        ->and($familyReport['cash_flow']['income'])->toBe(0.0)
        ->and($familyReport['family']['pengeluaran'])->toBe(0.0)
        ->and($familyReport['cash_flow']['transfer_in'])->toBe(1_500_000.0)
        ->and($familyReport['family']['modal_ke_usaha'])->toBe(3_000_000.0)
        ->and($familyReport['family']['penerimaan_prive'])->toBe(400_000.0)
        ->and($familyReport['family']['penerimaan_laba'])->toBe(500_000.0)
        ->and($businessReport['business']['revenue'])->toBe(10_000_000.0)
        ->and($businessReport['business']['capital_received'])->toBe(3_000_000.0)
        ->and($businessReport['business']['revenue'])->not->toBe(13_000_000.0)
        ->and($businessReport['business']['operational_expense'])->toBe(2_000_000.0)
        ->and($businessReport['business']['prive'])->toBe(400_000.0)
        ->and($businessReport['business']['profit'])->toBe(8_000_000.0)
        ->and($businessReport['business']['profit'])->toBe($profit['profit'])
        ->and($businessReport['business']['profit_distributed'])->toBe(500_000.0)
        ->and($businessReport['cash_flow']['expense'])->toBe(2_000_000.0)
        ->and($businessReport['business']['budget_planned'])->toBe(12_777_777.0)
        ->and($businessReport['balance_total'])->toBe(10_100_000.0);
});

it('exports Excel and PDF for Entity A without Entity B data', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AlphaExport']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga BravoExport']);
    $accountA = app(FinanceAccountService::class)->create($familyA, [
        'name' => 'BCA AlphaExport',
        'type' => FinanceAccountType::BANK,
        'opening_balance' => 2_000_000,
        'account_number' => '123456789012',
    ]);
    $accountB = reportCashAccount($familyB, 'Kas BravoExport', 4_000_000);

    familyIncome($familyA, 250_000, now(), $accountA, 'GajiAlphaExport');
    familyIncome($familyB, 888_888, now(), $accountB, 'GajiBravoExport');
    Transaction::factory()->create([
        'finance_entity_id' => $familyB->id,
        'finance_account_id' => $accountB->id,
        'amount' => 77_777,
        'description' => 'BelanjaBravoExport',
        'transaction_date' => now(),
    ]);

    $report = entityReports()->report($familyA);
    $exportText = (new EntityReportExport($report))->plainText();
    expect($exportText)->toContain('Keluarga AlphaExport')
        ->toContain('GajiAlphaExport')
        ->and($exportText)->not->toContain('Keluarga BravoExport')
        ->not->toContain('GajiBravoExport')
        ->not->toContain('BelanjaBravoExport')
        ->not->toContain('123456789012');

    reportGrantAccess($familyA);
    $excel = $this->get(route('entity.reports.excel', $familyA))->assertOk();
    $sheet = downloadedSpreadsheetText($excel);
    expect($sheet)->toContain('Keluarga AlphaExport')
        ->and($sheet)->not->toContain('Keluarga BravoExport')
        ->not->toContain('GajiBravoExport')
        ->not->toContain('123456789012');

    $pdf = $this->get(route('entity.reports.pdf', $familyA));
    $pdf->assertOk();
    $pdfHtml = view('entity.reports.pdf', [
        'entity' => $familyA,
        'report' => $report,
    ])->render();
    expect($pdfHtml)->toContain('Keluarga AlphaExport')
        ->toContain('GajiAlphaExport')
        ->and($pdfHtml)->not->toContain('Keluarga BravoExport')
        ->not->toContain('GajiBravoExport')
        ->not->toContain('123456789012');
});

it('rejects another entity export and insight when the private capability is missing', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AccessA']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AccessB']);
    reportCashAccount($entityA, 'Kas A', 100_000);
    reportCashAccount($entityB, 'Kas B', 200_000);

    reportGrantAccess($entityA);

    $this->get(route('entity.reports.index', $entityB))->assertNotFound();
    $this->get(route('entity.reports.excel', $entityB))->assertNotFound();
    $this->get(route('entity.reports.pdf', $entityB))->assertNotFound();
    $this->get(route('entity.insight.index', $entityB))->assertNotFound();
    $this->post(route('entity.insight.summary', $entityB))->assertNotFound();
    $this->post(route('entity.ai.chat', $entityB), ['message' => 'Berapa saldo?'])->assertNotFound();
});

it('lets admin filter consolidated reports without mixing FAMILY and BUSINESS totals', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AdminFilter']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha AdminFilter']);
    $other = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AdminHidden']);
    $familyAccount = reportCashAccount($family, 'Kas Admin', 100_000);
    reportCashAccount($business, 'Kas Usaha Admin', 50_505);
    reportCashAccount($other, 'Kas Hidden', 10_101);
    familyIncome($family, 25_000, now(), $familyAccount, 'GajiAdminFilter');

    reportActingAdmin()
        ->get(route('admin.reports.index', ['finance_entity_id' => $family->id]))
        ->assertOk()
        ->assertSee('Keluarga AdminFilter')
        ->assertSee('FAMILY')
        ->assertSee(rupiah(125_000))
        ->assertDontSee(rupiah(10_101))
        ->assertDontSee(rupiah(50_505));

    reportActingAdmin()
        ->get(route('admin.reports.index', ['type' => 'BUSINESS']))
        ->assertOk()
        ->assertSee('Usaha AdminFilter')
        ->assertSee('BUSINESS')
        ->assertSee(rupiah(50_505))
        ->assertDontSee(rupiah(125_000))
        ->assertDontSee(rupiah(10_101));

    reportActingAdmin()
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Keluarga AdminFilter')
        ->assertSee('Usaha AdminFilter')
        ->assertSee('Angka FAMILY dan BUSINESS tidak digabung');
});

it('blocks private entity users from the admin consolidated report', function () {
    $entity = FinanceEntity::factory()->family()->create();
    reportGrantAccess($entity);

    $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});

it('scopes entity AI to the route entity and strips secrets', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AlphaInsight']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga BravoInsight']);
    $accountA = app(FinanceAccountService::class)->create($familyA, [
        'name' => 'BCA Insight',
        'type' => FinanceAccountType::BANK,
        'opening_balance' => 300_000,
        'account_number' => '987654321098',
    ]);
    $accountB = reportCashAccount($familyB, 'Kas BravoInsight', 1_000_000);
    familyIncome($familyA, 80_000, now(), $accountA, 'GajiAlphaInsight');
    familyIncome($familyB, 654_321, now(), $accountB, 'GajiBravoInsight');

    $payload = entityInsight()->payload($familyA);
    $json = json_encode($payload) ?: '';

    expect($payload['entity']['name'])->toBe('Keluarga AlphaInsight')
        ->and($payload['balance_summary']['total'])->toBe(380_000.0)
        ->and($json)->toContain('GajiAlphaInsight')
        ->and($json)->not->toContain('Keluarga BravoInsight')
        ->not->toContain('GajiBravoInsight')
        ->not->toContain('987654321098')
        ->not->toContain('password')
        ->not->toContain('token_hash')
        ->not->toContain('remember_token')
        ->and(entityInsight()->containsSensitiveValue($payload))->toBeFalse();

    reportGrantAccess($familyA);
    $this->mock(AiService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(false);
    });

    $this->get(route('entity.insight.index', $familyA))
        ->assertOk()
        ->assertSee('Keluarga AlphaInsight')
        ->assertSee('Insight AI')
        ->assertSee(rupiah(380_000))
        ->assertDontSee('Keluarga BravoInsight')
        ->assertDontSee('987654321098');

    $summary = $this->post(route('entity.insight.summary', $familyA))
        ->assertOk()
        ->assertJsonPath('payload.entity.name', 'Keluarga AlphaInsight')
        ->json();

    expect(json_encode($summary) ?: '')->not->toContain('Keluarga BravoInsight')
        ->not->toContain('987654321098');
});
