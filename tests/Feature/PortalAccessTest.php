<?php

use App\Enums\PortalAccessResourceType;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\PortalAccessToken;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\PortalAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function financeGrant(FinanceEntity $entity): array
{
    return [
        'resource_type' => PortalAccessResourceType::FINANCE,
        'finance_entity_id' => (int) $entity->id,
    ];
}

function plantationGrant(FinanceEntity $entity): array
{
    return [
        'resource_type' => PortalAccessResourceType::PLANTATION,
        'finance_entity_id' => (int) $entity->id,
    ];
}

function issuePortalAccess(string $name, array $grants, $expiresAt = null): array
{
    return app(PortalAccessTokenService::class)->issue($name, $grants, $expiresAt);
}

function openPortalAccess(string $name, array $grants): string
{
    [, $plain] = issuePortalAccess($name, $grants);
    test()->get(route('access.show', $plain))->assertRedirect(route('home'));

    return $plain;
}

it('shows one finance card for a single FinanceEntity portal grant', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Satu']);
    openPortalAccess('Yusril', [financeGrant($family)]);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Satu')
        ->assertSee('Keuangan Keluarga')
        ->assertDontSee('Management Kebun')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(1)
        ->and($html)->toContain('data-card-count="1"');
});

it('shows two finance cards for FAMILY and BUSINESS grants', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Yusril']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    openPortalAccess('Yusril', [financeGrant($family), financeGrant($business)]);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Yusril')
        ->assertSee('Usaha Kebun')
        ->assertSee('Keuangan Keluarga')
        ->assertSee('Keuangan Usaha')
        ->assertDontSee('Management Kebun')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(2)
        ->and($html)->toContain('data-card-count="2"');
});

it('shows finance and plantation cards only when both are granted', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    portalActivatePlantation($business);
    openPortalAccess('Kebun', [financeGrant($business), plantationGrant($business)]);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Usaha Kebun')
        ->assertSee('Keuangan Usaha')
        ->assertSee('Management Kebun')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(2)
        ->and($html)->toContain('data-app-type="finance_business"')
        ->and($html)->toContain('data-app-type="plantation"');
});

it('does not auto-grant Plantation when only BUSINESS finance is granted', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    portalActivatePlantation($business);
    openPortalAccess('Tanpa Kebun', [financeGrant($business)]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Usaha Kebun')
        ->assertSee('Keuangan Usaha')
        ->assertDontSee('Management Kebun');

    $this->post(route('portal.plantation.handoff', $business))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('renders three cards for family, business, and plantation grants', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Yusril']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    portalActivatePlantation($business);
    openPortalAccess('Yusril', [
        financeGrant($family),
        financeGrant($business),
        plantationGrant($business),
    ]);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Yusril')
        ->assertSee('Usaha Kebun')
        ->assertSee('Management Kebun')
        ->getContent();

    expect($html)->toContain('data-card-count="3"')
        ->and(substr_count($html, 'data-app-type="'))->toBe(3);
});

it('omits entities that are not in the portal grants', function () {
    Http::preventStrayRequests();

    $owned = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Milik']);
    $foreign = FinanceEntity::factory()->business()->create(['name' => 'Usaha Orang Lain']);
    portalActivatePlantation($foreign);
    openPortalAccess('Milik', [financeGrant($owned)]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Milik')
        ->assertDontSee('Usaha Orang Lain')
        ->assertDontSee('Management Kebun');
});

it('rejects a revoked portal access token', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Revoke']);
    [$token, $plain] = issuePortalAccess('Revoke', [financeGrant($family)]);

    $this->get(route('access.show', $plain))->assertRedirect(route('home'));
    $this->get(route('entity.dashboard', $family))->assertOk();

    app(PortalAccessTokenService::class)->revoke($token);

    $this->get(route('access.show', $plain))->assertNotFound();
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Keluarga Revoke');
    $this->get(route('entity.dashboard', $family))->assertNotFound();
});

it('rejects an expired portal access token', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Expired']);
    [$token, $plain] = issuePortalAccess('Expired', [financeGrant($family)], now()->addHour());
    $token->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->get(route('access.show', $plain))->assertNotFound();
});

it('does not allow an inactive finance entity to be opened from a portal grant', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Nonaktif', 'is_active' => true]);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Aktif']);
    openPortalAccess('Campuran', [financeGrant($family), financeGrant($business)]);

    $family->update(['is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Keluarga Nonaktif')
        ->assertSee('Usaha Aktif');

    $this->get(route('entity.dashboard', $family))->assertNotFound();
    $this->get(route('entity.dashboard', $business))->assertOk();
});

it('does not allow an inactive plantation integration to be opened', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Nonaktif Kebun']);
    $integration = portalActivatePlantation($business);
    openPortalAccess('Kebun Nonaktif', [financeGrant($business), plantationGrant($business)]);

    $integration->update(['status' => \App\Enums\PlantationIntegrationStatus::INACTIVE]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Usaha Nonaktif Kebun')
        ->assertDontSee('Management Kebun');

    $this->post(route('portal.plantation.handoff', $business))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('stores only the portal token hash and never the plaintext', function () {
    $family = FinanceEntity::factory()->family()->create();
    [$token, $plain] = issuePortalAccess('Hash Only', [financeGrant($family)]);

    $this->assertDatabaseHas('portal_access_tokens', [
        'id' => $token->id,
        'token_hash' => PortalAccessToken::hashToken($plain),
    ]);
    $this->assertDatabaseMissing('portal_access_tokens', [
        'token_hash' => $plain,
    ]);

    expect(PortalAccessToken::query()->pluck('token_hash'))
        ->not->toContain($plain)
        ->and($token->token_hash)->not->toBe($plain)
        ->and(strlen($plain))->toBe(64);
});

it('does not expose plaintext, hashes, or plantation secrets on the portal', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Rahasia']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Rahasia']);
    portalActivatePlantation($business);
    [$token, $plain] = issuePortalAccess('Rahasia', [
        financeGrant($family),
        financeGrant($business),
        plantationGrant($business),
    ]);

    $this->get(route('access.show', $plain))->assertRedirect(route('home'));

    $html = $this->get(route('home'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain($plain)
        ->not->toContain($token->getAttributes()['token_hash'])
        ->not->toContain('testing-plantation-service-token')
        ->not->toContain((string) config('services.plantation.token'))
        ->not->toContain(PORTAL_PLANTATION_TOKEN)
        ->not->toContain(PORTAL_PLANTATION_URL);
});

it('rejects cross-entity dashboard and plantation URL manipulation', function () {
    Http::preventStrayRequests();

    $owned = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Sah']);
    $foreign = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tebakan']);
    portalActivatePlantation($foreign);
    openPortalAccess('Sah', [financeGrant($owned)]);

    $this->get('/e/'.$foreign->public_id.'/dashboard')
        ->assertNotFound()
        ->assertDontSee('Usaha Tebakan');

    $this->post(route('portal.plantation.handoff', $foreign))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('keeps existing finance entity access links working', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Lama']);
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('entity.dashboard', $entity));

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Keluarga Lama');

    expect(FinanceEntityAccessToken::query()->count())->toBe(1)
        ->and(PortalAccessToken::query()->count())->toBe(0);
});

it('lets a plantation-only portal grant open kebun without finance dashboard access', function () {
    portalFakeHandoff();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun Only']);
    portalActivatePlantation($business);
    openPortalAccess('Kebun Only', [plantationGrant($business)]);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Management Kebun')
        ->assertDontSee('Keuangan Usaha')
        ->getContent();

    expect($html)->toContain('data-card-count="1"')
        ->and($html)->toContain('data-app-type="plantation"');

    $this->get(route('entity.dashboard', $business))->assertNotFound();

    $this->post(route('portal.plantation.handoff', $business))
        ->assertRedirect(PORTAL_PLANTATION_URL);

    expect(json_encode(session()->all()) ?: '')
        ->not->toContain(PORTAL_PLANTATION_TOKEN)
        ->not->toContain(PORTAL_PLANTATION_URL);
});

it('always sends a portal access token to the Arusku portal even for one grant', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Portal']);
    [, $plain] = issuePortalAccess('Satu', [financeGrant($family)]);

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('home'));
});
