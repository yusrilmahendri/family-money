<?php

use App\Enums\FinanceAccountType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantAccountAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

it('lists only the route entity accounts and masks the account number', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);

    app(FinanceAccountService::class)->create($entityA, [
        'name' => 'BCA Keluarga A',
        'type' => FinanceAccountType::BANK,
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
    ]);
    app(FinanceAccountService::class)->create($entityB, [
        'name' => 'BCA Keluarga B',
        'type' => FinanceAccountType::BANK,
        'account_number' => '9999888877',
    ]);

    grantAccountAccess($entityA);

    $this->get(route('entity.accounts.index', $entityA))
        ->assertOk()
        ->assertSee('Kas & Rekening')
        ->assertSee('BCA Keluarga A')
        ->assertSee('******7890')
        ->assertDontSee('1234567890')
        ->assertDontSee('BCA Keluarga B')
        ->assertDontSee('9999888877');
});

it('creates an account owned by the route entity and rejects a forged finance_entity_id', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    grantAccountAccess($entityA);

    $this->post(route('entity.accounts.store', $entityA), [
        'name' => 'GoPay',
        'type' => FinanceAccountType::EWALLET->value,
        'finance_entity_id' => $entityB->id,
        'public_id' => '01FORGEDPUBLICIDACCOUNT00',
    ])->assertSessionHasErrors(['finance_entity_id', 'public_id']);

    $this->post(route('entity.accounts.store', $entityA), [
        'name' => 'GoPay',
        'type' => FinanceAccountType::EWALLET->value,
    ])->assertRedirect(route('entity.accounts.index', $entityA));

    $this->assertDatabaseHas('finance_accounts', [
        'name' => 'GoPay',
        'finance_entity_id' => $entityA->id,
    ]);
    expect(FinanceAccount::query()->where('finance_entity_id', $entityB->id)->where('name', 'GoPay')->count())->toBe(0);
});

it('rejects a duplicate account name inside the same entity but allows the same name on another entity', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    grantAccountAccess($entityA);

    $this->post(route('entity.accounts.store', $entityA), [
        'name' => 'BCA',
        'type' => FinanceAccountType::BANK->value,
    ])->assertRedirect();

    $this->from(route('entity.accounts.create', $entityA))
        ->post(route('entity.accounts.store', $entityA), [
            'name' => 'BCA',
            'type' => FinanceAccountType::BANK->value,
        ])
        ->assertRedirect(route('entity.accounts.create', $entityA))
        ->assertSessionHasErrors('name');

    grantAccountAccess($entityB);

    $this->post(route('entity.accounts.store', $entityB), [
        'name' => 'BCA',
        'type' => FinanceAccountType::BANK->value,
    ])->assertRedirect(route('entity.accounts.index', $entityB));

    expect(FinanceAccount::query()->where('name', 'BCA')->count())->toBe(2);
});

it('returns 404 when a private user opens another entity account', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    $foreign = app(FinanceAccountService::class)->create($entityB, [
        'name' => 'BRI Kebun',
        'type' => FinanceAccountType::BANK,
    ]);

    grantAccountAccess($entityA);

    $this->get(route('entity.accounts.edit', [$entityA, $foreign]))->assertNotFound();
    $this->put(route('entity.accounts.update', [$entityA, $foreign]), [
        'name' => 'Hacked',
        'type' => FinanceAccountType::CASH->value,
    ])->assertNotFound();
    $this->post(route('entity.accounts.activate', [$entityA, $foreign]))->assertNotFound();
    $this->post(route('entity.accounts.deactivate', [$entityA, $foreign]))->assertNotFound();
    $this->post(route('entity.accounts.set-default', [$entityA, $foreign]))->assertNotFound();
});

it('allows a private user to edit activate deactivate and set default on the owned entity', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $service = app(FinanceAccountService::class);
    $first = $service->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
    ]);
    $second = $service->create($entity, [
        'name' => 'BCA',
        'type' => FinanceAccountType::BANK,
        'bank_name' => 'BCA',
        'account_number' => '1111222233',
    ]);

    grantAccountAccess($entity);

    $this->put(route('entity.accounts.update', [$entity, $second]), [
        'name' => 'BCA Utama',
        'type' => FinanceAccountType::BANK->value,
        'bank_name' => 'BCA',
        'account_number' => '1111222233',
        'opening_balance' => '0',
    ])->assertRedirect(route('entity.accounts.index', $entity));

    expect($second->fresh()->name)->toBe('BCA Utama');

    $this->post(route('entity.accounts.set-default', [$entity, $second]))
        ->assertRedirect(route('entity.accounts.index', $entity));

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse();

    $this->post(route('entity.accounts.deactivate', [$entity, $second]))
        ->assertRedirect(route('entity.accounts.index', $entity));

    expect($second->fresh()->is_active)->toBeFalse()
        ->and($first->fresh()->is_default)->toBeTrue();

    $this->post(route('entity.accounts.activate', [$entity, $second]))
        ->assertRedirect(route('entity.accounts.index', $entity));

    expect($second->fresh()->is_active)->toBeTrue();
});

it('shows Kas & Rekening on both FAMILY and BUSINESS private navigation', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Nav']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Nav']);

    grantAccountAccess($family);
    $this->get(route('entity.dashboard', $family))
        ->assertOk()
        ->assertSee('Kas & Rekening', false);

    grantAccountAccess($business);
    $this->get(route('entity.dashboard', $business))
        ->assertOk()
        ->assertSee('Kas & Rekening', false);
});
