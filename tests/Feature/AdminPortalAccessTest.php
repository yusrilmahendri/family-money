<?php

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Models\AuditLog;
use App\Models\FinanceEntity;
use App\Models\PortalAccessGrant;
use App\Models\PortalAccessToken;
use App\Models\User;
use App\Services\PortalAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function portalAccessAdmin(): User
{
    return User::factory()->admin()->create();
}

function portalAccessFamily(): FinanceEntity
{
    return FinanceEntity::factory()->family()->create(['name' => 'Keluarga Admin']);
}

it('allows an admin to create a portal access link with selected services', function () {
    $family = portalAccessFamily();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    portalActivatePlantation($business);

    $response = $this->actingAs(portalAccessAdmin())
        ->post(route('admin.portal-access.store'), [
            'name' => 'Yusril',
            'grants' => [
                'finance:'.$family->public_id,
                'finance:'.$business->public_id,
                'plantation:'.$business->public_id,
            ],
        ]);

    $response->assertOk()
        ->assertViewIs('admin.portal-access.created')
        ->assertSee('Portal Access Created')
        ->assertSee('Link hanya dapat dilihat sekarang')
        ->assertSee('Yusril');

    $plain = $response->viewData('plainToken');
    $token = PortalAccessToken::query()->first();

    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('Yusril')
        ->and($token->token_hash)->toBe(PortalAccessToken::hashToken($plain))
        ->and($token->token_hash)->not->toBe($plain)
        ->and(strlen($plain))->toBe(64)
        ->and(PortalAccessGrant::query()->count())->toBe(3);
});

it('stores only the hash and never displays it on the list page', function () {
    $family = portalAccessFamily();
    $admin = portalAccessAdmin();

    $created = $this->actingAs($admin)
        ->post(route('admin.portal-access.store'), [
            'name' => 'Once',
            'grants' => ['finance:'.$family->public_id],
        ]);

    $plain = $created->viewData('plainToken');
    $token = PortalAccessToken::query()->first();
    $hash = $token->getAttributes()['token_hash'];

    $created->assertSee($plain);

    $this->actingAs($admin)
        ->get(route('admin.portal-access.index'))
        ->assertOk()
        ->assertSee('Once')
        ->assertSee('Keuangan Keluarga — Keluarga Admin')
        ->assertDontSee($plain, false)
        ->assertDontSee($hash, false);
});

it('ignores injected token values on create', function () {
    $family = portalAccessFamily();
    $injected = str_repeat('ab', 32);

    $response = $this->actingAs(portalAccessAdmin())
        ->post(route('admin.portal-access.store'), [
            'name' => 'Inject',
            'grants' => ['finance:'.$family->public_id],
            'token' => $injected,
            'token_hash' => 'injected-hash',
            'plain_token' => $injected,
        ]);

    $response->assertSessionHasErrors(['token', 'token_hash', 'plain_token']);
    expect(PortalAccessToken::query()->count())->toBe(0);
});

it('requires at least one grant', function () {
    $this->actingAs(portalAccessAdmin())
        ->from(route('admin.portal-access.index'))
        ->post(route('admin.portal-access.store'), [
            'name' => 'Kosong',
        ])
        ->assertRedirect(route('admin.portal-access.index'))
        ->assertSessionHasErrors('grants');

    expect(PortalAccessToken::query()->count())->toBe(0);
});

it('rejects a plantation grant without an integration', function () {
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tanpa Kebun']);

    $this->actingAs(portalAccessAdmin())
        ->from(route('admin.portal-access.index'))
        ->post(route('admin.portal-access.store'), [
            'name' => 'Tanpa Integrasi',
            'grants' => ['plantation:'.$business->public_id],
        ])
        ->assertRedirect(route('admin.portal-access.index'))
        ->assertSessionHasErrors('grants');

    expect(PortalAccessToken::query()->count())->toBe(0);
});

it('lists status, expiry, and last used without hashes', function () {
    $family = portalAccessFamily();
    [$token, $plain] = app(PortalAccessTokenService::class)->issue('Status', [
        ['resource_type' => 'finance', 'finance_entity_id' => $family->id],
    ]);
    $token->forceFill([
        'expires_at' => now()->addDay(),
        'last_used_at' => now()->subHour(),
    ])->save();

    $this->actingAs(portalAccessAdmin())
        ->get(route('admin.portal-access.index'))
        ->assertOk()
        ->assertSee('Status')
        ->assertSee('Aktif')
        ->assertSee($token->expires_at->format('Y-m-d H:i'))
        ->assertSee($token->last_used_at->format('Y-m-d H:i'))
        ->assertDontSee($token->getAttributes()['token_hash'], false)
        ->assertDontSee($plain, false);
});

it('shows the new plaintext only on regenerate', function () {
    $family = portalAccessFamily();
    $admin = portalAccessAdmin();
    [$token, $plain] = app(PortalAccessTokenService::class)->issue('Regen', [
        ['resource_type' => 'finance', 'finance_entity_id' => $family->id],
    ]);

    $regenerated = $this->actingAs($admin)
        ->post(route('admin.portal-access.regenerate', $token));

    $newPlain = $regenerated->viewData('plainToken');
    $replacement = PortalAccessToken::query()->where('is_active', true)->first();

    $regenerated->assertOk()->assertSee($newPlain);
    expect($newPlain)->not->toBe($plain)
        ->and($token->fresh()->is_active)->toBeFalse()
        ->and($replacement->grants()->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('admin.portal-access.index'))
        ->assertDontSee($newPlain, false)
        ->assertDontSee($plain, false);
});

it('revokes, reactivates, and permanently deletes portal access', function () {
    $family = portalAccessFamily();
    $admin = portalAccessAdmin();
    [$token] = app(PortalAccessTokenService::class)->issue('Lifecycle', [
        ['resource_type' => 'finance', 'finance_entity_id' => $family->id],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.portal-access.revoke', $token))
        ->assertRedirect(route('admin.portal-access.index'));

    expect($token->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.portal-access.activate', $token))
        ->assertRedirect(route('admin.portal-access.index'));

    expect($token->fresh()->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.portal-access.destroy', $token))
        ->assertRedirect(route('admin.portal-access.index'));

    expect(PortalAccessToken::query()->find($token->id))->toBeNull()
        ->and(PortalAccessGrant::query()->count())->toBe(0);
});

it('updates grants without rotating the token', function () {
    $family = portalAccessFamily();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kedua']);
    [$token] = app(PortalAccessTokenService::class)->issue('Edit', [
        ['resource_type' => 'finance', 'finance_entity_id' => $family->id],
    ]);
    $hash = $token->getAttributes()['token_hash'];

    $this->actingAs(portalAccessAdmin())
        ->put(route('admin.portal-access.update', $token), [
            'name' => 'Edit Baru',
            'grants' => [
                'finance:'.$family->public_id,
                'finance:'.$business->public_id,
            ],
        ])
        ->assertRedirect(route('admin.portal-access.index'));

    $fresh = $token->fresh();

    expect($fresh->name)->toBe('Edit Baru')
        ->and($fresh->getAttributes()['token_hash'])->toBe($hash)
        ->and($fresh->grants()->count())->toBe(2);
});

it('records ACCESS_LINK_DELETED without plaintext or token hash', function () {
    $family = portalAccessFamily();
    $created = $this->actingAs(portalAccessAdmin())
        ->post(route('admin.portal-access.store'), [
            'name' => 'Audit hapus',
            'grants' => ['finance:'.$family->public_id],
        ]);
    $plain = $created->viewData('plainToken');
    $token = PortalAccessToken::query()->first();
    $hash = $token->getRawOriginal('token_hash') ?? $token->token_hash;
    $tokenId = $token->id;

    $this->delete(route('admin.portal-access.destroy', $token))->assertRedirect();

    $log = AuditLog::query()
        ->where('action', AuditAction::ACCESS_LINK_DELETED)
        ->latest('id')
        ->first();
    $payload = json_encode([$log?->old_values, $log?->new_values]) ?: '';

    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe(AuditActorType::ADMIN)
        ->and($log->auditable_id)->toBe($tokenId)
        ->and($log->old_values['name'])->toBe('Audit hapus')
        ->and($payload)->not->toContain($plain)
        ->not->toContain($hash)
        ->and($log->old_values)->not->toHaveKey('token_hash')
        ->and($log->old_values)->not->toHaveKey('token');
});

it('rejects guests and non-admins from portal access admin', function () {
    $family = portalAccessFamily();
    [$token] = app(PortalAccessTokenService::class)->issue('Protected', [
        ['resource_type' => 'finance', 'finance_entity_id' => $family->id],
    ]);

    $this->get(route('admin.portal-access.index'))
        ->assertRedirect(route('admin.login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.portal-access.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->delete(route('admin.portal-access.destroy', $token))
        ->assertForbidden();

    expect(PortalAccessToken::query()->find($token->id))->not->toBeNull();
});
