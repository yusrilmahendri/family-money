<?php

use App\Enums\FinanceAccountType;
use App\Enums\ReceivableStatus;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Services\FinanceAccountService;
use App\Services\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function receivableService(): ReceivableService
{
    return app(ReceivableService::class);
}

it('creates a receivable without changing cash balance or income', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Rumah Piutang', 1_000_000);
    grantEntityAccess($entity);

    $this->post(route('entity.receivables.store', $entity), [
        'party_name' => 'Pak Budi',
        'principal_amount' => '10000000',
        'receivable_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'description' => 'Pinjaman',
        'finance_entity_id' => $entity->id,
        'status' => 'PAID',
        'remaining_balance' => '1',
    ])->assertSessionHasErrors(['finance_entity_id', 'status', 'remaining_balance']);

    $this->post(route('entity.receivables.store', $entity), [
        'party_name' => 'Pak Budi',
        'principal_amount' => '10000000',
        'receivable_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'description' => 'Pinjaman',
    ])->assertRedirect(route('entity.receivables.index', $entity));

    $receivable = Receivable::query()->first();

    expect($receivable)->not->toBeNull()
        ->and((float) $receivable->principal_amount)->toBe(10_000_000.0)
        ->and((float) $receivable->remaining_balance)->toBe(10_000_000.0)
        ->and($receivable->status)->toBe(ReceivableStatus::OPEN)
        ->and(balanceService()->balance($account->fresh()))->toBe(1_000_000.0)
        ->and(Income::query()->count())->toBe(0);

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Total Piutang Outstanding')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Total Saldo')
        ->assertSee('Rp 1.000.000');
});

it('records partial and full payments against the owning account only', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'BCA Piutang', 500_000);
    grantEntityAccess($entity);

    $this->post(route('entity.receivables.store', $entity), [
        'party_name' => 'Ibu Sari',
        'principal_amount' => '10000000',
        'receivable_date' => now()->toDateString(),
    ])->assertRedirect();

    $receivable = Receivable::query()->first();

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $account->id,
        'amount' => '4000000',
        'payment_date' => now()->toDateString(),
        'description' => 'Cicilan 1',
    ])->assertRedirect(route('entity.receivables.show', [$entity, $receivable]));

    $receivable->refresh();
    expect((float) $receivable->remaining_balance)->toBe(6_000_000.0)
        ->and($receivable->computedStatus())->toBe(ReceivableStatus::PARTIALLY_PAID)
        ->and($receivable->status)->toBe(ReceivableStatus::PARTIALLY_PAID)
        ->and(balanceService()->balance($account->fresh()))->toBe(4_500_000.0)
        ->and(Income::query()->count())->toBe(0);

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $account->id,
        'amount' => '6000000',
        'payment_date' => now()->toDateString(),
    ])->assertRedirect();

    $receivable->refresh();
    expect((float) $receivable->remaining_balance)->toBe(0.0)
        ->and($receivable->status)->toBe(ReceivableStatus::PAID)
        ->and(balanceService()->balance($account->fresh()))->toBe(10_500_000.0)
        ->and(ReceivablePayment::query()->count())->toBe(2);
});

it('rejects overpayment zero amount foreign account and inactive account', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $other = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Valid', 0);
    $foreign = cashAccount($other, 'Kas Asing', 0);
    $inactive = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Lama Piutang',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);

    grantEntityAccess($entity);
    $this->post(route('entity.receivables.store', $entity), [
        'party_name' => 'Debitur',
        'principal_amount' => '50000',
        'receivable_date' => now()->toDateString(),
    ]);
    $receivable = Receivable::query()->first();

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $account->id,
        'amount' => '60000',
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $account->id,
        'amount' => '0',
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $foreign->id,
        'amount' => '10000',
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('finance_account_id');

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $inactive->id,
        'amount' => '10000',
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('finance_account_id');

    expect(ReceivablePayment::query()->count())->toBe(0)
        ->and((float) $receivable->fresh()->remaining_balance)->toBe(50_000.0)
        ->and(balanceService()->balance($account->fresh()))->toBe(0.0)
        ->and(Route::has('entity.receivables.destroy'))->toBeFalse();
});

it('isolates receivables and nested payments between entities', function () {
    [$entityA, $entityB] = familyPair();
    $accountA = cashAccount($entityA, 'Kas A Piutang', 0);
    $accountB = cashAccount($entityB, 'Kas B Piutang', 0);
    grantEntityAccess($entityA);
    grantEntityAccess($entityB);

    $this->post(route('entity.receivables.store', $entityA), [
        'party_name' => 'Pihak A',
        'principal_amount' => '20000',
        'receivable_date' => now()->toDateString(),
    ]);
    $this->post(route('entity.receivables.store', $entityB), [
        'party_name' => 'Pihak B',
        'principal_amount' => '30000',
        'receivable_date' => now()->toDateString(),
    ]);

    $receivableA = Receivable::query()->where('finance_entity_id', $entityA->id)->first();
    $receivableB = Receivable::query()->where('finance_entity_id', $entityB->id)->first();

    $this->get(route('entity.receivables.index', $entityA))
        ->assertOk()
        ->assertSee('Pihak A')
        ->assertDontSee('Pihak B');

    $this->get(route('entity.receivables.show', [$entityA, $receivableB]))->assertNotFound();
    $this->post(route('entity.receivables.payments.store', [$entityA, $receivableB]), [
        'finance_account_id' => $accountA->id,
        'amount' => '10000',
        'payment_date' => now()->toDateString(),
    ])->assertNotFound();

    $this->post(route('entity.receivables.payments.store', [$entityB, $receivableB]), [
        'finance_account_id' => $accountB->id,
        'amount' => '10000',
        'payment_date' => now()->toDateString(),
    ])->assertRedirect();

    expect(ReceivablePayment::query()->where('receivable_id', $receivableB->id)->count())->toBe(1)
        ->and(ReceivablePayment::query()->where('receivable_id', $receivableA->id)->count())->toBe(0);
});

it('marks overdue receivables and keeps BUSINESS profit unchanged after payment', function () {
    $business = FinanceEntity::factory()->business()->create();
    $account = cashAccount($business, 'Kas Usaha Piutang', 0);
    businessIncome($business, 80_000, now(), $account);
    grantEntityAccess($business);

    $this->post(route('entity.receivables.store', $business), [
        'party_name' => 'Pelanggan Telat',
        'principal_amount' => '25000',
        'receivable_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
    ])->assertRedirect();

    $overdue = Receivable::query()->first();
    expect($overdue->computedStatus())->toBe(ReceivableStatus::OVERDUE)
        ->and($overdue->status)->toBe(ReceivableStatus::OVERDUE)
        ->and(profitService()->calculate($business)['profit'])->toBe(80_000.0)
        ->and(balanceService()->balance($account->fresh()))->toBe(80_000.0);

    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Piutang Jatuh Tempo')
        ->assertSee('Rp 25.000')
        ->assertSee('Laba / Rugi')
        ->assertSee('Rp 80.000');

    $this->post(route('entity.receivables.payments.store', [$business, $overdue]), [
        'finance_account_id' => $account->id,
        'amount' => '25000',
        'payment_date' => now()->toDateString(),
    ])->assertRedirect();

    expect($overdue->fresh()->status)->toBe(ReceivableStatus::PAID)
        ->and(profitService()->calculate($business)['profit'])->toBe(80_000.0)
        ->and((float) $business->incomes()->sum('amount'))->toBe(80_000.0)
        ->and(balanceService()->balance($account->fresh()))->toBe(105_000.0)
        ->and(Income::query()->count())->toBe(1);
});

it('rolls back a payment when the wrapping transaction fails', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Atomic Piutang', 200_000);
    $receivable = receivableService()->create($entity, [
        'party_name' => 'Atomic',
        'principal_amount' => 40_000,
        'receivable_date' => now()->toDateString(),
    ]);

    expect(function () use ($receivable, $account): void {
        DB::transaction(function () use ($receivable, $account): void {
            receivableService()->recordPayment($receivable, [
                'finance_account_id' => $account->id,
                'amount' => 15_000,
                'payment_date' => now()->toDateString(),
            ]);

            throw new RuntimeException('force rollback');
        });
    })->toThrow(RuntimeException::class);

    expect(ReceivablePayment::query()->count())->toBe(0)
        ->and((float) $receivable->fresh()->remaining_balance)->toBe(40_000.0)
        ->and(balanceService()->balance($account->fresh()))->toBe(200_000.0);
});

it('locks principal after a payment and detects invalid receivable records in audit', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Audit Piutang', 0);
    $other = FinanceEntity::factory()->family()->create();
    $foreign = cashAccount($other, 'Kas Asing Audit', 0);

    $receivable = receivableService()->create($entity, [
        'party_name' => 'Audit',
        'principal_amount' => 20_000,
        'receivable_date' => now()->toDateString(),
        'due_date' => now()->addWeek()->toDateString(),
    ]);
    receivableService()->recordPayment($receivable, [
        'finance_account_id' => $account->id,
        'amount' => 5_000,
        'payment_date' => now()->toDateString(),
    ]);

    expect(fn () => receivableService()->update($receivable->fresh(), [
        'party_name' => 'Audit',
        'principal_amount' => 30_000,
        'receivable_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    $stale = receivableService()->create($entity, [
        'party_name' => 'Stale Overdue',
        'principal_amount' => 8_000,
        'receivable_date' => now()->subDays(20)->toDateString(),
        'due_date' => now()->addDay()->toDateString(),
    ]);
    $stale->update([
        'due_date' => now()->subDays(3)->toDateString(),
        'status' => ReceivableStatus::OPEN,
        'remaining_balance' => 9_000,
    ]);

    ReceivablePayment::query()->create([
        'receivable_id' => $stale->id,
        'finance_account_id' => $foreign->id,
        'amount' => 1_000,
        'payment_date' => now()->toDateString(),
    ]);

    $before = Receivable::query()->count();

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Receivable Audit')
        ->expectsOutputToContain('Remaining exceeds principal')
        ->expectsOutputToContain('Orphan receivable records')
        ->assertFailed();

    $audit = receivableService()->audit();

    expect(Receivable::query()->count())->toBe($before)
        ->and($audit['remaining_exceeds_principal'])->toBeGreaterThan(0)
        ->and($audit['payment_mismatch'])->toBeGreaterThan(0)
        ->and($audit['account_entity_mismatch'])->toBeGreaterThan(0)
        ->and($audit['invalid_status'])->toBeGreaterThan(0)
        ->and($audit['unmarked_overdue'])->toBeGreaterThan(0)
        ->and($audit)->toHaveKeys(['orphan_receivables', 'orphan_payments', 'invalid_account_relation', 'negative_remaining']);
});
