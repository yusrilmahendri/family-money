<?php

use App\Enums\AuditAction;
use App\Enums\FinanceAccountType;
use App\Enums\IntegrationEventType;
use App\Enums\PlantationIntegrationStatus;
use App\Models\AuditLog;
use App\Models\ExternalFinancialReference;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use App\Models\Income;
use App\Models\PlantationIntegration;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Transaction;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function deleteTestAccounts(): FinanceAccountService
{
    return app(FinanceAccountService::class);
}

function deleteTestCash(FinanceEntity $entity, string $name, float $opening = 0): FinanceAccount
{
    return deleteTestAccounts()->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function deleteTestSibling(FinanceEntity $entity, string $name = 'BCA Cadangan'): FinanceAccount
{
    return deleteTestAccounts()->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::BANK,
        'bank_name' => 'Mandiri',
        'account_number' => '1234567890',
    ]);
}

function deleteTestGrantAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function assertAccountDeleteRejected(FinanceEntity $entity, FinanceAccount $account, string $message): void
{
    test()->from(route('entity.accounts.index', $entity))
        ->delete(route('entity.accounts.destroy', [$entity, $account]))
        ->assertRedirect(route('entity.accounts.index', $entity))
        ->assertSessionHas('danger', $message);

    test()->assertDatabaseHas('finance_accounts', ['id' => $account->id]);
}

it('deletes a new unused non-default account', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);
    deleteTestGrantAccess($entity);

    $this->delete(route('entity.accounts.destroy', [$entity, $account]))
        ->assertRedirect(route('entity.accounts.index', $entity))
        ->assertSessionHas('success', 'Rekening berhasil dihapus.');

    $this->assertDatabaseMissing('finance_accounts', ['id' => $account->id]);
});

it('rejects delete when the account has a transaction', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 25_000,
    ]);
    deleteTestGrantAccess($entity);

    expect(deleteTestAccounts()->hasFinancialHistory($account))->toBeTrue()
        ->and(deleteTestAccounts()->canBeDeleted($account))->toBeFalse();

    assertAccountDeleteRejected($entity, $account, FinanceAccountService::DELETE_BLOCKED_HISTORY);
});

it('rejects delete when the balance is zero but history exists', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);

    Income::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Setoran',
        'amount' => 50_000,
        'income_date' => now(),
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 50_000,
    ]);

    expect((float) app(\App\Services\FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(0.0)
        ->and(deleteTestAccounts()->hasFinancialHistory($account->fresh()))->toBeTrue();

    deleteTestGrantAccess($entity);
    assertAccountDeleteRejected($entity, $account, FinanceAccountService::DELETE_BLOCKED_HISTORY);
});

it('rejects delete of the default account even when unused', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $default = deleteTestCash($entity, 'Kas Utama Keluarga');
    deleteTestSibling($entity);
    deleteTestGrantAccess($entity);

    expect($default->fresh()->is_default)->toBeTrue()
        ->and(deleteTestAccounts()->hasFinancialHistory($default))->toBeFalse()
        ->and(deleteTestAccounts()->canBeDeleted($default))->toBeFalse();

    assertAccountDeleteRejected($entity, $default, FinanceAccountService::DELETE_BLOCKED_DEFAULT);
});

it('deletes an inactive account that has no financial history', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity, 'Kas Utama Keluarga Cadangan');
    deleteTestAccounts()->deactivate($account);
    deleteTestGrantAccess($entity);

    expect($account->fresh()->is_active)->toBeFalse()
        ->and(deleteTestAccounts()->canBeDeleted($account->fresh()))->toBeTrue();

    $this->delete(route('entity.accounts.destroy', [$entity, $account]))
        ->assertRedirect(route('entity.accounts.index', $entity))
        ->assertSessionHas('success', 'Rekening berhasil dihapus.');

    $this->assertDatabaseMissing('finance_accounts', ['id' => $account->id]);
});

it('rejects delete of an inactive account that has history', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 10_000,
    ]);
    deleteTestAccounts()->deactivate($account);
    deleteTestGrantAccess($entity);

    expect($account->fresh()->is_active)->toBeFalse();
    assertAccountDeleteRejected($entity, $account->fresh(), FinanceAccountService::DELETE_BLOCKED_HISTORY);
});

it('returns 404 when deleting an account owned by another entity', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    deleteTestCash($entityA, 'Kas A');
    $foreign = deleteTestCash($entityB, 'Kas B');
    deleteTestGrantAccess($entityA);

    $this->delete(route('entity.accounts.destroy', [$entityA, $foreign]))->assertNotFound();
    $this->assertDatabaseHas('finance_accounts', ['id' => $foreign->id]);
});

it('deletes through DELETE plus CSRF and rejects GET', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);
    deleteTestGrantAccess($entity);

    $this->get(route('entity.accounts.index', $entity))
        ->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee('Hapus');

    $this->get('/e/'.$entity->public_id.'/accounts/'.$account->public_id)
        ->assertStatus(405);
    $this->assertDatabaseHas('finance_accounts', ['id' => $account->id]);

    $this->delete(route('entity.accounts.destroy', [$entity, $account]))
        ->assertRedirect(route('entity.accounts.index', $entity))
        ->assertSessionHas('success', 'Rekening berhasil dihapus.');
});

it('does not cascade-delete transactions when account delete is rejected', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);
    $transaction = Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 75_000,
        'description' => 'Harus tetap ada',
    ]);
    deleteTestGrantAccess($entity);

    assertAccountDeleteRejected($entity, $account, FinanceAccountService::DELETE_BLOCKED_HISTORY);

    expect(Transaction::query()->find($transaction->id))->not->toBeNull()
        ->and((int) $transaction->fresh()->finance_account_id)->toBe((int) $account->id);
});

it('rejects delete when the account is a transfer source', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = deleteTestCash($entity, 'Kas Sumber');
    $destination = deleteTestSibling($entity, 'Kas Tujuan');
    deleteTestAccounts()->setDefault($destination);
    FinanceTransfer::factory()->create([
        'finance_entity_id' => $entity->id,
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 15_000,
    ]);
    deleteTestGrantAccess($entity);

    assertAccountDeleteRejected($entity, $source->fresh(), FinanceAccountService::DELETE_BLOCKED_HISTORY);
});

it('rejects delete when the account is a transfer destination', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = deleteTestCash($entity, 'Kas Sumber');
    $destination = deleteTestSibling($entity, 'Kas Tujuan');
    FinanceTransfer::factory()->create([
        'finance_entity_id' => $entity->id,
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 15_000,
    ]);
    deleteTestGrantAccess($entity);

    assertAccountDeleteRejected($entity, $destination, FinanceAccountService::DELETE_BLOCKED_HISTORY);
});

it('rejects delete when the account received a receivable payment', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $account = deleteTestSibling($entity);
    $receivable = Receivable::factory()->create(['finance_entity_id' => $entity->id]);
    ReceivablePayment::factory()->create([
        'receivable_id' => $receivable->id,
        'finance_account_id' => $account->id,
        'amount' => 4_000,
    ]);
    deleteTestGrantAccess($entity);

    assertAccountDeleteRejected($entity, $account, FinanceAccountService::DELETE_BLOCKED_HISTORY);
});

it('rejects delete when a Plantation synced transaction uses the account', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = deleteTestCash($entity, 'Kas Usaha');
    $replacement = deleteTestSibling($entity, 'BCA Usaha');
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => '01PLANTATIONACCOUNTDELETE001',
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    $source = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', [
        'event_id' => (string) Str::ulid(),
        'event_type' => IntegrationEventType::PLANTATION_PURCHASE_POSTED->value,
        'event_version' => 1,
        'occurred_at' => now()->toIso8601String(),
        'plantation_entity_public_id' => '01PLANTATIONACCOUNTDELETE001',
        'finance_entity_public_id' => $entity->public_id,
        'source_public_id' => $source,
        'payload' => [
            'purchase_public_id' => $source,
            'purchase_date' => now()->toDateString(),
            'amount' => '150000.00',
            'description' => 'Pupuk',
            'supplier' => ['public_id' => '01SUP', 'name' => 'CV Tani'],
        ],
    ], [
        'Authorization' => 'Bearer testing-plantation-service-token',
        'Accept' => 'application/json',
    ])->assertOk();

    deleteTestAccounts()->setDefault($replacement);
    deleteTestGrantAccess($entity);

    expect(Transaction::query()->where('finance_account_id', $account->id)->count())->toBe(1);
    assertAccountDeleteRejected($entity, $account->fresh(), FinanceAccountService::DELETE_BLOCKED_HISTORY);
    expect(Transaction::query()->where('finance_account_id', $account->id)->count())->toBe(1);
});

it('keeps Plantation external financial references intact when delete is rejected', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = deleteTestCash($entity, 'Kas Usaha');
    $replacement = deleteTestSibling($entity, 'BCA Usaha');
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => '01PLANTATIONACCOUNTDELETE002',
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    $source = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', [
        'event_id' => (string) Str::ulid(),
        'event_type' => IntegrationEventType::PLANTATION_PURCHASE_POSTED->value,
        'event_version' => 1,
        'occurred_at' => now()->toIso8601String(),
        'plantation_entity_public_id' => '01PLANTATIONACCOUNTDELETE002',
        'finance_entity_public_id' => $entity->public_id,
        'source_public_id' => $source,
        'payload' => [
            'purchase_public_id' => $source,
            'purchase_date' => now()->toDateString(),
            'amount' => '250000.00',
            'supplier' => ['public_id' => null, 'name' => 'CV Tani'],
        ],
    ], [
        'Authorization' => 'Bearer testing-plantation-service-token',
        'Accept' => 'application/json',
    ])->assertOk();

    $transaction = Transaction::query()->where('finance_account_id', $account->id)->first();
    $reference = ExternalFinancialReference::query()
        ->where('source_public_id', $source)
        ->first();

    deleteTestAccounts()->setDefault($replacement);
    deleteTestGrantAccess($entity);
    assertAccountDeleteRejected($entity, $account->fresh(), FinanceAccountService::DELETE_BLOCKED_HISTORY);

    expect($transaction->fresh())->not->toBeNull()
        ->and($reference->fresh())->not->toBeNull()
        ->and((int) $reference->fresh()->record_id)->toBe((int) $transaction->id)
        ->and((int) $transaction->fresh()->finance_account_id)->toBe((int) $account->id);
});

it('writes an AuditLog only after a successful account delete', function () {
    $entity = FinanceEntity::factory()->family()->create();
    deleteTestCash($entity, 'Kas Utama Keluarga');
    $blocked = deleteTestSibling($entity, 'BCA Histori');
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $blocked->id,
        'amount' => 5_000,
    ]);
    $deletable = deleteTestSibling($entity, 'GoPay Kosong');
    deleteTestGrantAccess($entity);

    $this->delete(route('entity.accounts.destroy', [$entity, $blocked]))
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('auditable_type', (new FinanceAccount)->getMorphClass())
        ->where('action', AuditAction::DELETE)
        ->count())->toBe(0);

    $this->delete(route('entity.accounts.destroy', [$entity, $deletable]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Rekening berhasil dihapus.');

    $log = AuditLog::query()
        ->where('auditable_type', (new FinanceAccount)->getMorphClass())
        ->where('action', AuditAction::DELETE)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_id)->toBe($deletable->id)
        ->and($log->finance_entity_id)->toBe($entity->id)
        ->and($log->old_values['name'])->toBe('GoPay Kosong')
        ->and($log->old_values['type'])->toBe(FinanceAccountType::BANK->value)
        ->and($log->old_values['bank_name'])->toBe('Mandiri')
        ->and($log->old_values['public_id'])->toBe($deletable->public_id)
        ->and($log->old_values['account_number'])->not->toBe('1234567890')
        ->and($log->new_values)->toBeNull();
});

it('disables Hapus on the index when the account cannot be deleted', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $default = deleteTestCash($entity, 'Mandiri');
    $used = deleteTestSibling($entity, 'Kas Histori');
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $used->id,
        'amount' => 2_876_982,
    ]);
    $unused = deleteTestSibling($entity, 'Kas Kosong');
    deleteTestGrantAccess($entity);

    $this->get(route('entity.accounts.index', $entity))
        ->assertOk()
        ->assertSee('Tidak dapat dihapus karena memiliki histori transaksi.', false)
        ->assertSee(FinanceAccountService::DELETE_BLOCKED_DEFAULT, false)
        ->assertSee('name="_method"', false)
        ->assertSee($unused->name);
});

it('rejects deleting the sole remaining account to protect the entity invariant', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = deleteTestCash($entity, 'Kas Utama Keluarga');
    deleteTestAccounts()->deactivate($account);
    deleteTestGrantAccess($entity);

    expect($entity->accounts()->count())->toBe(1)
        ->and($account->fresh()->is_default)->toBeFalse()
        ->and(deleteTestAccounts()->canBeDeleted($account->fresh()))->toBeFalse();

    assertAccountDeleteRejected($entity, $account->fresh(), FinanceAccountService::DELETE_BLOCKED_SOLE);
});
