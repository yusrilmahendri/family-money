<?php

use App\Enums\AuditAction;
use App\Enums\FinanceAccountType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Receivable;
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

function familyIncomeGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function familyIncomeAccount(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::BANK,
        'opening_balance' => $opening,
    ]);
}

function familyIncomeCategory(FinanceEntity $entity, string $name = 'Gaji'): Category
{
    return Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'name' => $name,
        'context' => FinanceContext::PRIBADI,
    ]);
}

it('lets a FAMILY entity create update and delete income against its own account', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Gaji']);
    $account = familyIncomeAccount($family, 'BCA Keluarga', 10_000_000);
    $category = familyIncomeCategory($family);
    familyIncomeGrant($family);

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Pemasukan')
        ->assertSee('bukan transfer/prive/laba');

    $this->get(route('entity.incomes.index', $family))
        ->assertOk()
        ->assertSee('Pemasukan Keluarga')
        ->assertSee('Tambah');

    $this->get(route('entity.incomes.create', $family))
        ->assertOk()
        ->assertSee('Sumber Pemasukan')
        ->assertSee('Masuk ke Rekening')
        ->assertSee('Keterangan');

    $this->post(route('entity.incomes.store', $family), [
        'source' => 'Gaji Agustus',
        'amount' => 'Rp 15.000.000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'description' => 'Gaji bulanan',
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.incomes.store', $family), [
        'source' => 'Gaji Agustus',
        'amount' => 'Rp 15.000.000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
        'description' => 'Gaji bulanan',
        'finance_entity_id' => $family->id,
    ])->assertSessionHasErrors('finance_entity_id');

    $this->post(route('entity.incomes.store', $family), [
        'source' => 'Gaji Agustus',
        'amount' => 'Rp 15.000.000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
        'description' => 'Gaji bulanan',
    ])->assertRedirect(route('entity.incomes.index', $family));

    $income = Income::query()->first();
    $balances = app(FinanceAccountBalanceService::class);

    expect($income)->not->toBeNull()
        ->and($income->finance_entity_id)->toBe($family->id)
        ->and($income->finance_account_id)->toBe($account->id)
        ->and((float) $income->amount)->toBe(15_000_000.0)
        ->and($income->context)->toBe(FinanceContext::PRIBADI)
        ->and($balances->balance($account->fresh()))->toBe(25_000_000.0);

    $this->put(route('entity.incomes.update', [$family, $income]), [
        'source' => 'Gaji Agustus revisi',
        'amount' => '12000000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect(route('entity.incomes.index', $family));

    expect((float) $income->fresh()->amount)->toBe(12_000_000.0)
        ->and($balances->balance($account->fresh()))->toBe(22_000_000.0);

    $this->delete(route('entity.incomes.destroy', [$family, $income]))
        ->assertRedirect(route('entity.incomes.index', $family));

    expect(Income::query()->find($income->id))->toBeNull()
        ->and($balances->balance($account->fresh()))->toBe(10_000_000.0)
        ->and(AuditLog::query()->where('action', AuditAction::CREATE)->where('auditable_type', Income::class)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::UPDATE)->where('auditable_type', Income::class)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::DELETE)->where('auditable_type', Income::class)->exists())->toBeTrue();

    $payload = json_encode(AuditLog::query()->where('auditable_type', Income::class)->get(['old_values', 'new_values'])) ?: '';
    expect($payload)->not->toContain('token_hash')
        ->not->toContain('"password"')
        ->not->toContain('token');
});

it('rejects a FAMILY income that uses another entity account category or record', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga IncomeA']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga IncomeB']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha IncomeB']);
    $accountA = familyIncomeAccount($familyA, 'BCA A', 1_000_000);
    $accountB = familyIncomeAccount($familyB, 'BCA B', 9_000_000);
    $businessAccount = familyIncomeAccount($business, 'Kas Usaha B', 5_000_000);
    $categoryA = familyIncomeCategory($familyA, 'Gaji A');
    $categoryB = familyIncomeCategory($familyB, 'Gaji B');
    $incomeB = Income::query()->create([
        'finance_entity_id' => $familyB->id,
        'finance_account_id' => $accountB->id,
        'category_id' => $categoryB->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Gaji Rahasia B',
        'amount' => 3_000_000,
        'income_date' => now(),
    ]);
    familyIncomeGrant($familyA);
    familyIncomeGrant($familyB);

    $this->get(route('entity.incomes.index', $familyA))
        ->assertOk()
        ->assertDontSee('Gaji Rahasia B');

    $this->post(route('entity.incomes.store', $familyA), [
        'source' => 'Gaji silang',
        'amount' => '1000000',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
        'finance_account_id' => $businessAccount->id,
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.incomes.store', $familyA), [
        'source' => 'Gaji silang',
        'amount' => '1000000',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryB->id,
        'finance_account_id' => $accountA->id,
    ])->assertSessionHasErrors('category_id');

    $this->get(route('entity.incomes.edit', [$familyA, $incomeB]))->assertNotFound();
    $this->put(route('entity.incomes.update', [$familyA, $incomeB]), [
        'source' => 'Hacked',
        'amount' => '1',
        'income_date' => now()->toDateString(),
        'category_id' => $categoryA->id,
        'finance_account_id' => $accountA->id,
    ])->assertNotFound();
    $this->delete(route('entity.incomes.destroy', [$familyA, $incomeB]))->assertNotFound();

    expect($incomeB->fresh()->source)->toBe('Gaji Rahasia B')
        ->and(Income::query()->where('source', 'Gaji silang')->exists())->toBeFalse();
});

it('counts FAMILY income on dashboard report and insight without mixing other cash inflows', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-24 10:00:00', config('app.timezone')));
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Dashboard Income']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Dashboard Income']);
    $familyAccount = familyIncomeAccount($family, 'BCA Dashboard', 10_000_000);
    $otherFamilyAccount = app(FinanceAccountService::class)->create($family, [
        'name' => 'Kas Rumah',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    $businessAccount = familyIncomeAccount($business, 'Kas Usaha Dashboard', 0);
    $category = familyIncomeCategory($family, 'Gaji');
    familyIncomeGrant($family);
    familyIncomeGrant($business);

    $this->post(route('entity.incomes.store', $family), [
        'source' => 'GajiAgustusDash',
        'amount' => '15000000',
        'income_date' => '2026-08-10',
        'category_id' => $category->id,
        'finance_account_id' => $familyAccount->id,
    ])->assertRedirect();

    app(FinanceTransferService::class)->create($family, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $otherFamilyAccount->id,
        'amount' => 1_000_000,
        'transaction_date' => '2026-08-11',
        'description' => 'Geser kas',
    ]);

    $report = app(EntityReportService::class)->report($family);
    $month = app(EntityReportService::class)->report($family, '2026-08-01', '2026-08-31');
    $dashboard = app(EntityReportService::class)->dashboardMetrics($family);
    $insight = app(EntityInsightDataService::class)->chatContext($family, 'Berapa pemasukan bulan ini?');
    $balances = app(FinanceAccountBalanceService::class);

    expect($report['family']['pemasukan'])->toBe(15_000_000.0)
        ->and($month['cash_flow']['income'])->toBe(15_000_000.0)
        ->and($dashboard['metrics']['pemasukan'])->toBe(15_000_000.0)
        ->and($insight['period_income'])->toBe(15_000_000.0)
        ->and($insight['lifetime_income'])->toBe(15_000_000.0)
        ->and($insight['kategori_pemasukan'][0]['name'])->toBe('Gaji')
        ->and($insight['facts_relevant_to_question']['period_income'])->toBe(15_000_000.0)
        ->and($balances->balanceForEntity($family))->toBe(25_000_000.0)
        ->and(json_encode($insight))->toContain('GajiAgustusDash')
        ->not->toContain('token_hash');

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('GajiAgustusDash')
        ->assertSee('Rp 15.000.000')
        ->assertSee('Rp 25.000.000');

    $this->get(route('entity.reports.index', $family))
        ->assertOk()
        ->assertSee('Pemasukan')
        ->assertSee('Rp 15.000.000')
        ->assertSee('Rp 25.000.000');

    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $businessAccount->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $business->id,
            'context' => FinanceContext::USAHA_KEBUN,
        ])->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'PanenUsahaDash',
        'amount' => 88_000_000,
        'income_date' => now(),
    ]);

    expect(app(EntityReportService::class)->dashboardMetrics($family)['metrics']['pemasukan'])->toBe(15_000_000.0)
        ->and(json_encode(app(EntityInsightDataService::class)->chatContext($family, 'Berapa pemasukan bulan ini?')))
        ->not->toContain('PanenUsahaDash');
});

it('does not treat transfer prive profit distribution or receivable payment as FAMILY income', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga No Dobel']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha No Dobel']);
    $familyAccount = familyIncomeAccount($family, 'BCA No Dobel', 0);
    $familyCash = app(FinanceAccountService::class)->create($family, [
        'name' => 'Kas No Dobel',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    $businessAccount = familyIncomeAccount($business, 'Kas Usaha No Dobel', 0);

    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $businessAccount->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $business->id,
            'context' => FinanceContext::USAHA_KEBUN,
        ])->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => 50_000_000,
        'income_date' => now(),
    ]);

    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 5_000_000,
        'transaction_date' => now()->toDateString(),
    ]);
    app(ProfitDistributionService::class)->create($business, $family, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 2_000_000,
        'distribution_date' => now()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);
    app(FinanceTransferService::class)->create($family, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $familyCash->id,
        'amount' => 1_000_000,
        'transaction_date' => now()->toDateString(),
    ]);

    familyIncomeGrant($family);
    $this->post(route('entity.receivables.store', $family), [
        'party_name' => 'Piutang No Dobel',
        'principal_amount' => '3000000',
        'receivable_date' => now()->toDateString(),
    ])->assertRedirect();
    $receivable = Receivable::query()->first();
    $this->post(route('entity.receivables.payments.store', [$family, $receivable]), [
        'finance_account_id' => $familyAccount->id,
        'amount' => '1000000',
        'payment_date' => now()->toDateString(),
    ])->assertRedirect();

    $report = app(EntityReportService::class)->report($family);

    expect((float) $family->incomes()->sum('amount'))->toBe(0.0)
        ->and($report['family']['pemasukan'])->toBe(0.0)
        ->and($report['family']['penerimaan_prive'])->toBe(5_000_000.0)
        ->and($report['family']['penerimaan_laba'])->toBe(2_000_000.0)
        ->and($report['cash_flow']['receivable_in'])->toBe(1_000_000.0)
        ->and($report['cash_flow']['income'])->toBe(0.0)
        ->and(Income::query()->where('finance_entity_id', $family->id)->count())->toBe(0);
});
