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

it('allows an admin to permanently delete an access link', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Delete Link']);
    [$token] = app(FinanceEntityAccessTokenService::class)->issue($entity, 'Link lama');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.finance-entities.access-links.index', $entity))
        ->assertOk()
        ->assertSee('Link lama')
        ->assertSee('Hapus')
        ->assertSee('Hapus Access Link?')
        ->assertSee('Hapus Permanen')
        ->assertDontSee($token->getAttributes()['token_hash'], false);

    $this->actingAs($admin)
        ->get(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertMethodNotAllowed();

    expect(FinanceEntityAccessToken::query()->find($token->id))->not->toBeNull();

    $this->actingAs($admin)
        ->from(route('admin.finance-entities.access-links.index', $entity))
        ->delete(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertRedirect(route('admin.finance-entities.access-links.index', $entity))
        ->assertSessionHas('success');

    expect(FinanceEntityAccessToken::query()->find($token->id))->toBeNull();
    $this->assertDatabaseMissing('finance_entity_access_tokens', ['id' => $token->id]);
    $this->assertDatabaseHas('finance_entities', ['id' => $entity->id]);
});

it('invalidates a deleted access link and an existing session capability', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Hapus Sesi']);
    [$token, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity, 'Sesi');
    $admin = User::factory()->admin()->create();

    $this->get(route('access.show', $plain))->assertRedirect();
    $this->get(route('entity.dashboard', $entity))->assertOk();

    $this->actingAs($admin)
        ->delete(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertRedirect();

    $this->get(route('access.show', $plain))->assertNotFound();
    $this->get(route('entity.dashboard', $entity))->assertNotFound();
});

it('does not delete an access token from another finance entity', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A Link']);
    $entityB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B Link']);
    [$tokenA] = app(FinanceEntityAccessTokenService::class)->issue($entityA, 'Link A');
    [$tokenB] = app(FinanceEntityAccessTokenService::class)->issue($entityB, 'Link B');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.finance-entities.access-links.destroy', [$entityA, $tokenB]))
        ->assertNotFound();

    expect(FinanceEntityAccessToken::query()->find($tokenA->id))->not->toBeNull()
        ->and(FinanceEntityAccessToken::query()->find($tokenB->id))->not->toBeNull();
});

it('rejects guests and non-admins from deleting an access link', function () {
    $entity = FinanceEntity::factory()->create();
    [$token] = app(FinanceEntityAccessTokenService::class)->issue($entity, 'Protected');

    $this->delete(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertRedirect(route('admin.login'));

    $this->actingAs(User::factory()->create())
        ->delete(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertForbidden();

    expect(FinanceEntityAccessToken::query()->find($token->id))->not->toBeNull();
});

it('does not delete the finance entity or its financial data when an access link is removed', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Data Aman']);
    $account = app(\App\Services\FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Aman',
        'type' => 'CASH',
        'opening_balance' => 50_000,
    ]);
    \App\Models\Transaction::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'category_id' => \App\Models\Category::factory()->create(['finance_entity_id' => $entity->id])->id,
        'amount' => 12_000,
        'transaction_date' => now(),
        'description' => 'BelanjaAman',
    ]);
    [$token] = app(FinanceEntityAccessTokenService::class)->issue($entity, 'Keep data');

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertRedirect();

    $this->assertDatabaseHas('finance_entities', ['id' => $entity->id, 'name' => 'Keluarga Data Aman']);
    $this->assertDatabaseHas('transactions', ['description' => 'BelanjaAman', 'finance_entity_id' => $entity->id]);
    $this->assertDatabaseHas('finance_accounts', ['name' => 'Kas Aman', 'finance_entity_id' => $entity->id]);
});

it('records ACCESS_LINK_DELETED without plaintext or token hash', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $created = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.finance-entities.access-links.store', $entity), [
            'label' => 'Audit hapus',
        ]);
    $plain = $created->viewData('plainToken');
    $token = FinanceEntityAccessToken::query()->first();
    $hash = $token->getRawOriginal('token_hash') ?? $token->token_hash;
    $tokenId = $token->id;

    $this->delete(route('admin.finance-entities.access-links.destroy', [$entity, $token]))
        ->assertRedirect();

    $log = \App\Models\AuditLog::query()
        ->where('action', \App\Enums\AuditAction::ACCESS_LINK_DELETED)
        ->latest('id')
        ->first();
    $payload = json_encode([$log?->old_values, $log?->new_values]) ?: '';

    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(\App\Enums\AuditActorType::ADMIN)
        ->and($log->auditable_id)->toBe($tokenId)
        ->and($log->old_values['label'])->toBe('Audit hapus')
        ->and($log->old_values['access_token_id'])->toBe($tokenId)
        ->and($log->old_values['finance_entity_public_id'])->toBe($entity->public_id)
        ->and($log->new_values)->toBeNull()
        ->and($payload)->not->toContain($plain)
        ->not->toContain($hash)
        ->and($log->old_values)->not->toHaveKey('token_hash')
        ->and($log->old_values)->not->toHaveKey('token');
});
