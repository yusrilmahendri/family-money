<?php

use App\Enums\FinanceAccountType;
use App\Models\Debt;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\Income;
use App\Models\PortalAccessToken;
use App\Models\User;
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
use App\Support\FinanceEntityAccess;
use App\Support\FinanceOwnership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function readinessGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function readinessCash(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

it('blocks is_admin and token_hash mass assignment and hides the hash', function () {
    $user = User::query()->create([
        'name' => 'Bukan Admin',
        'email' => 'member-ready@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);
    expect($user->fresh()->is_admin)->toBeFalse();

    $entity = FinanceEntity::factory()->family()->create();
    $token = FinanceEntityAccessToken::factory()->create([
        'finance_entity_id' => $entity->id,
    ]);
    $original = $token->token_hash;

    $token->update(['token_hash' => str_repeat('a', 64)]);

    expect($token->fresh()->token_hash)->toBe($original)
        ->and($token->toArray())->not->toHaveKey('token_hash');

    $portal = PortalAccessToken::factory()->create(['name' => 'Ready']);
    $portalHash = $portal->token_hash;
    $portal->update(['token_hash' => str_repeat('b', 64)]);

    expect($portal->fresh()->token_hash)->toBe($portalHash)
        ->and($portal->toArray())->not->toHaveKey('token_hash');
});

it('does not store the plaintext access token in session', function () {
    $entity = FinanceEntity::factory()->family()->create();
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);

    $this->get(route('access.show', $plain))->assertRedirect();

    $session = session()->all();
    $encoded = json_encode($session) ?: '';

    expect($encoded)->not->toContain($plain)
        ->and(session()->previousUrl() ?? '')->not->toContain($plain)
        ->and(session(FinanceEntityAccess::SESSION_KEY)[$entity->public_id]['token_id'] ?? null)
        ->toBeInt()
        ->and(session(FinanceContext::SESSION_KEY))->toBeNull();
});

it('forces HTTPS only in production', function () {
    expect(URL::formatScheme())->not->toBe('https://');

    $this->app['env'] = 'production';
    app(\App\Providers\AppServiceProvider::class, ['app' => $this->app])->boot();
    expect(URL::formatScheme())->toBe('https://');

    $this->app['env'] = 'testing';
    URL::forceScheme('http');
});

it('keeps MySQL composite index names at or under 64 characters', function () {
    $defaults = [
        'business_capital_contributions_source_entity_id_transaction_date_index',
        'business_capital_contributions_business_entity_id_transaction_date_index',
        'profit_distributions_business_entity_id_period_start_period_end_index',
    ];

    foreach ($defaults as $name) {
        expect(strlen($name))->toBeGreaterThan(64);
    }

    foreach ([
        'bcc_source_entity_date_idx',
        'bcc_business_entity_date_idx',
        'ow_business_date_idx',
        'ow_family_date_idx',
        'pd_business_date_idx',
        'pd_family_date_idx',
        'pd_business_period_idx',
    ] as $name) {
        expect(strlen($name))->toBeLessThanOrEqual(64);
    }

    $capital = file_get_contents(database_path('migrations/2026_08_23_220000_create_business_capital_contributions_table.php')) ?: '';
    $profit = file_get_contents(database_path('migrations/2026_08_23_260000_create_profit_distributions_table.php')) ?: '';

    expect($capital)->toContain('bcc_source_entity_date_idx')
        ->and($capital)->toContain('bcc_business_entity_date_idx')
        ->and($profit)->toContain('pd_business_period_idx');
});

it('documents production session and debug settings', function () {
    $example = file_get_contents(base_path('.env.example')) ?: '';

    expect($example)->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('SESSION_HTTP_ONLY=true')
        ->toContain('SESSION_SAME_SITE=lax');
});

it('runs the full family business cash flow without leaking or double-counting', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Keluarga Ready',
            'type' => 'FAMILY',
            'is_active' => '1',
        ])
        ->assertRedirect();
    $this->actingAs($admin)
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Usaha Ready',
            'type' => 'BUSINESS',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $family = FinanceEntity::query()->where('name', 'Keluarga Ready')->firstOrFail();
    $business = FinanceEntity::query()->where('name', 'Usaha Ready')->firstOrFail();
    $familyKas = $family->defaultAccount();
    $familyBank = readinessCash($family, 'BCA Ready', 0);
    $businessKas = $business->defaultAccount();

    $familyKas->update(['opening_balance' => 20_000_000]);
    expect(app(FinanceAccountBalanceService::class)->balanceForEntity($family))->toBe(20_000_000.0);

    readinessGrant($family);
    readinessGrant($business);

    $this->post(route('entity.transactions.store', $family), [
        'amount' => '100000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja Ready',
        'finance_account_id' => $familyKas->id,
    ])->assertRedirect();

    $this->post(route('entity.debts.store', $family), [
        'title' => 'Hutang Ready',
        'principal_total' => '500000',
    ])->assertRedirect();

    $this->post(route('entity.savings-goals.store', $family), [
        'title' => 'Tabungan Ready',
        'target_amount' => '1000000',
    ])->assertRedirect();

    $this->post(route('entity.receivables.store', $family), [
        'party_name' => 'Piutang Ready',
        'principal_amount' => '250000',
        'receivable_date' => now()->toDateString(),
    ])->assertRedirect();

    $this->post(route('entity.incomes.store', $business), [
        'source' => 'Panen Ready',
        'amount' => '10000000',
        'income_date' => now()->toDateString(),
        'category_id' => $business->categories()->create([
            'name' => 'Panen',
            'context' => FinanceOwnership::contextFor($business),
        ])->id,
        'finance_account_id' => $businessKas->id,
    ])->assertRedirect();

    $category = $business->categories()->where('name', 'Panen')->firstOrFail();
    $this->post(route('entity.budgets.store', $business), [
        'amount' => '4000000',
        'periode' => now()->toDateString(),
        'category_id' => $category->id,
    ])->assertRedirect();
    $budget = $business->budgets()->firstOrFail();
    $this->post(route('entity.operational.store', $business), [
        'budget_id' => $budget->id,
        'name' => 'Pupuk Ready',
        'amount' => '2000000',
        'activity_date' => now()->toDateString(),
        'finance_account_id' => $businessKas->id,
    ])->assertRedirect();

    app(FinanceTransferService::class)->create($family, [
        'source_account_id' => $familyKas->id,
        'destination_account_id' => $familyBank->id,
        'amount' => 1_000_000,
        'transaction_date' => now(),
        'description' => 'Geser Ready',
    ]);
    app(BusinessCapitalContributionService::class)->create($family, $business, [
        'source_account_id' => $familyKas->id,
        'destination_account_id' => $businessKas->id,
        'amount' => 3_000_000,
        'transaction_date' => now(),
        'description' => 'Modal Ready',
    ]);
    app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $businessKas->id,
        'destination_account_id' => $familyKas->id,
        'amount' => 400_000,
        'transaction_date' => now(),
        'description' => 'Prive Ready',
    ]);
    [$from, $to] = app(BusinessProfitService::class)->currentMonthBounds();
    app(ProfitDistributionService::class)->create($business, $family, [
        'source_account_id' => $businessKas->id,
        'destination_account_id' => $familyKas->id,
        'amount' => 500_000,
        'distribution_date' => now(),
        'period_start' => $from,
        'period_end' => $to,
        'description' => 'Bagi Ready',
    ]);

    $balances = app(FinanceAccountBalanceService::class);
    $reports = app(EntityReportService::class);
    $profit = app(BusinessProfitService::class)->summary($business);
    $familyReport = $reports->report($family);
    $businessReport = $reports->report($business);
    $familyDash = $reports->dashboardMetrics($family);
    $businessDash = $reports->dashboardMetrics($business);

    expect($profit['revenue'])->toBe(10_000_000.0)
        ->and($profit['operational_expense'])->toBe(2_000_000.0)
        ->and($profit['profit'])->toBe(8_000_000.0)
        ->and($businessReport['business']['profit'])->toBe(8_000_000.0)
        ->and($businessDash['metrics']['laba'])->toBe(8_000_000.0)
        ->and($businessReport['business']['capital_received'])->toBe(3_000_000.0)
        ->and($businessReport['business']['prive'])->toBe(400_000.0)
        ->and($familyReport['family']['pengeluaran'])->toBe(100_000.0)
        ->and($familyDash['totalSaldo'])->toBe($familyReport['balance_total'])
        ->and($familyReport['balance_total'])->toBe($balances->balanceForEntity($family))
        ->and($businessReport['balance_total'])->toBe($balances->balanceForEntity($business))
        ->and($balances->balanceForEntity($family))->toBe(17_800_000.0)
        ->and($balances->balanceForEntity($business))->toBe(10_100_000.0)
        ->and($familyReport['piutang_outstanding'])->toBe(250_000.0)
        ->and((float) Debt::query()->where('title', 'Hutang Ready')->value('remaining_balance'))->toBe(500_000.0)
        ->and(Income::query()->count())->toBe(1);

    $this->artisan('finance:entity-audit')->assertSuccessful();
    $this->artisan('finance:account-audit')->assertSuccessful();
    $this->artisan('finance:balance-audit')->assertSuccessful();
    $this->artisan('finance:audit-log-check')->assertSuccessful();
    $this->artisan('finance:legacy-cleanup-check')->assertSuccessful();

    $other = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Lain Ready']);
    app(FinanceAccountService::class)->ensureDefaultAccount($other);
    $payload = app(EntityInsightDataService::class)->payload($family);
    expect(json_encode($payload))->not->toContain('Keluarga Lain Ready')
        ->not->toContain('Panen Ready');

    $this->get(route('entity.reports.excel', $family))->assertOk();
    $this->get(route('entity.reports.index', $other))->assertNotFound();

    $this->post(route('admin.logout'));
    $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
});
