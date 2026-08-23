<?php

use App\Enums\FinanceAccountType;
use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use App\Models\User;
use App\Services\FinanceAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function accountService(): FinanceAccountService
{
    return app(FinanceAccountService::class);
}

it('provisions FAMILY and BUSINESS default cash accounts for existing entities', function () {
    $family = FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_PRIBADI)->first();
    $business = FinanceEntity::query()->where('slug', FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN)->first();

    expect($family)->not->toBeNull()
        ->and($business)->not->toBeNull();

    $familyAccount = $family->accounts()->first();
    $businessAccount = $business->accounts()->first();

    expect($familyAccount)->not->toBeNull()
        ->and($familyAccount->name)->toBe('Kas Utama Keluarga')
        ->and($familyAccount->type)->toBe(FinanceAccountType::CASH)
        ->and((float) $familyAccount->opening_balance)->toBe(0.0)
        ->and($familyAccount->is_default)->toBeTrue()
        ->and($familyAccount->is_active)->toBeTrue()
        ->and($businessAccount)->not->toBeNull()
        ->and($businessAccount->name)->toBe('Kas Utama Usaha')
        ->and($businessAccount->type)->toBe(FinanceAccountType::CASH)
        ->and((float) $businessAccount->opening_balance)->toBe(0.0)
        ->and($businessAccount->is_default)->toBeTrue();
});

it('generates public_id automatically for a finance account', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = accountService()->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
    ]);

    expect($account->public_id)->not->toBeEmpty()
        ->and(strlen($account->public_id))->toBe(26);
});

it('makes the first account the default', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = accountService()->create($entity, [
        'name' => 'BCA',
        'type' => FinanceAccountType::BANK,
        'is_default' => false,
    ]);

    expect($account->is_default)->toBeTrue()
        ->and($entity->accounts()->where('is_default', true)->count())->toBe(1);
});

it('switches default atomically so only one default remains', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $first = accountService()->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
    ]);
    $second = accountService()->create($entity, [
        'name' => 'BCA',
        'type' => FinanceAccountType::BANK,
    ]);

    accountService()->setDefault($second);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($entity->accounts()->where('is_default', true)->count())->toBe(1);
});

it('promotes a replacement when the default account is deactivated', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $first = accountService()->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
    ]);
    $second = accountService()->create($entity, [
        'name' => 'GoPay',
        'type' => FinanceAccountType::EWALLET,
    ]);

    accountService()->deactivate($first);

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('allows an entity to have no active default when the last active account is deactivated', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = accountService()->create($entity, [
        'name' => 'Kas Utama Keluarga',
        'type' => FinanceAccountType::CASH,
    ]);

    accountService()->deactivate($account);

    expect($account->fresh()->is_default)->toBeFalse()
        ->and($entity->accounts()->where('is_default', true)->count())->toBe(0)
        ->and($entity->accounts()->where('is_active', true)->count())->toBe(0);
});

it('provisions a missing default account for an existing entity and stays idempotent', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tanpa Kas']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tanpa Kas']);

    expect($family->accounts()->count())->toBe(0)
        ->and($business->accounts()->count())->toBe(0);

    $created = accountService()->provisionMissingDefaults();

    expect($created)->toBeGreaterThanOrEqual(2)
        ->and($family->fresh()->accounts()->count())->toBe(1)
        ->and($family->fresh()->accounts()->first()->name)->toBe('Kas Utama Keluarga')
        ->and((float) $family->fresh()->accounts()->first()->opening_balance)->toBe(0.0)
        ->and($business->fresh()->accounts()->first()->name)->toBe('Kas Utama Usaha');

    $again = accountService()->provisionMissingDefaults();

    expect($again)->toBe(0)
        ->and($family->fresh()->accounts()->count())->toBe(1)
        ->and($business->fresh()->accounts()->count())->toBe(1);
});

it('creates a default account atomically when admin creates a new finance entity', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Keluarga Baru',
            'type' => FinanceEntityType::FAMILY->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $entity = FinanceEntity::query()->where('name', 'Keluarga Baru')->first();
    $account = $entity->accounts()->first();

    expect($entity)->not->toBeNull()
        ->and($account)->not->toBeNull()
        ->and($account->name)->toBe('Kas Utama Keluarga')
        ->and($account->type)->toBe(FinanceAccountType::CASH)
        ->and($account->is_default)->toBeTrue()
        ->and((float) $account->opening_balance)->toBe(0.0);

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Usaha Baru',
            'type' => FinanceEntityType::BUSINESS->value,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $business = FinanceEntity::query()->where('name', 'Usaha Baru')->first();

    expect($business->accounts()->first()->name)->toBe('Kas Utama Usaha');
});

it('locks FAMILY or BUSINESS type once the entity has a finance account', function () {
    $entity = FinanceEntity::factory()->family()->create([
        'name' => 'Keluarga Berkas',
        'slug' => 'keluarga-berkas',
    ]);
    accountService()->ensureDefaultAccount($entity);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.finance-entities.edit', $entity))
        ->put(route('admin.finance-entities.update', $entity), [
            'name' => 'Keluarga Berkas',
            'slug' => 'keluarga-berkas',
            'type' => FinanceEntityType::BUSINESS->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.edit', $entity))
        ->assertSessionHasErrors('type');

    expect($entity->fresh()->type)->toBe(FinanceEntityType::FAMILY);
});
