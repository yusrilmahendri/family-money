<?php

use App\Enums\IntegrationEventType;
use App\Enums\PlantationIntegrationStatus;
use App\Enums\ReceivablePaymentStatus;
use App\Enums\ReceivableStatus;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\PlantationIntegration;
use App\Models\ProcessedIntegrationEvent;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Transaction;
use App\Services\FinanceAccountBalanceService;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const INTEGRATION_PLANTATION_ID = '01PLANTATIONENTITYEVENT0001';

function integrationHeaders(string $token = 'testing-plantation-service-token'): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

function integrationBusiness(): array
{
    $entity = FinanceEntity::factory()->business()->create();
    $account = cashAccount($entity, 'Kas Usaha', 1_000_000);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => INTEGRATION_PLANTATION_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    return [$entity, $account];
}

function integrationEnvelope(IntegrationEventType $type, string $source, array $payload, ?FinanceEntity $entity = null): array
{
    return [
        'event_id' => (string) Str::ulid(),
        'event_type' => $type->value,
        'event_version' => 1,
        'occurred_at' => now()->toIso8601String(),
        'plantation_entity_public_id' => INTEGRATION_PLANTATION_ID,
        'finance_entity_public_id' => $entity?->public_id ?? 'missing',
        'source_public_id' => $source,
        'payload' => $payload,
    ];
}

it('rejects plantation events without a valid service token', function () {
    $this->postJson('/api/internal/plantation/events', [])->assertUnauthorized();
    $this->postJson('/api/internal/plantation/events', [], integrationHeaders('wrong'))->assertUnauthorized();
});

it('rejects unknown plantation mapping and inactive integration', function () {
    [$entity] = integrationBusiness();
    $envelope = integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_POSTED, 'src1', [
        'purchase_public_id' => 'src1',
        'purchase_date' => now()->toDateString(),
        'amount' => '1000',
        'supplier' => ['public_id' => null, 'name' => 'A'],
    ], $entity);
    $envelope['plantation_entity_public_id'] = 'unknown';

    $this->postJson('/api/internal/plantation/events', $envelope, integrationHeaders())
        ->assertStatus(422);

    $other = FinanceEntity::factory()->business()->create();
    cashAccount($other, 'Kas Lain', 0);
    $envelope['plantation_entity_public_id'] = INTEGRATION_PLANTATION_ID;
    $envelope['finance_entity_public_id'] = $other->public_id;
    $this->postJson('/api/internal/plantation/events', $envelope, integrationHeaders())
        ->assertStatus(422);
});

it('processes purchase expense once and reverses without deleting history', function () {
    [$entity, $account] = integrationBusiness();
    $source = (string) Str::ulid();
    $envelope = integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_POSTED, $source, [
        'purchase_public_id' => $source,
        'purchase_date' => now()->toDateString(),
        'amount' => '1500000.00',
        'description' => 'Pupuk',
        'supplier' => ['public_id' => '01SUP', 'name' => 'CV Tani'],
        'category' => 'FERTILIZER',
    ], $entity);

    $this->postJson('/api/internal/plantation/events', $envelope, integrationHeaders())
        ->assertOk()
        ->assertJsonPath('already_processed', false);

    expect(Transaction::query()->count())->toBe(1)
        ->and((float) Transaction::query()->first()->amount)->toBe(1_500_000.0)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(-500_000.0)
        ->and(Income::query()->count())->toBe(0);

    $this->postJson('/api/internal/plantation/events', $envelope, integrationHeaders())
        ->assertOk()
        ->assertJsonPath('already_processed', true);
    expect(Transaction::query()->count())->toBe(1);

    $conflict = $envelope;
    $conflict['payload']['amount'] = '1.00';
    $this->postJson('/api/internal/plantation/events', $conflict, integrationHeaders())
        ->assertStatus(409)
        ->assertJsonPath('code', 'INTEGRITY_CONFLICT');

    $cancel = integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_CANCELLED, $source, [
        'purchase_public_id' => $source,
        'cancelled_reason' => 'Batal',
    ], $entity);
    $this->postJson('/api/internal/plantation/events', $cancel, integrationHeaders())->assertOk();

    expect(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->first()->reversed_at)->not->toBeNull()
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(1_000_000.0);
});

it('returns dependency not ready when purchase cancel arrives first', function () {
    [$entity] = integrationBusiness();
    $source = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_CANCELLED, $source, [
        'purchase_public_id' => $source,
    ], $entity), integrationHeaders())
        ->assertStatus(409)
        ->assertJsonPath('code', 'DEPENDENCY_NOT_READY');
});

it('creates payroll expense only once', function () {
    [$entity, $account] = integrationBusiness();
    $source = (string) Str::ulid();
    $envelope = integrationEnvelope(IntegrationEventType::PLANTATION_PAYROLL_PAID, $source, [
        'payroll_public_id' => $source,
        'worker_public_id' => '01WORKER',
        'worker_name' => 'Budi',
        'work_activity_public_id' => '01ACT',
        'work_activity_title' => 'Panen',
        'work_type' => 'Panen',
        'activity_date' => now()->toDateString(),
        'paid_at' => now()->toIso8601String(),
        'payment_method' => 'CASH',
        'amount' => '150000.00',
    ], $entity);

    $this->postJson('/api/internal/plantation/events', $envelope, integrationHeaders())->assertOk();
    $this->postJson('/api/internal/plantation/events', $envelope, integrationHeaders())->assertJsonPath('already_processed', true);

    expect(Transaction::query()->count())->toBe(1)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(850_000.0);
});

it('creates harvest receivable without cash then payment and reversal', function () {
    [$entity, $account] = integrationBusiness();
    $saleId = (string) Str::ulid();
    $payId = (string) Str::ulid();

    $posted = integrationEnvelope(IntegrationEventType::HARVEST_SALE_POSTED, $saleId, [
        'sale_public_id' => $saleId,
        'sale_date' => now()->toDateString(),
        'invoice_number' => 'INV-1',
        'buyer' => ['public_id' => '01BUY', 'name' => 'PT Sawit'],
        'total_amount' => '3000000.00',
        'description' => 'TBS',
        'items' => [[
            'commodity' => 'PALM_OIL_FFB',
            'quantity' => '1000.000',
            'unit' => 'kg',
            'unit_price' => '3000.00',
            'line_total' => '3000000.00',
        ]],
    ], $entity);

    $this->postJson('/api/internal/plantation/events', $posted, integrationHeaders())->assertOk();
    $receivable = Receivable::query()->first();
    expect($receivable->status)->toBe(ReceivableStatus::OPEN)
        ->and((float) $receivable->remaining_balance)->toBe(3_000_000.0)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(1_000_000.0)
        ->and(Income::query()->count())->toBe(0);

    $paymentBeforeSale = integrationEnvelope(IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED, $payId, [
        'payment_public_id' => $payId,
        'sale_public_id' => (string) Str::ulid(),
        'payment_date' => now()->toDateString(),
        'payment_method' => 'CASH',
        'amount' => '1000000.00',
    ], $entity);
    $this->postJson('/api/internal/plantation/events', $paymentBeforeSale, integrationHeaders())
        ->assertStatus(409)
        ->assertJsonPath('code', 'DEPENDENCY_NOT_READY');

    $partial = integrationEnvelope(IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED, $payId, [
        'payment_public_id' => $payId,
        'sale_public_id' => $saleId,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'CASH',
        'amount' => '1000000.00',
    ], $entity);
    $this->postJson('/api/internal/plantation/events', $partial, integrationHeaders())->assertOk();
    $this->postJson('/api/internal/plantation/events', $partial, integrationHeaders())->assertJsonPath('already_processed', true);

    $receivable->refresh();
    expect((float) $receivable->remaining_balance)->toBe(2_000_000.0)
        ->and($receivable->status)->toBe(ReceivableStatus::PARTIALLY_PAID)
        ->and(ReceivablePayment::query()->count())->toBe(1)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(2_000_000.0)
        ->and(Income::query()->count())->toBe(0);

    $rest = integrationEnvelope(IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED, (string) Str::ulid(), [
        'payment_public_id' => (string) Str::ulid(),
        'sale_public_id' => $saleId,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'BANK_TRANSFER',
        'amount' => '2000000.00',
    ], $entity);
    $this->postJson('/api/internal/plantation/events', $rest, integrationHeaders())->assertOk();
    expect($receivable->fresh()->status)->toBe(ReceivableStatus::PAID)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(4_000_000.0);

    $reverse = integrationEnvelope(IntegrationEventType::HARVEST_SALE_PAYMENT_REVERSED, $payId, [
        'payment_public_id' => $payId,
        'sale_public_id' => $saleId,
        'amount' => '1000000.00',
    ], $entity);
    $this->postJson('/api/internal/plantation/events', $reverse, integrationHeaders())->assertOk();

    expect((float) $receivable->fresh()->remaining_balance)->toBe(1_000_000.0)
        ->and(ReceivablePayment::query()->where('status', ReceivablePaymentStatus::REVERSED)->count())->toBe(1)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(3_000_000.0);
});

it('cancels unpaid receivable and rejects cancel when active payment exists', function () {
    [$entity] = integrationBusiness();
    $saleId = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_POSTED, $saleId, [
        'sale_public_id' => $saleId,
        'sale_date' => now()->toDateString(),
        'buyer' => ['public_id' => '01BUY', 'name' => 'PT Sawit'],
        'total_amount' => '1000.00',
        'items' => [],
    ], $entity), integrationHeaders())->assertOk();

    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_CANCELLED, $saleId, [
        'sale_public_id' => $saleId,
    ], $entity), integrationHeaders())->assertOk();
    expect(Receivable::query()->first()->cancelled_at)->not->toBeNull();

    $saleId2 = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_POSTED, $saleId2, [
        'sale_public_id' => $saleId2,
        'sale_date' => now()->toDateString(),
        'buyer' => ['public_id' => '01BUY', 'name' => 'PT Sawit'],
        'total_amount' => '5000.00',
        'items' => [],
    ], $entity), integrationHeaders())->assertOk();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED, (string) Str::ulid(), [
        'payment_public_id' => (string) Str::ulid(),
        'sale_public_id' => $saleId2,
        'payment_date' => now()->toDateString(),
        'amount' => '1000.00',
        'payment_method' => 'CASH',
    ], $entity), integrationHeaders())->assertOk();

    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_CANCELLED, $saleId2, [
        'sale_public_id' => $saleId2,
    ], $entity), integrationHeaders())->assertStatus(422);
});

it('rejects events when the entity has no active default account', function () {
    $entity = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => INTEGRATION_PLANTATION_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PAYROLL_PAID, (string) Str::ulid(), [
        'payroll_public_id' => 'p1',
        'worker_name' => 'Budi',
        'work_activity_title' => 'Panen',
        'amount' => '1000',
        'paid_at' => now()->toIso8601String(),
    ], $entity), integrationHeaders())->assertStatus(422);
});

it('routes plantation cash postings only to the active default account', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $inactive = cashAccount($entity, 'Kas Lama Kebun', 5_000_000);
    $active = app(\App\Services\FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Aktif Kebun',
        'type' => \App\Enums\FinanceAccountType::CASH,
        'opening_balance' => 1_000_000,
    ]);
    app(\App\Services\FinanceAccountService::class)->deactivate($inactive);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => INTEGRATION_PLANTATION_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    expect($entity->fresh()->defaultAccount()?->id)->toBe($active->id)
        ->and($inactive->fresh()->is_active)->toBeFalse();

    $purchaseId = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_POSTED, $purchaseId, [
        'purchase_public_id' => $purchaseId,
        'purchase_date' => now()->toDateString(),
        'amount' => '100000',
        'supplier' => ['name' => 'CV Tani'],
    ], $entity), integrationHeaders())->assertOk();

    $payrollId = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PAYROLL_PAID, $payrollId, [
        'payroll_public_id' => $payrollId,
        'worker_name' => 'Budi',
        'work_activity_title' => 'Panen',
        'amount' => '50000',
        'paid_at' => now()->toIso8601String(),
    ], $entity), integrationHeaders())->assertOk();

    $saleId = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_POSTED, $saleId, [
        'sale_public_id' => $saleId,
        'sale_date' => now()->toDateString(),
        'buyer' => ['name' => 'PT Sawit'],
        'total_amount' => '200000.00',
        'items' => [],
    ], $entity), integrationHeaders())->assertOk();

    $payId = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED, $payId, [
        'payment_public_id' => $payId,
        'sale_public_id' => $saleId,
        'payment_date' => now()->toDateString(),
        'amount' => '200000.00',
        'payment_method' => 'CASH',
    ], $entity), integrationHeaders())->assertOk();

    expect(Transaction::query()->where('finance_account_id', $inactive->id)->count())->toBe(0)
        ->and(Transaction::query()->where('finance_account_id', $active->id)->count())->toBe(2)
        ->and(ReceivablePayment::query()->where('finance_account_id', $inactive->id)->count())->toBe(0)
        ->and(ReceivablePayment::query()->where('finance_account_id', $active->id)->count())->toBe(1);

    app(\App\Services\FinanceAccountService::class)->deactivate($active);

    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_POSTED, (string) Str::ulid(), [
        'purchase_public_id' => (string) Str::ulid(),
        'purchase_date' => now()->toDateString(),
        'amount' => '1000',
        'supplier' => ['name' => 'A'],
    ], $entity), integrationHeaders())
        ->assertStatus(422)
        ->assertJsonPath('message', 'Entity belum memiliki akun default yang aktif.');

    expect(Transaction::query()->where('finance_account_id', $inactive->id)->count())->toBe(0);
});

it('does not allow an event to target another finance entity', function () {
    [$entity] = integrationBusiness();
    $other = FinanceEntity::factory()->business()->create();
    cashAccount($other, 'Kas Other', 0);

    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_POSTED, 'x', [
        'purchase_public_id' => 'x',
        'purchase_date' => now()->toDateString(),
        'amount' => '1000',
        'supplier' => ['name' => 'A'],
    ], $other), integrationHeaders())->assertStatus(422);

    expect(ProcessedIntegrationEvent::query()->count())->toBe(0);
    expect(CanonicalJson::hash(['a' => 1, 'b' => 2]))->toBe(CanonicalJson::hash(['b' => 2, 'a' => 1]));
});
