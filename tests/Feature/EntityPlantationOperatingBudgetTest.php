<?php

use App\Enums\FinanceAccountType;
use App\Enums\PlantationIntegrationStatus;
use App\Enums\PlantationOperatingBudgetStatus;
use App\Models\Budget;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\PlantationOperatingBudget;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakeEntityPlantationBudgetHttp(bool $rejectBudgets = false): void
{
    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($rejectBudgets) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
        $method = $request->method();

        if ($rejectBudgets && $method === 'PUT' && str_contains($path, '/budget-allocations/')) {
            return Http::response([
                'message' => 'Unit kebun tidak ditemukan untuk Finance Entity tersebut.',
            ], 422);
        }

        if ($method === 'POST' && $path === '/api/internal/plantation-entities') {
            return Http::response([
                'data' => [
                    'public_id' => '01PLANTATIONENTITYTEST00001',
                    'name' => $request['name'] ?? 'Kebun',
                    'finance_entity_public_id' => $request['finance_entity_public_id'] ?? null,
                ],
            ], 201);
        }

        if ($method === 'PUT' && str_contains($path, '/budget-allocations/')) {
            return Http::response([
                'data' => [
                    'public_id' => '01PLANTALLOCTEST000000001',
                    'finance_budget_public_id' => $request['budget_public_id'] ?? basename($path),
                    'name' => $request['name'] ?? null,
                    'allocated_amount' => (string) ($request['allocated_amount'] ?? 0),
                    'status' => 'ACTIVE',
                ],
            ]);
        }

        return Http::response(['data' => ['ok' => true, 'is_active' => true]]);
    });
}

function activateEntityPlantationBusiness(): FinanceEntity
{
    fakeEntityPlantationBudgetHttp();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Dashboard Anggaran']);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    return $business->fresh();
}

function grantOperatingBudgetAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function operatingBudgetCashAccount(FinanceEntity $entity, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Usaha Anggaran',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

it('creates a plantation operating budget from the finance dashboard when plantation is active', function () {
    $business = activateEntityPlantationBusiness();
    $account = operatingBudgetCashAccount($business, 5_000_000);
    $balanceBefore = app(FinanceAccountBalanceService::class)->balance($account->fresh());
    grantOperatingBudgetAccess($business);

    $this->get(route('entity.budgets.create', $business))
        ->assertOk()
        ->assertSee('Tambah Anggaran Kebun')
        ->assertSee('Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.')
        ->assertSee('Nama anggaran')
        ->assertSee('Periode mulai')
        ->assertSee('Periode selesai')
        ->assertSee('Alokasi')
        ->assertSee('entity-form-grid', false)
        ->assertSee('allocated_amount', false)
        ->assertDontSee('name="category_id"', false)
        ->assertDontSee('menambah saldo');

    $this->post(route('entity.budgets.store', $business), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 'Rp 50.000.000',
    ])
        ->assertRedirect(route('entity.budgets.index', $business))
        ->assertSessionHas('success');

    $budget = PlantationOperatingBudget::query()->first();
    expect($budget)->not->toBeNull()
        ->and($budget->finance_entity_id)->toBe($business->id)
        ->and($budget->name)->toBe('Anggaran Operasional September')
        ->and($budget->period_start?->toDateString())->toBe('2026-09-01')
        ->and($budget->period_end?->toDateString())->toBe('2026-09-30')
        ->and((float) $budget->allocated_amount)->toBe(50_000_000.0)
        ->and($budget->status)->toBe(PlantationOperatingBudgetStatus::ACTIVE)
        ->and($budget->public_id)->not->toBeEmpty()
        ->and(Budget::query()->count())->toBe(0)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe($balanceBefore);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($business, $budget) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        return $request->method() === 'PUT'
            && str_ends_with($path, '/budget-allocations/'.$budget->public_id)
            && $request['budget_public_id'] === $budget->public_id
            && $request['finance_entity_public_id'] === $business->public_id
            && $request['name'] === 'Anggaran Operasional September'
            && $request['period_start'] === '2026-09-01'
            && $request['period_end'] === '2026-09-30'
            && (float) $request['allocated_amount'] === 50_000_000.0;
    });

    $this->get(route('entity.budgets.index', $business))
        ->assertOk()
        ->assertSee('Tambah Anggaran Kebun')
        ->assertSee('Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.')
        ->assertSee('Anggaran Operasional September')
        ->assertSee('Status sinkronisasi')
        ->assertSee('Kirim ulang')
        ->assertSee('Ubah')
        ->assertSee('entity-table-responsive', false)
        ->assertDontSee('menambah saldo');
});

it('keeps the dashboard budget and marks sync error when plantation rejects the push', function () {
    $rejectBudgets = false;

    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$rejectBudgets) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
        $method = $request->method();

        if ($rejectBudgets && $method === 'PUT' && str_contains($path, '/budget-allocations/')) {
            return Http::response([
                'message' => 'Unit kebun tidak ditemukan untuk Finance Entity tersebut.',
            ], 422);
        }

        if ($method === 'POST' && $path === '/api/internal/plantation-entities') {
            return Http::response([
                'data' => [
                    'public_id' => '01PLANTATIONENTITYTEST00001',
                    'name' => $request['name'] ?? 'Kebun',
                    'finance_entity_public_id' => $request['finance_entity_public_id'] ?? null,
                ],
            ], 201);
        }

        return Http::response(['data' => ['ok' => true, 'is_active' => true]]);
    });

    $business = FinanceEntity::factory()->business()->create();
    $account = operatingBudgetCashAccount($business, 3_000_000);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));
    $balanceBefore = app(FinanceAccountBalanceService::class)->balance($account->fresh());

    $rejectBudgets = true;
    grantOperatingBudgetAccess($business->fresh());

    $this->post(route('entity.budgets.store', $business->fresh()), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])
        ->assertRedirect(route('entity.budgets.index', $business))
        ->assertSessionHas('danger');

    $budget = PlantationOperatingBudget::query()->first();
    expect($budget)->not->toBeNull()
        ->and($budget->status)->toBe(PlantationOperatingBudgetStatus::SYNC_ERROR)
        ->and($budget->last_error)->not->toBeEmpty()
        ->and(Budget::query()->count())->toBe(0)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe($balanceBefore);

    $this->get(route('entity.budgets.index', $business))
        ->assertOk()
        ->assertSee('SYNC_ERROR')
        ->assertSee('Gagal sinkron')
        ->assertSee('Kirim ulang')
        ->assertSee($budget->last_error);
});

it('resyncs an existing dashboard budget without creating a duplicate', function () {
    $business = activateEntityPlantationBusiness();
    grantOperatingBudgetAccess($business);

    $this->post(route('entity.budgets.store', $business), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])->assertRedirect();

    $budget = PlantationOperatingBudget::query()->first();
    expect($budget)->not->toBeNull();

    $this->post(route('entity.budgets.operating.sync', [$business, $budget]))
        ->assertRedirect(route('entity.budgets.index', $business))
        ->assertSessionHas('success');
    $this->post(route('entity.budgets.operating.sync', [$business, $budget]))
        ->assertRedirect(route('entity.budgets.index', $business));

    expect(PlantationOperatingBudget::query()->count())->toBe(1)
        ->and($budget->fresh()->status)->toBe(PlantationOperatingBudgetStatus::ACTIVE)
        ->and($budget->fresh()->public_id)->toBe($budget->public_id);

    $puts = collect(Http::recorded())
        ->filter(function (array $pair) use ($budget) {
            [$request] = $pair;
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

            return $request->method() === 'PUT'
                && str_ends_with($path, '/budget-allocations/'.$budget->public_id);
        });

    expect($puts)->toHaveCount(3);
});

it('rejects a forged public_id on dashboard create so the existing budget is not replaced', function () {
    $business = activateEntityPlantationBusiness();
    grantOperatingBudgetAccess($business);

    $this->post(route('entity.budgets.store', $business), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])->assertRedirect();

    $this->from(route('entity.budgets.create', $business))
        ->post(route('entity.budgets.store', $business), [
            'name' => 'Anggaran Kedua',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'allocated_amount' => '1000000',
            'public_id' => '01FORGEDBUDGETPUBLICID0000',
        ])
        ->assertRedirect(route('entity.budgets.create', $business))
        ->assertSessionHasErrors('public_id');

    expect(PlantationOperatingBudget::query()->count())->toBe(1);
});

it('does not let a family entity create a plantation operating budget', function () {
    fakeEntityPlantationBudgetHttp();
    $family = FinanceEntity::factory()->family()->create();
    grantOperatingBudgetAccess($family);

    $this->post(route('entity.budgets.store', $family), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])->assertNotFound();

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('validates that plantation integration must be active before creating a kebun budget', function () {
    Http::fake();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => '01PLANTATIONENTITYTEST00001',
        'status' => PlantationIntegrationStatus::INACTIVE,
    ]);
    grantOperatingBudgetAccess($business);

    $this->from(route('entity.budgets.create', $business))
        ->post(route('entity.budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => '50000000',
        ])
        ->assertRedirect(route('entity.budgets.create', $business))
        ->assertSessionHasErrors('name');

    expect(PlantationOperatingBudget::query()->count())->toBe(0)
        ->and(Budget::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('rejects plantation budget fields when the business has no plantation integration', function () {
    Http::fake();
    $business = FinanceEntity::factory()->business()->create();
    grantOperatingBudgetAccess($business);

    $this->from(route('entity.budgets.create', $business))
        ->post(route('entity.budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => '50000000',
        ])
        ->assertRedirect(route('entity.budgets.create', $business))
        ->assertSessionHasErrors('name');

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('rejects a zero allocation and an inverted period', function () {
    $business = activateEntityPlantationBusiness();
    grantOperatingBudgetAccess($business);

    $this->from(route('entity.budgets.create', $business))
        ->post(route('entity.budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => '0',
        ])
        ->assertRedirect(route('entity.budgets.create', $business))
        ->assertSessionHasErrors('allocated_amount');

    $this->from(route('entity.budgets.create', $business))
        ->post(route('entity.budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-30',
            'period_end' => '2026-09-01',
            'allocated_amount' => '50000000',
        ])
        ->assertRedirect(route('entity.budgets.create', $business))
        ->assertSessionHasErrors('period_end');

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
});

it('does not let one business sync another entity plantation budget', function () {
    $businessA = activateEntityPlantationBusiness();
    grantOperatingBudgetAccess($businessA);
    $this->post(route('entity.budgets.store', $businessA), [
        'name' => 'Anggaran A',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])->assertRedirect();
    $budgetA = PlantationOperatingBudget::query()->first();

    $businessB = FinanceEntity::factory()->business()->create(['name' => 'Usaha Lain']);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $businessB));
    grantOperatingBudgetAccess($businessB->fresh());

    $this->post(route('entity.budgets.operating.sync', [$businessB->fresh(), $budgetA]))
        ->assertNotFound();

    expect(PlantationOperatingBudget::query()->count())->toBe(1);
});

it('updates a plantation operating budget from the finance dashboard without changing cash or public_id', function () {
    $business = activateEntityPlantationBusiness();
    $account = operatingBudgetCashAccount($business, 5_000_000);
    $balanceBefore = app(FinanceAccountBalanceService::class)->balance($account->fresh());
    grantOperatingBudgetAccess($business);

    $this->post(route('entity.budgets.store', $business), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])->assertRedirect();

    $budget = PlantationOperatingBudget::query()->first();
    expect($budget)->not->toBeNull();

    $this->get(route('entity.budgets.operating.edit', [$business, $budget]))
        ->assertOk()
        ->assertSee('Ubah Anggaran Kebun')
        ->assertSee('Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.');

    $this->put(route('entity.budgets.operating.update', [$business, $budget]), [
        'name' => 'Anggaran Operasional Oktober',
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
        'allocated_amount' => 'Rp 60.000.000',
    ])
        ->assertRedirect(route('entity.budgets.index', $business))
        ->assertSessionHas('success');

    $fresh = $budget->fresh();
    expect($fresh->name)->toBe('Anggaran Operasional Oktober')
        ->and($fresh->public_id)->toBe($budget->public_id)
        ->and($fresh->period_start?->toDateString())->toBe('2026-10-01')
        ->and($fresh->period_end?->toDateString())->toBe('2026-10-31')
        ->and((float) $fresh->allocated_amount)->toBe(60_000_000.0)
        ->and($fresh->status)->toBe(PlantationOperatingBudgetStatus::ACTIVE)
        ->and(PlantationOperatingBudget::query()->count())->toBe(1)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe($balanceBefore);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($business, $budget) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        return $request->method() === 'PUT'
            && str_ends_with($path, '/budget-allocations/'.$budget->public_id)
            && $request['name'] === 'Anggaran Operasional Oktober'
            && $request['finance_entity_public_id'] === $business->public_id
            && (float) $request['allocated_amount'] === 60_000_000.0;
    });
});

it('does not let one business edit another entity plantation budget', function () {
    $businessA = activateEntityPlantationBusiness();
    grantOperatingBudgetAccess($businessA);
    $this->post(route('entity.budgets.store', $businessA), [
        'name' => 'Anggaran A',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ])->assertRedirect();
    $budgetA = PlantationOperatingBudget::query()->first();

    $businessB = FinanceEntity::factory()->business()->create(['name' => 'Usaha Lain']);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $businessB));
    grantOperatingBudgetAccess($businessB->fresh());

    $this->get(route('entity.budgets.operating.edit', [$businessB->fresh(), $budgetA]))
        ->assertNotFound();
    $this->put(route('entity.budgets.operating.update', [$businessB->fresh(), $budgetA]), [
        'name' => 'Anggaran Diretas',
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
        'allocated_amount' => '1000',
    ])->assertNotFound();

    expect($budgetA->fresh()->name)->toBe('Anggaran A')
        ->and(PlantationOperatingBudget::query()->count())->toBe(1);
});
