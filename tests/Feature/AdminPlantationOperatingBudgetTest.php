<?php

use App\Enums\AuditAction;
use App\Enums\PlantationIntegrationStatus;
use App\Enums\PlantationOperatingBudgetStatus;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\PlantationOperatingBudget;
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

it('rejects a plantation operating budget when management kebun is not connected', function () {
    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()
        ->post(route('admin.plantation-integrations.operating-budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => 'Rp 50.000.000',
        ])
        ->assertRedirect(route('admin.plantation-integrations.index'));

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('sends an idempotent finance-owned budget contract to plantation', function () {
    $business = activatePlantationBusiness();

    actingAdmin()
        ->post(route('admin.plantation-integrations.operating-budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => 'Rp 50.000.000',
        ])
        ->assertRedirect(route('admin.plantation-integrations.operating-budgets.index', $business))
        ->assertSessionHas('success');

    $budget = PlantationOperatingBudget::query()->first();
    expect($budget)->not->toBeNull()
        ->and($budget->status)->toBe(PlantationOperatingBudgetStatus::ACTIVE)
        ->and((float) $budget->allocated_amount)->toBe(50_000_000.0);

    actingAdmin()
        ->from(route('admin.plantation-integrations.operating-budgets.create', $business))
        ->post(route('admin.plantation-integrations.operating-budgets.store', $business), [
            'name' => 'Anggaran Kedua',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'allocated_amount' => '1000000',
            'public_id' => '01FORGEDBUDGETPUBLICID0000',
        ])
        ->assertRedirect(route('admin.plantation-integrations.operating-budgets.create', $business))
        ->assertSessionHasErrors('public_id');

    expect(PlantationOperatingBudget::query()->count())->toBe(1);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($business, $budget) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        return $request->method() === 'PUT'
            && str_ends_with($path, '/budget-allocations/'.$budget->public_id)
            && $request['budget_public_id'] === $budget->public_id
            && $request['finance_entity_public_id'] === $business->public_id
            && $request['name'] === 'Anggaran Operasional September'
            && $request['period_start'] === '2026-09-01'
            && $request['period_end'] === '2026-09-30'
            && (float) $request['allocated_amount'] === 50_000_000.0
            && ! isset($request['category_id'])
            && ! isset($request['amount_saldo']);
    });
});

it('keeps a local budget when plantation rejects the sync', function () {
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
    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    $rejectBudgets = true;

    actingAdmin()
        ->post(route('admin.plantation-integrations.operating-budgets.store', $business->fresh()), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => '50000000',
        ])
        ->assertRedirect()
        ->assertSessionHas('danger');

    $budget = PlantationOperatingBudget::query()->first();
    expect($budget)->not->toBeNull()
        ->and($budget->status)->toBe(PlantationOperatingBudgetStatus::SYNC_ERROR);
});

it('records plantation operating budget creation in the audit log', function () {
    $business = activatePlantationBusiness();

    actingAdmin()->post(route('admin.plantation-integrations.operating-budgets.store', $business), [
        'name' => 'Anggaran Operasional September',
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated_amount' => '50000000',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => AuditAction::PLANTATION_OPERATING_BUDGET_CREATED->value,
        'finance_entity_id' => $business->id,
    ]);
});

it('requires an active plantation integration', function () {
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => '01PLANTATIONENTITYTEST00001',
        'status' => PlantationIntegrationStatus::INACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.operating-budgets.store', $business), [
            'name' => 'Anggaran Operasional September',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => '50000000',
        ])
        ->assertRedirect()
        ->assertSessionHas('danger');

    expect(PlantationOperatingBudget::query()->count())->toBe(0);
    Http::assertNothingSent();
});
