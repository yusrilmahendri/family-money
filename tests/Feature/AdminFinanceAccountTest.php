<?php

use App\Enums\FinanceAccountType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\User;
use App\Services\FinanceAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function actingAccountAdmin()
{
    return test()->actingAs(User::factory()->admin()->create());
}

it('allows an admin to list create edit activate deactivate and set default accounts', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Admin']);
    $service = app(FinanceAccountService::class);
    $first = $service->ensureDefaultAccount($entity);

    actingAccountAdmin()
        ->get(route('admin.finance-entities.accounts.index', $entity))
        ->assertOk()
        ->assertSee('Kas Utama Keluarga')
        ->assertSee($first->name);

    actingAccountAdmin()
        ->post(route('admin.finance-entities.accounts.store', $entity), [
            'name' => 'BCA',
            'type' => FinanceAccountType::BANK->value,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'opening_balance' => '0',
        ])
        ->assertRedirect(route('admin.finance-entities.accounts.index', $entity));

    $second = FinanceAccount::query()->where('name', 'BCA')->where('finance_entity_id', $entity->id)->first();

    expect($second)->not->toBeNull();

    actingAccountAdmin()
        ->get(route('admin.finance-entities.accounts.index', $entity))
        ->assertOk()
        ->assertSee('BCA')
        ->assertSee('******7890')
        ->assertDontSee('1234567890');

    actingAccountAdmin()
        ->put(route('admin.finance-entities.accounts.update', [$entity, $second]), [
            'name' => 'BCA Utama',
            'type' => FinanceAccountType::BANK->value,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
        ])
        ->assertRedirect(route('admin.finance-entities.accounts.index', $entity));

    expect($second->fresh()->name)->toBe('BCA Utama');

    actingAccountAdmin()
        ->post(route('admin.finance-entities.accounts.set-default', [$entity, $second]))
        ->assertRedirect();

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse()
        ->and($entity->accounts()->where('is_default', true)->count())->toBe(1);

    actingAccountAdmin()
        ->post(route('admin.finance-entities.accounts.deactivate', [$entity, $second]))
        ->assertRedirect();

    expect($second->fresh()->is_active)->toBeFalse()
        ->and($first->fresh()->is_default)->toBeTrue();

    actingAccountAdmin()
        ->post(route('admin.finance-entities.accounts.activate', [$entity, $second]))
        ->assertRedirect();

    expect($second->fresh()->is_active)->toBeTrue();
});

it('rejects a forged finance_entity_id on admin account create', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);

    actingAccountAdmin()
        ->post(route('admin.finance-entities.accounts.store', $entityA), [
            'name' => 'GoPay',
            'type' => FinanceAccountType::EWALLET->value,
            'finance_entity_id' => $entityB->id,
            'public_id' => '01FORGEDPUBLICIDACCOUNT00',
        ])
        ->assertSessionHasErrors(['finance_entity_id', 'public_id']);
});

it('returns 404 when admin opens an account from another entity url', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    $foreign = app(FinanceAccountService::class)->create($entityB, [
        'name' => 'BRI Kebun',
        'type' => FinanceAccountType::BANK,
    ]);

    actingAccountAdmin()
        ->get(route('admin.finance-entities.accounts.edit', [$entityA, $foreign]))
        ->assertNotFound();

    actingAccountAdmin()
        ->put(route('admin.finance-entities.accounts.update', [$entityA, $foreign]), [
            'name' => 'Hacked',
            'type' => FinanceAccountType::CASH->value,
        ])
        ->assertNotFound();
});

it('does not expose a destructive delete route for finance accounts', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = app(FinanceAccountService::class)->ensureDefaultAccount($entity);

    expect(Route::has('admin.finance-entities.accounts.destroy'))->toBeFalse()
        ->and(Route::has('entity.accounts.destroy'))->toBeFalse();

    $response = actingAccountAdmin()->delete(
        '/admin/finance-entities/'.$entity->public_id.'/accounts/'.$account->public_id
    );

    expect($response->status())->toBeIn([404, 405]);
    $this->assertDatabaseHas('finance_accounts', ['id' => $account->id]);
});
