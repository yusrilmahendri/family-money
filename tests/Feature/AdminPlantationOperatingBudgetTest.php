<?php

use App\Enums\AuditAction;
use App\Enums\PlantationOperatingBudgetStatus;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use App\Services\PlantationOperatingBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakePlantationWithBudgets(): void
{
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
        $method = $request->method();

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

function activatePlantationBusiness(): FinanceEntity
{
    fakePlantationWithBudgets();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Anggaran Kebun']);
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    return $business->fresh();
}

function createPlantationOperatingBudget(FinanceEntity $business, array $overrides = []): PlantationOperatingBudget
{
    return app(PlantationOperatingBudgetService::class)->create($business, array_merge([
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 50_000_000.0,
    ], $overrides));
}

function adminOperatingBudgetsPath(FinanceEntity $entity, string $suffix = ''): string
{
    return '/admin/plantation-integrations/'.$entity->public_id.'/operating-budgets'.($suffix !== '' ? '/'.$suffix : '');
}

it('does not sync historical category budgets to plantation', function () {
    Http::fake();
    $business = FinanceEntity::factory()->business()->create();
    $category = Category::factory()->create(['finance_entity_id' => $business->id]);

    Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $category->id,
        'amount' => 50_000_000,
        'amount_saldo' => 0,
        'periode' => now(),
        'description' => 'Anggaran lama',
    ]);

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does not let admin create a plantation operating budget through hidden urls', function () {
    $business = activatePlantationBusiness();
    $payload = [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => 'Rp 50.000.000',
    ];

    actingAdmin()
        ->get(adminOperatingBudgetsPath($business, 'create'))
        ->assertNotFound();

    actingAdmin()
        ->post(adminOperatingBudgetsPath($business), $payload)
        ->assertStatus(405);

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
});

it('does not let admin edit a plantation operating budget through hidden urls', function () {
    $business = activatePlantationBusiness();
    $budget = createPlantationOperatingBudget($business);

    actingAdmin()
        ->get(adminOperatingBudgetsPath($business, $budget->public_id.'/edit'))
        ->assertNotFound();

    actingAdmin()
        ->put(adminOperatingBudgetsPath($business, $budget->public_id), [
            'name' => 'Anggaran Diubah Admin',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'allocated_amount' => '1000000',
        ])
        ->assertNotFound();

    expect($budget->fresh()->name)->toBe('Anggaran Operasional September')
        ->and((float) $budget->fresh()->allocated_amount)->toBe(50_000_000.0)
        ->and(PlantationOperatingBudget::query()->count())->toBe(1);
});

it('lets admin monitor existing plantation budgets without a create action', function () {
    $business = activatePlantationBusiness();
    $budget = createPlantationOperatingBudget($business);

    actingAdmin()
        ->get(route('admin.plantation-integrations.operating-budgets.index', $business))
        ->assertOk()
        ->assertSee($budget->name)
        ->assertSee('Kirim ulang')
        ->assertSee('dashboard Finance')
        ->assertSee('Status sinkronisasi')
        ->assertDontSee('Buat anggaran kebun')
        ->assertDontSee('Buat dari control plane');
});

it('resends an existing plantation operating budget without creating a duplicate', function () {
    $business = activatePlantationBusiness();
    $budget = createPlantationOperatingBudget($business);

    actingAdmin()
        ->post(route('admin.plantation-integrations.operating-budgets.sync', [$business, $budget]))
        ->assertRedirect(route('admin.plantation-integrations.operating-budgets.index', $business))
        ->assertSessionHas('success');

    actingAdmin()
        ->post(route('admin.plantation-integrations.operating-budgets.sync', [$business, $budget]))
        ->assertRedirect(route('admin.plantation-integrations.operating-budgets.index', $business));

    expect(PlantationOperatingBudget::query()->count())->toBe(1)
        ->and($budget->fresh()->public_id)->toBe($budget->public_id)
        ->and($budget->fresh()->status)->toBe(PlantationOperatingBudgetStatus::ACTIVE);

    $puts = collect(Http::recorded())
        ->filter(function (array $pair) use ($budget) {
            [$request] = $pair;
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

            return $request->method() === 'PUT'
                && str_ends_with($path, '/budget-allocations/'.$budget->public_id);
        });

    expect($puts->count())->toBeGreaterThanOrEqual(3);
});

it('records plantation operating budget creation in the audit log from the finance-owned service', function () {
    $business = activatePlantationBusiness();
    createPlantationOperatingBudget($business);

    $this->assertDatabaseHas('audit_logs', [
        'action' => AuditAction::PLANTATION_OPERATING_BUDGET_CREATED->value,
        'finance_entity_id' => $business->id,
    ]);
});

it('rejects a disconnected entity from the admin monitoring page', function () {
    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()
        ->get(route('admin.plantation-integrations.operating-budgets.index', $business))
        ->assertRedirect(route('admin.plantation-integrations.index'));

    actingAdmin()
        ->post(adminOperatingBudgetsPath($business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => '50000000',
        ])
        ->assertStatus(405);

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
    Http::assertNothingSent();
});
