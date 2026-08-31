<?php

use App\Enums\PlantationIntegrationStatus;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\User;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const PORTAL_PLANTATION_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PORTAL_PLANTATION_URL = 'http://plantation.test/access/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PORTAL_PLANTATION_PUBLIC_ID = '01PORTALPLANTATIONENTITY0001';

function portalIssue(FinanceEntity $entity): array
{
    return app(FinanceEntityAccessTokenService::class)->issue($entity);
}

function portalGrant(FinanceEntity $entity): string
{
    [, $plain] = portalIssue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();

    return $plain;
}

function portalActivatePlantation(FinanceEntity $entity, PlantationIntegrationStatus $status = PlantationIntegrationStatus::ACTIVE): PlantationIntegration
{
    return PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => PORTAL_PLANTATION_PUBLIC_ID,
        'status' => $status,
    ]);
}

function portalFakeHandoff(string $accessUrl = PORTAL_PLANTATION_URL): void
{
    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($accessUrl) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        if ($request->method() === 'POST' && preg_match('#/access-links$#', $path)) {
            return Http::response([
                'data' => [
                    'id' => 21,
                    'label' => $request['label'] ?? 'Finance portal',
                    'token' => PORTAL_PLANTATION_TOKEN,
                    'access_url' => $accessUrl,
                    'is_active' => true,
                ],
            ], 201);
        }

        return Http::response(['message' => 'Unexpected plantation request'], 500);
    });
}

it('redirects a session with only one finance entity to the existing dashboard', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tunggal']);
    [, $plain] = portalIssue($entity);

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('entity.dashboard', $entity));

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Keluarga Tunggal')
        ->assertDontSee('entity-topbar-portal')
        ->assertDontSee('portal-grid')
        ->assertDontSee('css/portal.css');
});

it('sends family plus business access to the portal with a card per entity', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Yusril']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Toko']);
    [, $familyPlain] = portalIssue($family);
    [, $businessPlain] = portalIssue($business);

    $this->get(route('access.show', $familyPlain))
        ->assertRedirect(route('entity.dashboard', $family));

    $this->get(route('access.show', $businessPlain))
        ->assertRedirect(route('home'));

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('ARUSKU')
        ->assertSee('Portal Arusku')
        ->assertSee('Pilih layanan yang ingin Anda buka.')
        ->assertSee('Keluarga Yusril')
        ->assertSee('Usaha Toko')
        ->assertSee('Keuangan Keluarga')
        ->assertSee('Keuangan Usaha')
        ->assertDontSee('Management Kebun')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(2)
        ->and($html)->toContain('data-card-count="2"')
        ->and($html)->toContain('href="'.route('entity.dashboard', $family).'"')
        ->and($html)->toContain('href="'.route('entity.dashboard', $business).'"')
        ->and($html)->not->toContain('>Buka</a>')
        ->and($html)->not->toContain('>Buka</button>');
});

it('shows finance and plantation cards when the authorized business has ACTIVE integration', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    portalActivatePlantation($business);
    [, $plain] = portalIssue($business);

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('home'));

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Usaha Kebun')
        ->assertSee('Keuangan Usaha')
        ->assertSee('Management Kebun')
        ->assertSee('Kelola pekerjaan kebun, pekerja, persediaan, panen, dan penjualan.')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(2)
        ->and($html)->toContain('data-card-count="2"')
        ->and($html)->toContain('data-app-type="plantation"')
        ->and($html)->toContain('action="'.route('portal.plantation.handoff', $business).'"')
        ->and($html)->toContain('Buka Management Kebun untuk Usaha Kebun');

    Http::assertNothingSent();
});

it('does not show a plantation card for business without integration', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tanpa Kebun']);
    [, $plain] = portalIssue($business);

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('entity.dashboard', $business));

    portalGrant(FinanceEntity::factory()->family()->create(['name' => 'Keluarga Pendamping']));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Usaha Tanpa Kebun')
        ->assertDontSee('Management Kebun');
});

it('does not show a plantation card when integration is INACTIVE', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Nonaktif Kebun']);
    portalActivatePlantation($business, PlantationIntegrationStatus::INACTIVE);
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Nonaktif']);
    portalGrant($family);
    portalGrant($business);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Usaha Nonaktif Kebun')
        ->assertDontSee('Management Kebun');
});

it('omits entities the session is not authorized to use', function () {
    Http::preventStrayRequests();

    $owned = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Milik']);
    $foreign = FinanceEntity::factory()->business()->create(['name' => 'Usaha Orang Lain']);
    portalActivatePlantation($foreign);
    portalGrant($owned);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Milik')
        ->assertDontSee('Usaha Orang Lain')
        ->assertDontSee('Management Kebun');
});

it('rejects a guessed destination or entity public id', function () {
    Http::preventStrayRequests();

    $owned = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Sah']);
    $foreign = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tebakan']);
    portalActivatePlantation($foreign);
    portalGrant($owned);

    $this->get('/e/'.$foreign->public_id.'/dashboard')
        ->assertNotFound()
        ->assertDontSee('Usaha Tebakan');

    $this->post(route('portal.plantation.handoff', $foreign))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('invalidates portal and destinations after the access token is revoked', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Revoke Portal']);
    [$token, $plain] = portalIssue($entity);
    $admin = User::factory()->admin()->create();

    $this->get(route('access.show', $plain))->assertRedirect();
    $this->get(route('entity.dashboard', $entity))->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.finance-entities.access-links.revoke', [$entity, $token]))
        ->assertRedirect();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Keluarga Revoke Portal');
    $this->get(route('entity.dashboard', $entity))->assertNotFound();
});

it('does not expose plaintext tokens, token hashes, or service secrets on the portal', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Rahasia']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Rahasia']);
    portalActivatePlantation($business);
    [$familyToken, $familyPlain] = portalIssue($family);
    [$businessToken, $businessPlain] = portalIssue($business);

    $this->get(route('access.show', $familyPlain))->assertRedirect();
    $this->get(route('access.show', $businessPlain))->assertRedirect(route('home'));

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Rahasia')
        ->assertSee('Usaha Rahasia')
        ->assertSee('Management Kebun')
        ->getContent();

    expect($html)
        ->not->toContain($familyPlain)
        ->not->toContain($businessPlain)
        ->not->toContain($familyToken->token_hash)
        ->not->toContain($businessToken->token_hash)
        ->not->toContain('testing-plantation-service-token')
        ->not->toContain((string) config('services.plantation.token'))
        ->not->toContain(PORTAL_PLANTATION_TOKEN)
        ->not->toContain(PORTAL_PLANTATION_URL);
});

it('builds a separate card for each authorized finance entity', function () {
    Http::preventStrayRequests();

    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Yusril']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Kedua']);
    $kebun = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun']);
    $toko = FinanceEntity::factory()->business()->create(['name' => 'Usaha Toko']);
    portalActivatePlantation($kebun);

    portalGrant($familyA);
    portalGrant($familyB);
    portalGrant($kebun);
    portalGrant($toko);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keluarga Yusril')
        ->assertSee('Keluarga Kedua')
        ->assertSee('Usaha Kebun')
        ->assertSee('Usaha Toko')
        ->assertSee('Management Kebun')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(5)
        ->and($html)->toContain('data-card-count="5"')
        ->and(substr_count($html, 'data-app-type="finance_personal"'))->toBe(2)
        ->and(substr_count($html, 'data-app-type="finance_business"'))->toBe(2)
        ->and(substr_count($html, 'data-app-type="plantation"'))->toBe(1)
        ->and($html)->not->toContain('Management Kebun — Usaha Toko');
});

it('issues a short-lived plantation handoff without storing the access url', function () {
    portalFakeHandoff();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun Handoff']);
    portalActivatePlantation($business);
    portalGrant($business);

    $this->from(route('home'))
        ->post(route('portal.plantation.handoff', $business))
        ->assertRedirect(PORTAL_PLANTATION_URL);

    expect(json_encode(session()->all()) ?: '')
        ->not->toContain(PORTAL_PLANTATION_TOKEN)
        ->not->toContain(PORTAL_PLANTATION_URL)
        ->not->toContain('testing-plantation-service-token');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        return $request->method() === 'POST'
            && str_ends_with($path, '/access-links')
            && is_string($request['label'] ?? null)
            && str_starts_with((string) $request['label'], 'Finance portal ')
            && is_string($request['expires_at'] ?? null)
            && ! str_contains((string) $request['label'], PORTAL_PLANTATION_TOKEN);
    });
});

it('rejects plantation handoff when integration is inactive', function () {
    Http::preventStrayRequests();

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Inactive Handoff']);
    portalActivatePlantation($business, PlantationIntegrationStatus::INACTIVE);
    portalGrant($business);

    $this->post(route('portal.plantation.handoff', $business))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('returns to the portal when plantation handoff cannot contact the service', function () {
    Http::fake([
        'http://plantation.test/*' => Http::response(['message' => 'Unavailable'], 503),
    ]);

    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Handoff Down']);
    portalActivatePlantation($business);
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Handoff Down']);
    portalGrant($family);
    portalGrant($business);

    $this->from(route('home'))
        ->post(route('portal.plantation.handoff', $business))
        ->assertRedirect(route('home'))
        ->assertSessionHas('danger');

    expect(json_encode(session()->all()) ?: '')
        ->not->toContain(PORTAL_PLANTATION_URL);
});

it('shows the public Arusku portal without a private capability', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('ARUSKU')
        ->assertSee('Portal Arusku')
        ->assertSee('Belum ada layanan yang dapat dibuka.')
        ->assertSee('Gunakan tautan akses yang diberikan administrator untuk membuka layanan Anda.')
        ->assertDontSee('Keuangan Kita')
        ->assertDontSee('Admin Login')
        ->assertDontSee('/access/{token}');
});

it('lets a single-destination session open a centered portal card', function () {
    Http::preventStrayRequests();

    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tunggal Portal']);
    portalGrant($entity);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Keuangan Keluarga')
        ->assertSee('Keluarga Tunggal Portal')
        ->assertSee('Sesi aktif')
        ->assertSee('css/portal.css')
        ->assertDontSee('css/entity.css')
        ->getContent();

    expect(substr_count($html, 'data-app-type="'))->toBe(1)
        ->and($html)->toContain('data-card-count="1"');
});

it('renders three clickable cards for family plus business with plantation', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tiga']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tiga']);
    portalActivatePlantation($business);
    portalGrant($family);
    portalGrant($business);

    $html = $this->get(route('home'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-card-count="3"')
        ->and(substr_count($html, 'data-app-type="'))->toBe(3)
        ->and($html)->toContain('data-app-type="finance_personal"')
        ->and($html)->toContain('data-app-type="finance_business"')
        ->and($html)->toContain('data-app-type="plantation"');
});

it('shows an empty state instead of inventing application cards', function () {
    $html = $this->withViewErrors([])->view('portal.index', [
        'title' => 'Portal Arusku',
        'destinations' => collect(),
        'accessName' => null,
    ]);

    $html->assertSee('Belum ada layanan yang dapat dibuka.')
        ->assertSee('Buka tautan akses yang diberikan administrator.')
        ->assertDontSee('data-app-type=')
        ->assertDontSee('data-card-count');
});
