<?php

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a FAMILY finance entity', function () {
    $entity = FinanceEntity::factory()->family()->create([
        'name' => 'Keluarga A',
    ]);

    expect($entity->exists)->toBeTrue()
        ->and($entity->name)->toBe('Keluarga A')
        ->and($entity->type)->toBe(FinanceEntityType::FAMILY)
        ->and($entity->type->value)->toBe('FAMILY');

    $this->assertDatabaseHas('finance_entities', [
        'id' => $entity->id,
        'name' => 'Keluarga A',
        'type' => FinanceEntityType::FAMILY->value,
    ]);
});

it('can create a BUSINESS finance entity', function () {
    $entity = FinanceEntity::factory()->business()->create([
        'name' => 'Usaha Kebun A',
    ]);

    expect($entity->exists)->toBeTrue()
        ->and($entity->name)->toBe('Usaha Kebun A')
        ->and($entity->type)->toBe(FinanceEntityType::BUSINESS)
        ->and($entity->type->value)->toBe('BUSINESS');

    $this->assertDatabaseHas('finance_entities', [
        'id' => $entity->id,
        'name' => 'Usaha Kebun A',
        'type' => FinanceEntityType::BUSINESS->value,
    ]);
});

it('generates public_id automatically', function () {
    $entity = FinanceEntity::create([
        'name' => 'Keluarga B',
        'type' => FinanceEntityType::FAMILY,
    ]);

    expect($entity->public_id)->not->toBeEmpty()
        ->and($entity->public_id)->toBeString()
        ->and(strlen($entity->public_id))->toBe(26);

    $this->assertDatabaseHas('finance_entities', [
        'id' => $entity->id,
        'public_id' => $entity->public_id,
    ]);
});

it('keeps public_id unique', function () {
    $first = FinanceEntity::factory()->create();
    $second = FinanceEntity::factory()->create();

    expect($first->public_id)->not->toBe($second->public_id);

    $duplicate = FinanceEntity::factory()->make([
        'name' => 'Duplicate Public Id',
        'slug' => 'duplicate-public-id',
    ]);
    $duplicate->public_id = $first->public_id;

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

it('rejects an invalid type', function () {
    expect(fn () => FinanceEntity::factory()->create([
        'type' => 'INVALID',
    ]))->toThrow(ValueError::class);

    expect(FinanceEntityType::isValid('INVALID'))->toBeFalse()
        ->and(FinanceEntityType::isValid(FinanceEntityType::FAMILY->value))->toBeTrue();
});

it('defaults is_active to true', function () {
    $entity = FinanceEntity::create([
        'name' => 'Usaha Kebun B',
        'type' => FinanceEntityType::BUSINESS,
    ]);

    expect($entity->is_active)->toBeTrue()
        ->and($entity->fresh()?->is_active)->toBeTrue();

    $this->assertDatabaseHas('finance_entities', [
        'id' => $entity->id,
        'is_active' => 1,
    ]);
});

it('does not accept public_id from mass assignment', function () {
    $entity = FinanceEntity::create([
        'name' => 'Keluarga C',
        'type' => FinanceEntityType::FAMILY,
        'public_id' => 'user-supplied-public-id',
    ]);

    expect($entity->public_id)->not->toBe('user-supplied-public-id')
        ->and($entity->public_id)->not->toBeEmpty();
});
