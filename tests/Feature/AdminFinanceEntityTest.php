<?php

use App\Enums\FinanceEntityType;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAdmin()
{
    return test()->actingAs(User::factory()->admin()->create());
}

it('redirects a guest away from the finance entity admin list', function () {
    $this->get(route('admin.finance-entities.index'))
        ->assertRedirect(route('admin.login'));
});

it('allows an admin to view the finance entity list', function () {
    $entity = FinanceEntity::factory()->family()->create([
        'name' => 'Keluarga A',
    ]);

    actingAdmin()
        ->get(route('admin.finance-entities.index'))
        ->assertOk()
        ->assertSee('Keluarga A')
        ->assertSee($entity->public_id)
        ->assertSee($entity->slug)
        ->assertSee('Hapus')
        ->assertSee('Hapus Permanen')
        ->assertDontSee('>'.$entity->id.'<', false);
});

it('allows an admin to create a FAMILY finance entity', function () {
    actingAdmin()
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Keluarga A',
            'type' => FinanceEntityType::FAMILY->value,
            'description' => 'Keluarga pertama',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $entity = FinanceEntity::query()->where('name', 'Keluarga A')->first();

    expect($entity)->not->toBeNull()
        ->and($entity->type)->toBe(FinanceEntityType::FAMILY)
        ->and($entity->is_active)->toBeTrue()
        ->and($entity->public_id)->not->toBeEmpty();
});

it('allows an admin to create a BUSINESS finance entity', function () {
    actingAdmin()
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Usaha Kebun A',
            'type' => FinanceEntityType::BUSINESS->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $this->assertDatabaseHas('finance_entities', [
        'name' => 'Usaha Kebun A',
        'type' => FinanceEntityType::BUSINESS->value,
    ]);
});

it('does not allow public_id to be overwritten from the request', function () {
    actingAdmin()
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Keluarga C',
            'type' => FinanceEntityType::FAMILY->value,
            'is_active' => '1',
            'public_id' => 'user-supplied-public-id',
            'id' => 999,
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    $entity = FinanceEntity::query()->where('name', 'Keluarga C')->first();

    expect($entity->public_id)->not->toBe('user-supplied-public-id')
        ->and($entity->id)->not->toBe(999);
});

it('allows an admin to edit a finance entity', function () {
    $entity = FinanceEntity::factory()->family()->create([
        'name' => 'Keluarga Lama',
        'slug' => 'keluarga-lama',
    ]);

    actingAdmin()
        ->put(route('admin.finance-entities.update', $entity), [
            'name' => 'Keluarga Baru',
            'slug' => 'keluarga-baru',
            'type' => FinanceEntityType::FAMILY->value,
            'description' => 'Updated',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    expect($entity->fresh())
        ->name->toBe('Keluarga Baru')
        ->slug->toBe('keluarga-baru')
        ->description->toBe('Updated');
});

it('rejects a duplicate slug on update', function () {
    $first = FinanceEntity::factory()->create(['slug' => 'keluarga-a']);
    $second = FinanceEntity::factory()->create(['slug' => 'keluarga-b']);

    actingAdmin()
        ->from(route('admin.finance-entities.edit', $second))
        ->put(route('admin.finance-entities.update', $second), [
            'name' => $second->name,
            'slug' => $first->slug,
            'type' => $second->type->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.edit', $second))
        ->assertSessionHasErrors('slug');
});

it('rejects an invalid type', function () {
    actingAdmin()
        ->from(route('admin.finance-entities.create'))
        ->post(route('admin.finance-entities.store'), [
            'name' => 'Entity Salah',
            'type' => 'INVALID',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.create'))
        ->assertSessionHasErrors('type');

    $this->assertDatabaseMissing('finance_entities', [
        'name' => 'Entity Salah',
    ]);
});

it('allows an admin to deactivate a finance entity', function () {
    $entity = FinanceEntity::factory()->create(['is_active' => true]);

    actingAdmin()
        ->post(route('admin.finance-entities.deactivate', $entity))
        ->assertRedirect(route('admin.finance-entities.index'));

    expect($entity->fresh()->is_active)->toBeFalse();
});

it('allows an admin to activate a finance entity again', function () {
    $entity = FinanceEntity::factory()->create(['is_active' => false]);

    actingAdmin()
        ->post(route('admin.finance-entities.activate', $entity))
        ->assertRedirect(route('admin.finance-entities.index'));

    expect($entity->fresh()->is_active)->toBeTrue();
});

it('allows changing type when the entity has no financial data', function () {
    $entity = FinanceEntity::factory()->family()->create([
        'name' => 'Kosong',
        'slug' => 'kosong',
    ]);

    actingAdmin()
        ->put(route('admin.finance-entities.update', $entity), [
            'name' => 'Kosong',
            'slug' => 'kosong',
            'type' => FinanceEntityType::BUSINESS->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    expect($entity->fresh()->type)->toBe(FinanceEntityType::BUSINESS);
});

it('locks type when the entity already has financial data but still allows name edits', function () {
    $entity = FinanceEntity::factory()->family()->create([
        'name' => 'Keluarga Data',
        'slug' => 'keluarga-data',
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $entity->id])->id,
    ]);

    actingAdmin()
        ->from(route('admin.finance-entities.edit', $entity))
        ->put(route('admin.finance-entities.update', $entity), [
            'name' => 'Keluarga Data',
            'slug' => 'keluarga-data',
            'type' => FinanceEntityType::BUSINESS->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.edit', $entity))
        ->assertSessionHasErrors('type');

    expect($entity->fresh()->type)->toBe(FinanceEntityType::FAMILY);

    actingAdmin()
        ->put(route('admin.finance-entities.update', $entity), [
            'name' => 'Keluarga Data Baru',
            'slug' => 'keluarga-data',
            'type' => FinanceEntityType::FAMILY->value,
            'description' => 'Nama tetap bisa diubah',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.finance-entities.index'));

    expect($entity->fresh())
        ->name->toBe('Keluarga Data Baru')
        ->description->toBe('Nama tetap bisa diubah')
        ->type->toBe(FinanceEntityType::FAMILY);
});

it('exposes a confirmation-protected delete route for finance entities', function () {
    $entity = FinanceEntity::factory()->create();

    expect(\Illuminate\Support\Facades\Route::has('admin.finance-entities.destroy'))->toBeTrue();

    actingAdmin()
        ->from(route('admin.finance-entities.index'))
        ->delete(route('admin.finance-entities.destroy', $entity), [
            'confirmation' => 'SALAH',
        ])
        ->assertRedirect(route('admin.finance-entities.index'))
        ->assertSessionHasErrors('confirmation');

    $this->assertDatabaseHas('finance_entities', [
        'id' => $entity->id,
        'public_id' => $entity->public_id,
    ]);
});
