<?php

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\FinanceAccountType;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\ReceivablePayment;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BusinessCapitalContributionService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\FinanceEntityDeletionService;
use App\Services\OwnerWithdrawalService;
use App\Services\ProfitDistributionService;
use App\Services\ReceivableService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function purgeActingAdmin()
{
    return test()->actingAs(User::factory()->admin()->create());
}

function purgeCash(FinanceEntity $entity, string $name, float $opening = 0)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

function purgeGrant(FinanceEntity $entity): string
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);

    test()->get(route('access.show', $plain))->assertRedirect();

    return $plain;
}

it('allows an admin to permanently delete an empty finance entity', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Kosong']);

    purgeActingAdmin()
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'HAPUS',
        ])
        ->assertRedirect(route('admin.finance-entities.index'))
        ->assertSessionHas('success', 'Finance Entity dan seluruh data terkait berhasil dihapus permanen.');

    $this->assertDatabaseMissing('finance_entities', ['id' => $entity->id]);
});

it('allows an admin to delete an entity with financial data and related accounts', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Data Hapus']);
    $account = purgeCash($entity, 'Kas Data Hapus', 2_000_000);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::PRIBADI,
    ]);
    $transaction = Transaction::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 150_000,
        'transaction_date' => now(),
        'description' => 'Belanja hapus',
    ]);
    $debt = Debt::query()->create([
        'finance_entity_id' => $entity->id,
        'title' => 'Hutang Hapus',
        'principal_total' => 400_000,
        'remaining_balance' => 300_000,
    ]);
    DebtPayment::query()->create([
        'debt_id' => $debt->id,
        'finance_account_id' => $account->id,
        'amount' => 100_000,
        'paid_on' => now(),
    ]);
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $entity->id,
        'title' => 'Tabungan Hapus',
        'target_amount' => 1_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $account->id,
        'amount' => 50_000,
        'contributed_on' => now(),
    ]);
    $receivable = app(ReceivableService::class)->create($entity, [
        'party_name' => 'Piutang Hapus',
        'principal_amount' => 80_000,
        'receivable_date' => now()->toDateString(),
    ]);
    ReceivablePayment::query()->create([
        'receivable_id' => $receivable->id,
        'finance_account_id' => $account->id,
        'amount' => 20_000,
        'payment_date' => now(),
    ]);
    RecurringTransaction::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'name' => 'Recurring Hapus',
        'amount' => 25_000,
        'frequency' => 'monthly',
        'start_date' => now(),
        'next_due' => now(),
        'active' => true,
    ]);

    purgeActingAdmin()
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'Keluarga Data Hapus',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $this->assertDatabaseMissing('finance_entities', ['id' => $entity->id]);
    $this->assertDatabaseMissing('finance_accounts', ['id' => $account->id]);
    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertDatabaseMissing('debts', ['id' => $debt->id]);
    $this->assertDatabaseMissing('debt_payments', ['debt_id' => $debt->id]);
    $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
    $this->assertDatabaseMissing('goal_contributions', ['savings_goal_id' => $goal->id]);
    $this->assertDatabaseMissing('receivables', ['id' => $receivable->id]);
    $this->assertDatabaseMissing('receivable_payments', ['receivable_id' => $receivable->id]);
    $this->assertDatabaseMissing('recurring_transactions', ['finance_entity_id' => $entity->id]);
});

it('deletes BUSINESS income budget and operational data with the entity', function () {
    $entity = FinanceEntity::factory()->business()->create(['name' => 'Usaha Hapus']);
    $account = purgeCash($entity, 'Kas Usaha Hapus', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $income = Income::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen hapus',
        'amount' => 500_000,
        'income_date' => now(),
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => $category->id,
        'amount' => 200_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    $activity = BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $account->id,
        'name' => 'Pupuk hapus',
        'amount' => 40_000,
        'activity_date' => now(),
    ]);

    purgeActingAdmin()
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'HAPUS',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    $this->assertDatabaseMissing('budget_activities', ['id' => $activity->id]);
    $this->assertDatabaseMissing('finance_accounts', ['id' => $account->id]);
});

it('invalidates private access tokens after the entity is deleted', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Token Hapus']);
    $plain = purgeGrant($entity);
    $tokenId = FinanceEntityAccessToken::query()->firstOrFail()->id;

    $this->get(route('entity.dashboard', $entity))->assertOk();

    purgeActingAdmin()
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'HAPUS',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('finance_entity_access_tokens', ['id' => $tokenId]);
    $this->get(route('access.show', $plain))->assertNotFound();
    $this->get('/e/'.$entity->public_id.'/dashboard')->assertNotFound();
});

it('removes cross-entity capital prive and profit distribution without leaving orphans', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Cross']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Cross']);
    $other = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tetap']);
    $familyAccount = purgeCash($family, 'Kas Cross Family', 5_000_000);
    $businessAccount = purgeCash($business, 'Kas Cross Usaha', 0);
    $otherAccount = purgeCash($other, 'Kas Tetap', 100_000);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $businessAccount->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen cross',
        'amount' => 800_000,
        'income_date' => now(),
    ]);

    $capital = app(BusinessCapitalContributionService::class)->create($family, $business, [
        'source_account_id' => $familyAccount->id,
        'destination_account_id' => $businessAccount->id,
        'amount' => 1_000_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Modal cross',
    ]);
    $withdrawal = app(OwnerWithdrawalService::class)->create($business, $family, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 50_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Prive cross',
    ]);
    $distribution = app(ProfitDistributionService::class)->create($business, $family, [
        'source_account_id' => $businessAccount->id,
        'destination_account_id' => $familyAccount->id,
        'amount' => 100_000,
        'distribution_date' => now()->toDateString(),
        'description' => 'Bagi laba cross',
    ]);

    purgeActingAdmin()
        ->delete(route('admin.finance-entities.destroy', $family), [
            'confirmation' => 'HAPUS',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('finance_entities', ['id' => $family->id]);
    $this->assertDatabaseMissing('business_capital_contributions', ['id' => $capital->id]);
    $this->assertDatabaseMissing('owner_withdrawals', ['id' => $withdrawal->id]);
    $this->assertDatabaseMissing('profit_distributions', ['id' => $distribution->id]);
    $this->assertDatabaseHas('finance_entities', ['id' => $business->id]);
    $this->assertDatabaseHas('finance_entities', ['id' => $other->id]);
    $this->assertDatabaseHas('finance_accounts', ['id' => $businessAccount->id]);
    $this->assertDatabaseHas('finance_accounts', ['id' => $otherAccount->id]);
    $this->assertDatabaseHas('incomes', ['source' => 'Panen cross']);
    expect(BusinessCapitalContribution::query()->count())->toBe(0)
        ->and(OwnerWithdrawal::query()->count())->toBe(0)
        ->and(ProfitDistribution::query()->count())->toBe(0);
});

it('rejects a wrong confirmation with 422 and keeps the entity', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Konfirmasi']);

    purgeActingAdmin()
        ->deleteJson(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'SALAH',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('confirmation');

    $this->assertDatabaseHas('finance_entities', ['id' => $entity->id]);
});

it('rejects a guest from the delete endpoint', function () {
    $entity = FinanceEntity::factory()->create();

    $this->delete(route('admin.finance-entities.destroy', $entity), [
        'confirmation' => 'HAPUS',
    ])->assertRedirect(route('admin.login'));

    $this->assertDatabaseHas('finance_entities', ['id' => $entity->id]);
});

it('rejects a non-admin from the delete endpoint', function () {
    $entity = FinanceEntity::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'HAPUS',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('finance_entities', ['id' => $entity->id]);
});

it('rejects a private-link session from the admin delete endpoint', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Private']);
    purgeGrant($entity);

    $this->delete(route('admin.finance-entities.destroy', $entity), [
        'confirmation' => 'HAPUS',
    ])->assertRedirect(route('admin.login'));

    $this->assertDatabaseHas('finance_entities', ['id' => $entity->id]);
});

it('rolls back deletion when a later step fails', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Rollback']);
    $account = purgeCash($entity, 'Kas Rollback', 75_000);

    FinanceEntity::deleting(function (FinanceEntity $deleting) use ($entity): void {
        if ((int) $deleting->id === (int) $entity->id) {
            throw new RuntimeException('force rollback');
        }
    });

    expect(fn () => app(FinanceEntityDeletionService::class)->delete($entity))
        ->toThrow(RuntimeException::class);

    expect($entity->fresh())->not->toBeNull()
        ->and($account->fresh())->not->toBeNull();
    $this->assertDatabaseHas('finance_accounts', ['id' => $account->id]);
});

it('records a safe FINANCE_ENTITY_DELETED audit without an orphan entity FK', function () {
    $admin = User::factory()->admin()->create();
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Audit Hapus']);
    purgeCash($entity, 'Kas Audit Hapus', 10_000);
    $publicId = $entity->public_id;

    $this->actingAs($admin)
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'HAPUS',
        ])
        ->assertRedirect();

    $log = AuditLog::query()
        ->where('action', AuditAction::FINANCE_ENTITY_DELETED)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(AuditActorType::ADMIN)
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->finance_entity_id)->toBeNull()
        ->and($log->old_values['public_id'])->toBe($publicId)
        ->and($log->old_values['name'])->toBe('Keluarga Audit Hapus')
        ->and($log->old_values['accounts_count'])->toBe(1)
        ->and($log->old_values)->not->toHaveKey('token')
        ->and($log->old_values)->not->toHaveKey('token_hash')
        ->and($log->new_values)->toBeNull();

    expect(app(AuditLogService::class)->hasIntegrityIssues())->toBeFalse();
});

it('does not recreate a deleted default-slug entity automatically', function () {
    $entity = FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_PRIBADI)->first()
        ?? FinanceEntity::factory()->family()->create([
            'name' => 'Keuangan Keluarga',
            'slug' => FinanceEntity::DEFAULT_SLUG_PRIBADI,
        ]);

    purgeActingAdmin()
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'HAPUS',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('finance_entities', [
        'slug' => FinanceEntity::DEFAULT_SLUG_PRIBADI,
    ]);
});
