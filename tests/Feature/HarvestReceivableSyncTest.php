<?php

use App\Enums\HarvestFinanceEventType;
use App\Enums\PlantationIntegrationStatus;
use App\Enums\ReceivableStatus;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\PlantationIntegration;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Services\FinanceAccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const HARVEST_SALE_PUBLIC_ID = '01HARVESTSALEPUBLICID00001';
const HARVEST_PAYMENT_PUBLIC_ID = '01HARVESTSALEPAYMENTID0001';
const PLANTATION_ENTITY_PUBLIC_ID = '01PLANTATIONENTITYHARVEST01';

function harvestFinanceHeaders(string $token = 'testing-plantation-service-token'): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

function linkedBusiness(): array
{
    $entity = FinanceEntity::factory()->business()->create();
    $account = cashAccount($entity, 'Kas Kebun', 1_000_000);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    return [$entity, $account];
}

function harvestSalePayload(array $overrides = []): array
{
    return array_merge([
        'public_id' => HARVEST_SALE_PUBLIC_ID,
        'buyer_name' => 'PT Pembeli Sawit',
        'sale_date' => now()->toDateString(),
        'invoice_number' => 'INV-1',
        'description' => 'TBS',
        'total_amount' => '3000000',
        'status' => 'POSTED',
        'payment_status' => 'UNPAID',
    ], $overrides);
}

function harvestPostedEvent(array $saleOverrides = []): array
{
    return [
        'event' => HarvestFinanceEventType::HARVEST_SALE_POSTED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'finance_entity_public_id' => null,
        'sale' => harvestSalePayload($saleOverrides),
    ];
}

it('rejects harvest finance events without the plantation bearer token', function () {
    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent())
        ->assertUnauthorized();

    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent(), harvestFinanceHeaders('wrong'))
        ->assertUnauthorized();
});

it('creates a receivable from HARVEST_SALE_POSTED without changing cash or income', function () {
    [$entity, $account] = linkedBusiness();

    $this->postJson('/api/internal/plantation-harvest-events', [
        ...harvestPostedEvent(),
        'finance_entity_public_id' => $entity->public_id,
    ], harvestFinanceHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', ReceivableStatus::OPEN->value);

    $receivable = Receivable::query()->first();

    expect($receivable)->not->toBeNull()
        ->and($receivable->party_name)->toBe('PT Pembeli Sawit')
        ->and((float) $receivable->principal_amount)->toBe(3_000_000.0)
        ->and((float) $receivable->remaining_balance)->toBe(3_000_000.0)
        ->and($receivable->status)->toBe(ReceivableStatus::OPEN)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(1_000_000.0)
        ->and(Income::query()->count())->toBe(0);

    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent(), harvestFinanceHeaders())
        ->assertOk();

    expect(Receivable::query()->count())->toBe(1);
});

it('records HARVEST_SALE_PAYMENT_RECEIVED against cash and reduces remaining balance', function () {
    [$entity, $account] = linkedBusiness();

    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent(), harvestFinanceHeaders())->assertOk();

    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'finance_entity_public_id' => $entity->public_id,
        'sale' => harvestSalePayload(),
        'payment' => [
            'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
            'amount' => '1000000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reference_number' => 'TRX-1',
            'status' => 'ACTIVE',
        ],
    ], harvestFinanceHeaders())->assertOk()->assertJsonPath('data.status', ReceivableStatus::PARTIALLY_PAID->value);

    $receivable = Receivable::query()->first();

    expect((float) $receivable->remaining_balance)->toBe(2_000_000.0)
        ->and($receivable->status)->toBe(ReceivableStatus::PARTIALLY_PAID)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(2_000_000.0)
        ->and(Income::query()->count())->toBe(0)
        ->and(ReceivablePayment::query()->count())->toBe(1);

    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'sale' => harvestSalePayload(),
        'payment' => [
            'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
            'amount' => '1000000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'CASH',
        ],
    ], harvestFinanceHeaders())->assertOk();

    expect(ReceivablePayment::query()->count())->toBe(1)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(2_000_000.0);
});

it('marks the receivable PAID when harvest sale is paid in full immediately', function () {
    [$entity, $account] = linkedBusiness();

    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent(), harvestFinanceHeaders())->assertOk();

    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'finance_entity_public_id' => $entity->public_id,
        'sale' => harvestSalePayload(['payment_status' => 'PAID']),
        'payment' => [
            'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
            'amount' => '3000000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'BANK_TRANSFER',
        ],
    ], harvestFinanceHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', ReceivableStatus::PAID->value);

    $receivable = Receivable::query()->first();

    expect((float) $receivable->remaining_balance)->toBe(0.0)
        ->and($receivable->status)->toBe(ReceivableStatus::PAID)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(4_000_000.0)
        ->and(Income::query()->count())->toBe(0);
});

it('creates the receivable from the payment event when POSTED was missed', function () {
    [$entity, $account] = linkedBusiness();

    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'sale' => harvestSalePayload(),
        'payment' => [
            'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
            'amount' => '3000000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'CASH',
        ],
    ], harvestFinanceHeaders())->assertOk()->assertJsonPath('data.status', ReceivableStatus::PAID->value);

    expect(Receivable::query()->count())->toBe(1)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(4_000_000.0);
});

it('reverses a harvest payment and restores remaining balance and cash', function () {
    [$entity, $account] = linkedBusiness();

    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent(), harvestFinanceHeaders())->assertOk();
    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'sale' => harvestSalePayload(),
        'payment' => [
            'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
            'amount' => '500000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'CASH',
        ],
    ], harvestFinanceHeaders())->assertOk();

    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_PAYMENT_REVERSED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'sale' => harvestSalePayload(),
        'payment' => [
            'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
            'amount' => '500000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'status' => 'REVERSED',
        ],
    ], harvestFinanceHeaders())->assertOk();

    $receivable = Receivable::query()->first();

    expect((float) $receivable->remaining_balance)->toBe(3_000_000.0)
        ->and($receivable->status)->toBe(ReceivableStatus::OPEN)
        ->and(ReceivablePayment::query()->count())->toBe(1)
        ->and(ReceivablePayment::query()->first()->status->value)->toBe('REVERSED')
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(1_000_000.0);
});

it('voids an unpaid harvest receivable when the sale is cancelled', function () {
    linkedBusiness();

    $this->postJson('/api/internal/plantation-harvest-events', harvestPostedEvent(), harvestFinanceHeaders())->assertOk();
    expect(Receivable::query()->count())->toBe(1);

    $this->postJson('/api/internal/plantation-harvest-events', [
        'event' => HarvestFinanceEventType::HARVEST_SALE_CANCELLED->value,
        'plantation_entity_public_id' => PLANTATION_ENTITY_PUBLIC_ID,
        'sale' => harvestSalePayload(['status' => 'CANCELLED']),
    ], harvestFinanceHeaders())->assertOk();

    expect(Receivable::query()->count())->toBe(1)
        ->and(Receivable::query()->first()->cancelled_at)->not->toBeNull();
});

it('pulls posted harvest sales from plantation during admin sync', function () {
    Http::preventStrayRequests();
    [$entity, $account] = linkedBusiness();

    Http::fake([
        'http://plantation.test/api/internal/plantation-entities/'.PLANTATION_ENTITY_PUBLIC_ID.'/harvest-sales' => Http::response([
            'data' => [[
                ...harvestSalePayload(),
                'payments' => [[
                    'public_id' => HARVEST_PAYMENT_PUBLIC_ID,
                    'amount' => '3000000',
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'CASH',
                    'status' => 'ACTIVE',
                ]],
            ]],
        ]),
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.sync-harvest-receivables', $entity))
        ->assertRedirect(route('admin.plantation-integrations.show', $entity))
        ->assertSessionHas('success');

    $receivable = Receivable::query()->first();

    expect($receivable)->not->toBeNull()
        ->and($receivable->status)->toBe(ReceivableStatus::PAID)
        ->and((float) $receivable->remaining_balance)->toBe(0.0)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(4_000_000.0)
        ->and(Income::query()->count())->toBe(0);
});
