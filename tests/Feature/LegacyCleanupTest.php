<?php

use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Saldo;
use App\Models\User;
use App\Services\EntityReportService;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\Insight\EntityInsightDataService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function cleanupGrantAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function cleanupAdmin()
{
    return test()->actingAs(User::factory()->admin()->create());
}

it('keeps entity and admin flows working without FinanceContext session', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Cleanup']);
    $account = app(FinanceAccountService::class)->create($family, [
        'name' => 'Kas Cleanup',
        'type' => 'CASH',
        'opening_balance' => 100_000,
    ]);
    cleanupGrantAccess($family);

    expect(session(FinanceContext::SESSION_KEY))->toBeNull();

    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Keluarga Cleanup')
        ->assertSee('Rp 100.000');

    $this->post(route('entity.transactions.store', $family), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja cleanup',
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    expect(session(FinanceContext::SESSION_KEY))->toBeNull()
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(75_000.0);
});

it('does not let entity income write saldos and keeps account balance as the source of truth', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = app(FinanceAccountService::class)->create($business, [
        'name' => 'Kas Cleanup Usaha',
        'type' => 'CASH',
        'opening_balance' => 0,
    ]);
    $category = \App\Models\Category::factory()->create([
        'finance_entity_id' => $business->id,
    ]);
    cleanupGrantAccess($business);

    $this->post(route('entity.incomes.store', $business), [
        'source' => 'Panen cleanup',
        'amount' => '200000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    $income = Income::query()->where('source', 'Panen cleanup')->first();

    expect($income)->not->toBeNull()
        ->and($income->saldo)->toBeNull()
        ->and(Saldo::query()->where('income_id', $income->id)->count())->toBe(0)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(200_000.0);
});

it('keeps report export and AI scoped to the route entity after cleanup', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga CleanupA']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga CleanupB']);
    app(FinanceAccountService::class)->create($entityA, [
        'name' => 'Kas A',
        'type' => 'CASH',
        'opening_balance' => 80_000,
    ]);
    cleanupGrantAccess($entityA);

    $report = app(EntityReportService::class)->report($entityA);
    $payload = app(EntityInsightDataService::class)->payload($entityA);

    expect($report['entity_name'])->toBe('Keluarga CleanupA')
        ->and($report['balance_total'])->toBe(80_000.0)
        ->and(json_encode($payload))->not->toContain('Keluarga CleanupB');

    $this->get(route('entity.reports.index', $entityA))->assertOk()->assertSee('Keluarga CleanupA')->assertDontSee('Keluarga CleanupB');
    $this->get(route('entity.reports.excel', $entityA))->assertOk();
    $this->get(route('entity.reports.pdf', $entityA))->assertOk();
    $this->get(route('entity.insight.index', $entityA))->assertOk();
    $this->get(route('entity.reports.index', $entityB))->assertNotFound();
});

it('keeps admin working after legacy portal retirement', function () {
    cleanupAdmin()
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');

    cleanupAdmin()
        ->get(route('admin.reports.index'))
        ->assertOk();
});

it('retires global finance routes so they cannot write legacy saldos', function () {
    $this->get('/')->assertOk()->assertSee('tautan privat')->assertSee('Admin Login');
    $this->get(route('apps.index'))->assertRedirect(route('home'));
    $this->get(route('dashboard'))->assertRedirect(route('home'));
    $this->get(route('insight.index'))->assertRedirect(route('home'));
    $this->post(route('incomes.store'), ['source' => 'X', 'amount' => '1'])->assertRedirect(route('home'));
    $this->post(route('transactions.store'), ['description' => 'X'])->assertRedirect(route('home'));

    expect(Income::query()->where('source', 'X')->exists())->toBeFalse()
        ->and(Saldo::query()->count())->toBe(0);
});

it('does not use FinanceContext session or SaldoGlobalService from entity runtime files', function () {
    $files = collect(File::allFiles(app_path('Http/Controllers/Entity')))
        ->merge(File::allFiles(app_path('Services')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
        ->reject(fn ($file) => in_array($file->getFilename(), [
            'InsightDataService.php',
            'FinanceContextService.php',
            'SaldoGlobalService.php',
            'FinanceEntityOwnershipMigrator.php',
            'FinanceBalanceAuditCommand.php',
        ], true));

    foreach ($files as $file) {
        if (! str_contains($file->getPathname(), '/Entity/')
            && ! str_contains($file->getPathname(), '/Services/')) {
            continue;
        }

        $contents = File::get($file->getPathname());

        if (str_contains($file->getPathname(), 'Entity/') || str_contains($file->getFilename(), 'Entity')) {
            expect($contents)->not->toContain('FinanceContext::current(')
                ->and($contents)->not->toContain('FinanceContext::set(')
                ->and($contents)->not->toContain('SaldoGlobalService');
        }
    }

    expect(File::exists(app_path('Service/AiService.php')))->toBeFalse()
        ->and(File::exists(app_path('Services/AiService.php')))->toBeTrue()
        ->and(File::exists(app_path('Services/RecurringTransactionRunner.php')))->toBeTrue();
});

it('passes the read-only legacy cleanup check', function () {
    $this->artisan('finance:legacy-cleanup-check')
        ->assertSuccessful()
        ->expectsOutputToContain('saldos table retained');
});
