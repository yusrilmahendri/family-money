<?php

use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\User;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an admin to create a private access link', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.finance-entities.access-links.store', $entity), [
            'label' => 'Link keluarga',
        ]);

    $response->assertOk()
        ->assertViewIs('admin.access-links.created')
        ->assertViewHas('plainToken')
        ->assertSee('Private Link Created')
        ->assertSee('Link hanya dapat dilihat sekarang');

    $plain = $response->viewData('plainToken');
    $token = FinanceEntityAccessToken::query()->first();

    expect($token)->not->toBeNull()
        ->and($token->label)->toBe('Link keluarga')
        ->and($token->token_hash)->toBe(FinanceEntityAccessToken::hashToken($plain))
        ->and($token->token_hash)->not->toBe($plain)
        ->and(strlen($plain))->toBe(64);
});

it('stores only the token hash and never the plaintext', function () {
    $entity = FinanceEntity::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.finance-entities.access-links.store', $entity));

    $plain = $response->viewData('plainToken');

    $this->assertDatabaseHas('finance_entity_access_tokens', [
        'token_hash' => FinanceEntityAccessToken::hashToken($plain),
    ]);
    $this->assertDatabaseMissing('finance_entity_access_tokens', [
        'token_hash' => $plain,
    ]);

    expect(FinanceEntityAccessToken::query()->pluck('token_hash'))
        ->not->toContain($plain);
});

it('keeps token hashes unique', function () {
    $first = FinanceEntityAccessToken::factory()->create();
    $duplicate = FinanceEntityAccessToken::factory()->make([
        'finance_entity_id' => FinanceEntity::factory(),
    ]);
    $duplicate->token_hash = $first->getRawOriginal('token_hash') ?? $first->token_hash;

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

it('ignores token values supplied in the admin request', function () {
    $entity = FinanceEntity::factory()->create();
    $injected = str_repeat('ab', 32);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.finance-entities.access-links.store', $entity), [
            'token' => $injected,
            'token_hash' => 'injected-hash',
            'plain_token' => $injected,
        ]);

    $plain = $response->viewData('plainToken');
    $token = FinanceEntityAccessToken::query()->first();

    expect($plain)->not->toBe($injected)
        ->and($token->token_hash)->not->toBe('injected-hash')
        ->and($token->token_hash)->not->toBe(FinanceEntityAccessToken::hashToken($injected));
});

it('does not display the token hash on the admin access-link page', function () {
    $entity = FinanceEntity::factory()->create();
    [$token] = app(FinanceEntityAccessTokenService::class)->issue($entity, 'Family link');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.finance-entities.access-links.index', $entity))
        ->assertOk()
        ->assertSee('Family link')
        ->assertDontSee($token->getAttributes()['token_hash'], false);
});

it('rejects a past expiration on the admin form', function () {
    $entity = FinanceEntity::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.finance-entities.access-links.index', $entity))
        ->post(route('admin.finance-entities.access-links.store', $entity), [
            'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('admin.finance-entities.access-links.index', $entity))
        ->assertSessionHasErrors('expires_at');

    expect(FinanceEntityAccessToken::query()->count())->toBe(0);
});

it('shows the new plaintext only on create or regenerate responses', function () {
    $entity = FinanceEntity::factory()->create();
    $admin = User::factory()->admin()->create();

    $created = $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.store', $entity), [
            'label' => 'Once',
        ]);

    $plain = $created->viewData('plainToken');
    $token = FinanceEntityAccessToken::query()->first();

    $created->assertSee($plain);

    $this->actingAs($admin)
        ->get(route('admin.finance-entities.access-links.index', $entity))
        ->assertOk()
        ->assertDontSee($plain, false);

    $regenerated = $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.regenerate', [$entity, $token]));

    $newPlain = $regenerated->viewData('plainToken');

    $regenerated->assertOk()->assertSee($newPlain);
    expect($newPlain)->not->toBe($plain);

    $this->actingAs($admin)
        ->get(route('admin.finance-entities.access-links.index', $entity))
        ->assertDontSee($newPlain, false)
        ->assertDontSee($plain, false);
});
