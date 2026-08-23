<?php

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\FinanceAccountType;
use App\Enums\FinanceEntityType;
use App\Models\AuditLog;
use App\Models\BusinessCapitalContribution;
use App\Models\Category;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\FinanceTransfer;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FinanceAccountService;
use App\Services\FinanceTransferService;
use App\Services\RecurringTransactionRunner;
use App\Support\FinanceEntityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function latestAuditLog(?string $type = null, ?AuditAction $action = null): ?AuditLog
{
    $query = AuditLog::query()->latest('id');

    if ($type !== null) {
        $query->where('auditable_type', $type);
    }

    if ($action instanceof AuditAction) {
        $query->where('action', $action);
    }

    return $query->first();
}

it('records an admin create of a finance entity', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Keluarga Audit',
            'type' => FinanceEntityType::FAMILY->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $entity = FinanceEntity::query()->where('name', 'Keluarga Audit')->first();
    $log = latestAuditLog(FinanceEntity::class, AuditAction::CREATE);

    expect($entity)->not->toBeNull()
        ->and($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(AuditActorType::ADMIN)
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->finance_entity_id)->toBe($entity->id)
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values['name'])->toBe('Keluarga Audit')
        ->and($log->new_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('token_hash');
});

it('records a private-link transaction create', function () {
    $entity = FinanceEntity::factory()->family()->create();
    grantEntityAccess($entity);
    $tokenId = FinanceEntityAccess::tokenIdFor($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja audit',
    ])->assertRedirect(route('entity.transactions.index', $entity));

    $transaction = Transaction::query()->where('description', 'Belanja audit')->first();
    $log = latestAuditLog(Transaction::class, AuditAction::CREATE);

    expect($transaction)->not->toBeNull()
        ->and($log)->not->toBeNull()
        ->and($log->auditable_id)->toBe($transaction->id)
        ->and($log->actor_type)->toBe(AuditActorType::PRIVATE_LINK)
        ->and($log->actor_id)->toBe($tokenId)
        ->and($log->finance_entity_id)->toBe($entity->id)
        ->and((float) $log->new_values['amount'])->toBe(25000.0);
});

it('stores before and after values when a private user updates a transaction', function () {
    $entity = FinanceEntity::factory()->family()->create();
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Sebelum',
    ])->assertRedirect();

    $transaction = Transaction::query()->where('description', 'Sebelum')->first();

    $this->put(route('entity.transactions.update', [$entity, $transaction]), [
        'amount' => '50000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Sesudah',
    ])->assertRedirect(route('entity.transactions.index', $entity));

    $log = latestAuditLog(Transaction::class, AuditAction::UPDATE);

    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(AuditActorType::PRIVATE_LINK)
        ->and((float) $log->old_values['amount'])->toBe(25000.0)
        ->and((float) $log->new_values['amount'])->toBe(50000.0)
        ->and($log->old_values['description'])->toBe('Sebelum')
        ->and($log->new_values['description'])->toBe('Sesudah');
});

it('keeps a delete snapshot after the transaction is removed', function () {
    $entity = FinanceEntity::factory()->family()->create();
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '12000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Akan dihapus',
    ])->assertRedirect();

    $transaction = Transaction::query()->where('description', 'Akan dihapus')->first();
    $id = $transaction->id;

    $this->delete(route('entity.transactions.destroy', [$entity, $transaction]))
        ->assertRedirect(route('entity.transactions.index', $entity));

    expect(Transaction::query()->find($id))->toBeNull();

    $log = latestAuditLog(Transaction::class, AuditAction::DELETE);

    expect($log)->not->toBeNull()
        ->and($log->auditable_id)->toBe($id)
        ->and($log->old_values['description'])->toBe('Akan dihapus')
        ->and((float) $log->old_values['amount'])->toBe(12000.0);
});

it('marks recurring postings as SYSTEM', function () {
    $entity = FinanceEntity::factory()->family()->create();
    RecurringTransaction::query()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'BPJS audit',
        'amount' => 150000,
        'frequency' => 'monthly',
        'start_date' => now()->subMonth(),
        'next_due' => now()->toDateString(),
        'active' => true,
    ]);

    expect(app(RecurringTransactionRunner::class)->runDue())->toBeGreaterThan(0);

    $transaction = Transaction::query()->where('description', '[Otomatis] BPJS audit')->first();
    $log = AuditLog::query()
        ->where('auditable_type', Transaction::class)
        ->where('auditable_id', $transaction->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(AuditActorType::SYSTEM)
        ->and($log->actor_id)->toBeNull()
        ->and($log->action)->toBe(AuditAction::CREATE);
});

it('records a transfer', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'Kas Sumber Audit', 1_000_000);
    $destination = cashAccount($entity, 'Kas Tujuan Audit', 0);
    grantEntityAccess($entity);

    $this->post(route('entity.transfers.store', $entity), [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => '300000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Geser audit',
    ])->assertRedirect(route('entity.transfers.index', $entity));

    $transfer = FinanceTransfer::query()->first();
    $log = latestAuditLog(FinanceTransfer::class, AuditAction::TRANSFER);

    expect($transfer)->not->toBeNull()
        ->and($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(AuditActorType::PRIVATE_LINK)
        ->and((float) $log->new_values['amount'])->toBe(300000.0)
        ->and($log->finance_entity_id)->toBe($entity->id);
});

it('records capital with the family as the primary entity', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Modal Audit']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Modal Audit']);
    $source = cashAccount($family, 'BCA Modal Audit', 5_000_000);
    $destination = cashAccount($business, 'Kas Modal Audit', 0);
    grantEntityAccess($family);
    grantEntityAccess($business);

    $this->post(route('entity.capital-contributions.store', $family), [
        'source_account_id' => $source->id,
        'business_public_id' => $business->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '2000000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Setor modal audit',
    ])->assertRedirect(route('entity.capital-contributions.index', $family));

    $log = latestAuditLog(BusinessCapitalContribution::class, AuditAction::CREATE);

    expect($log)->not->toBeNull()
        ->and($log->finance_entity_id)->toBe($family->id)
        ->and($log->new_values['counterpart_entity_id'])->toBe($business->id)
        ->and($log->new_values['counterpart_entity_public_id'])->toBe($business->public_id)
        ->and((float) $log->new_values['amount'])->toBe(2_000_000.0);
});

it('records prive with the business as the primary entity', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Prive Audit', 8_000_000);
    $destination = cashAccount($family, 'BCA Prive Audit', 0);
    grantEntityAccess($business);
    grantEntityAccess($family);

    $this->post(route('entity.owner-withdrawals.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '1000000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Prive audit',
    ])->assertRedirect(route('entity.owner-withdrawals.index', $business));

    $log = latestAuditLog(OwnerWithdrawal::class, AuditAction::CREATE);

    expect(OwnerWithdrawal::query()->count())->toBe(1)
        ->and($log)->not->toBeNull()
        ->and($log->finance_entity_id)->toBe($business->id)
        ->and($log->new_values['counterpart_entity_id'])->toBe($family->id)
        ->and((float) $log->new_values['amount'])->toBe(1_000_000.0);
});

it('records a profit distribution', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    $source = cashAccount($business, 'Kas Laba Audit', 0);
    $destination = cashAccount($family, 'BCA Laba Audit', 0);
    businessIncome($business, 10_000_000, now(), $source);
    businessExpense($business, 4_000_000, now(), $source);
    grantEntityAccess($business);
    grantEntityAccess($family);

    [$from, $to] = profitService()->currentMonthBounds();

    $this->post(route('entity.profit-distributions.store', $business), [
        'source_account_id' => $source->id,
        'family_public_id' => $family->public_id,
        'destination_account_id' => $destination->id,
        'amount' => '1000000',
        'distribution_date' => now()->toDateString(),
        'period_start' => $from,
        'period_end' => $to,
        'description' => 'Bagi laba audit',
    ])->assertRedirect(route('entity.profit-distributions.index', $business));

    $log = latestAuditLog(ProfitDistribution::class, AuditAction::CREATE);

    expect(ProfitDistribution::query()->count())->toBe(1)
        ->and($log)->not->toBeNull()
        ->and($log->finance_entity_id)->toBe($business->id)
        ->and($log->new_values['counterpart_entity_id'])->toBe($family->id)
        ->and((float) $log->new_values['amount'])->toBe(1_000_000.0);
});

it('records a receivable payment', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = cashAccount($entity, 'Kas Piutang Audit', 500_000);
    grantEntityAccess($entity);

    $this->post(route('entity.receivables.store', $entity), [
        'party_name' => 'Pak Audit',
        'principal_amount' => '4000000',
        'receivable_date' => now()->toDateString(),
    ])->assertRedirect();

    $receivable = Receivable::query()->first();

    $this->post(route('entity.receivables.payments.store', [$entity, $receivable]), [
        'finance_account_id' => $account->id,
        'amount' => '1500000',
        'payment_date' => now()->toDateString(),
    ])->assertRedirect();

    $payment = ReceivablePayment::query()->first();
    $log = latestAuditLog(ReceivablePayment::class, AuditAction::PAYMENT);

    expect($payment)->not->toBeNull()
        ->and($log)->not->toBeNull()
        ->and($log->auditable_id)->toBe($payment->id)
        ->and($log->actor_type)->toBe(AuditActorType::PRIVATE_LINK)
        ->and((float) $log->new_values['amount'])->toBe(1_500_000.0);
});

it('audits revoke and regenerate without storing plaintext or token hash', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $admin = User::factory()->admin()->create();

    $created = $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.store', $entity), [
            'label' => 'Link audit',
        ]);

    $plain = $created->viewData('plainToken');
    $token = FinanceEntityAccessToken::query()->first();
    $hash = $token->getRawOriginal('token_hash') ?? $token->token_hash;

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.revoke', [$entity, $token]))
        ->assertRedirect();

    $revoke = latestAuditLog(FinanceEntityAccessToken::class, AuditAction::REVOKE);
    $revokePayload = json_encode([$revoke->old_values, $revoke->new_values]);

    expect($revoke)->not->toBeNull()
        ->and($revoke->actor_type)->toBe(AuditActorType::ADMIN)
        ->and($revokePayload)->not->toContain($plain)
        ->and($revokePayload)->not->toContain($hash)
        ->and($revoke->new_values)->not->toHaveKey('token_hash')
        ->and($revoke->new_values)->not->toHaveKey('token');

    $token->refresh();

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.regenerate', [$entity, $token]))
        ->assertOk();

    $replacement = FinanceEntityAccessToken::query()->latest('id')->first();
    $regenerated = latestAuditLog(FinanceEntityAccessToken::class, AuditAction::REGENERATE);
    $regenPayload = json_encode([$regenerated->old_values, $regenerated->new_values]);
    $newHash = $replacement->getRawOriginal('token_hash') ?? $replacement->token_hash;

    expect($regenerated)->not->toBeNull()
        ->and($regenPayload)->not->toContain($plain)
        ->and($regenPayload)->not->toContain($hash)
        ->and($regenPayload)->not->toContain($newHash)
        ->and($regenerated->new_values)->not->toHaveKey('token_hash');
});

it('never persists password or token fields even if they are supplied', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $transaction = Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entity->id])->id,
        'description' => 'Sanitasi',
    ]);

    app(AuditLogService::class)->recordCreated($transaction, $entity, extra: [
        'password' => 'secret-password',
        'token' => 'plain-token-value',
        'token_hash' => 'deadbeef',
        'remember_token' => 'remember-me',
        '_token' => 'csrf-token',
        'amount' => 99,
    ]);

    $log = latestAuditLog(Transaction::class, AuditAction::CREATE);

    expect($log->new_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('token')
        ->and($log->new_values)->not->toHaveKey('token_hash')
        ->and($log->new_values)->not->toHaveKey('remember_token')
        ->and($log->new_values)->not->toHaveKey('_token')
        ->and(json_encode($log->new_values))->not->toContain('secret-password')
        ->and(json_encode($log->new_values))->not->toContain('plain-token-value')
        ->and(json_encode($log->new_values))->not->toContain('deadbeef');
});

it('does not keep a success audit when a financial transaction rolls back', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'Kas Rollback A', 1_000_000);
    $destination = cashAccount($entity, 'Kas Rollback B', 0);
    $transfersBefore = FinanceTransfer::query()->count();
    $auditsBefore = AuditLog::query()->where('action', AuditAction::TRANSFER)->count();

    try {
        DB::transaction(function () use ($entity, $source, $destination): void {
            app(FinanceTransferService::class)->create($entity, [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => 250_000,
                'transaction_date' => now()->toDateString(),
                'description' => 'Harus rollback',
            ]);

            throw new RuntimeException('force-rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force-rollback');
    }

    expect(FinanceTransfer::query()->count())->toBe($transfersBefore)
        ->and(AuditLog::query()->where('action', AuditAction::TRANSFER)->count())->toBe($auditsBefore);
});

it('does not write a transfer audit when the financial operation is rejected', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'Kas Tolak A', 10_000);
    $destination = cashAccount($entity, 'Kas Tolak B', 0);
    $before = AuditLog::query()->where('action', AuditAction::TRANSFER)->count();

    grantEntityAccess($entity);
    $this->from(route('entity.transfers.create', $entity))
        ->post(route('entity.transfers.store', $entity), [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'amount' => '999999',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('amount');

    expect(FinanceTransfer::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::TRANSFER)->count())->toBe($before);
});

it('lets an admin view audit logs and hides them from non-admins', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Terlihat']);
    actingAdmin()
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Keluarga Panel',
            'type' => FinanceEntityType::FAMILY->value,
            'is_active' => '1',
        ])
        ->assertRedirect();

    actingAdmin()
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee('Audit Logs')
        ->assertSee('Keluarga Panel')
        ->assertSee('CREATE');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();

    auth()->logout();

    $this->get(route('admin.audit-logs.index'))
        ->assertRedirect(route('admin.login'));

    grantEntityAccess($entity);
    $this->get(route('admin.audit-logs.index'))
        ->assertRedirect(route('admin.login'));
    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertDontSee('Audit Logs');
});

it('does not expose edit or delete routes for audit logs', function () {
    expect(Route::has('admin.audit-logs.edit'))->toBeFalse()
        ->and(Route::has('admin.audit-logs.update'))->toBeFalse()
        ->and(Route::has('admin.audit-logs.destroy'))->toBeFalse()
        ->and(Route::has('entity.audit-logs.index'))->toBeFalse();
});

it('rejects updates and deletes on the audit log model', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $transaction = Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entity->id])->id,
    ]);
    app(AuditLogService::class)->recordCreated($transaction, $entity);

    $log = AuditLog::query()->first();

    expect(fn () => $log->update(['action' => AuditAction::DELETE]))
        ->toThrow(LogicException::class)
        ->and(fn () => $log->delete())
        ->toThrow(LogicException::class);

    expect(AuditLog::query()->count())->toBe(1);
});

it('masks account numbers in account snapshots', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = app(FinanceAccountService::class)->create($entity, [
        'name' => 'BCA Masked',
        'type' => FinanceAccountType::BANK,
        'account_number' => '1234567890',
        'opening_balance' => 0,
    ]);

    $log = AuditLog::query()
        ->where('auditable_type', FinanceAccount::class)
        ->where('auditable_id', $account->id)
        ->where('action', AuditAction::CREATE)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['account_number'])->toBe('******7890')
        ->and(json_encode($log->new_values))->not->toContain('1234567890');
});

it('passes the read-only audit log check when rows are clean', function () {
    $this->artisan('finance:audit-log-check')
        ->expectsOutputToContain('Audit Log Check')
        ->expectsOutputToContain('Audit logs look consistent.')
        ->assertSuccessful();
});

it('fails the audit log check for invalid actor, action, secrets, json, or entity', function () {
    DB::table('audit_logs')->insert([
        'finance_entity_id' => 999999,
        'actor_type' => 'HACKER',
        'actor_id' => null,
        'action' => '',
        'auditable_type' => Transaction::class,
        'auditable_id' => 1,
        'old_values' => json_encode(['password' => 'leaked']),
        'new_values' => '{not-json',
        'created_at' => now(),
    ]);

    $this->artisan('finance:audit-log-check')
        ->expectsOutputToContain('Invalid actor type')
        ->expectsOutputToContain('Sensitive fields')
        ->expectsOutputToContain('Malformed JSON')
        ->expectsOutputToContain('Invalid entity reference')
        ->assertFailed();
});
