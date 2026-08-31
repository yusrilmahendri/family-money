<?php

use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\User;
use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceContext;
use App\Support\FinanceEntityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function issueLink(FinanceEntity $entity, ?string $label = null, $expiresAt = null): array
{
    return app(FinanceEntityAccessTokenService::class)->issue($entity, $label, $expiresAt);
}

it('allows a valid token to grant access and redirect to the entity dashboard', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    [, $plain] = issueLink($entity, 'A');

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('entity.dashboard', $entity));

    expect(FinanceEntityAccess::hasCapability($entity))->toBeTrue();

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Keluarga A')
        ->assertSee('Akses private berhasil')
        ->assertSee('FAMILY');

    expect(FinanceEntityAccessToken::query()->first()->last_used_at)->not->toBeNull();
});

it('rejects an invalid token with a generic 404', function () {
    $this->get('/access/'.str_repeat('aa', 32))
        ->assertNotFound()
        ->assertSee('Akses tidak valid')
        ->assertDontSee('expired')
        ->assertDontSee('disabled');
});

it('rejects an inactive token', function () {
    $entity = FinanceEntity::factory()->create();
    [$token, $plain] = issueLink($entity);
    $token->update(['is_active' => false]);

    $this->get(route('access.show', $plain))
        ->assertNotFound()
        ->assertSee('Akses tidak valid');
});

it('rejects an expired token', function () {
    $entity = FinanceEntity::factory()->create();
    [$token, $plain] = issueLink($entity, null, now()->addHour());
    $token->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->get(route('access.show', $plain))
        ->assertNotFound()
        ->assertSee('Akses tidak valid');
});

it('rejects access when the finance entity is inactive', function () {
    $entity = FinanceEntity::factory()->create(['is_active' => false]);
    [, $plain] = issueLink($entity);

    $this->get(route('access.show', $plain))
        ->assertNotFound()
        ->assertSee('Akses tidak valid');
});

it('allows a future expiration and grants access', function () {
    $entity = FinanceEntity::factory()->create(['name' => 'Keluarga Future']);
    [, $plain] = issueLink($entity, 'Future', now()->addDay());

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('entity.dashboard', $entity));

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Keluarga Future');
});

it('isolates capability to the granted entity', function () {
    $entityA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun B']);
    [, $plainA] = issueLink($entityA);

    $this->get(route('access.show', $plainA))->assertRedirect();

    $this->get(route('entity.dashboard', $entityA))
        ->assertOk()
        ->assertSee('Keluarga A');

    $this->get(route('entity.dashboard', $entityB))
        ->assertNotFound()
        ->assertSee('Akses tidak valid')
        ->assertDontSee('Usaha Kebun B');
});

it('does not grant access by guessing another entity public_id', function () {
    $entityA = FinanceEntity::factory()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->create(['name' => 'Keluarga B']);
    [, $plainA] = issueLink($entityA);

    $this->get(route('access.show', $plainA))->assertRedirect();

    $this->get('/e/'.$entityB->public_id.'/dashboard')
        ->assertNotFound();
});

it('can hold capabilities for two entities in the same session', function () {
    $entityA = FinanceEntity::factory()->create(['name' => 'Keluarga A']);
    $entityB = FinanceEntity::factory()->create(['name' => 'Usaha Kebun A']);
    [, $plainA] = issueLink($entityA);
    [, $plainB] = issueLink($entityB);

    $this->get(route('access.show', $plainA))
        ->assertRedirect(route('entity.dashboard', $entityA));
    $this->get(route('access.show', $plainB))
        ->assertRedirect(route('home'));

    expect(FinanceEntityAccess::hasCapability($entityA))->toBeTrue()
        ->and(FinanceEntityAccess::hasCapability($entityB))->toBeTrue();

    $this->get(route('entity.dashboard', $entityB))
        ->assertOk()
        ->assertSee('Usaha Kebun A');

    $this->get(route('entity.dashboard', $entityA))
        ->assertOk()
        ->assertSee('Keluarga A');
});

it('rejects a revoked token and existing session capability', function () {
    $entity = FinanceEntity::factory()->create(['name' => 'Keluarga Revoke']);
    [$token, $plain] = issueLink($entity);
    $admin = User::factory()->admin()->create();

    $this->get(route('access.show', $plain))->assertRedirect();
    $this->get(route('entity.dashboard', $entity))->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.revoke', [$entity, $token]))
        ->assertRedirect();

    $this->get(route('access.show', $plain))->assertNotFound();
    $this->get(route('entity.dashboard', $entity))->assertNotFound();
});

it('invalidates the old token and accepts the new token after regenerate', function () {
    $entity = FinanceEntity::factory()->create(['name' => 'Keluarga Regen']);
    [$token, $oldPlain] = issueLink($entity);
    $admin = User::factory()->admin()->create();

    $regenerated = $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.regenerate', [$entity, $token]));

    $newPlain = $regenerated->viewData('plainToken');

    expect($newPlain)->not->toBe($oldPlain);
    expect($token->fresh()->is_active)->toBeFalse();

    $this->get(route('access.show', $oldPlain))->assertNotFound();

    $this->get(route('access.show', $newPlain))
        ->assertRedirect(route('entity.dashboard', $entity));

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Keluarga Regen');
});

it('rejects an existing capability after the token expires', function () {
    $entity = FinanceEntity::factory()->create(['name' => 'Keluarga Expiry']);
    [, $plain] = issueLink($entity, 'Soon', now()->addDay());

    $this->get(route('access.show', $plain))->assertRedirect();
    $this->get(route('entity.dashboard', $entity))->assertOk();

    $this->travel(2)->days();

    $this->get(route('entity.dashboard', $entity))
        ->assertNotFound()
        ->assertSee('Akses tidak valid');
});

it('does not use the retired apps portal for private access', function () {
    $this->get('/apps')->assertRedirect(route('home'));
    $this->post(route('apps.select'), [
        'context' => FinanceContext::PRIBADI,
    ])->assertRedirect(route('home'));

    expect(session(FinanceContext::SESSION_KEY))->toBeNull();
});

it('keeps admin login working', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin-task3@example.com']);

    $this->post(route('admin.login.store'), [
        'email' => 'admin-task3@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});
