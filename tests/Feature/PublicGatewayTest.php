<?php

use App\Enums\PlantationIntegrationStatus;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\User;
use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceContext;
use App\Support\FinanceEntityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function gatewayIssue(FinanceEntity $entity): array
{
    return app(FinanceEntityAccessTokenService::class)->issue($entity);
}

it('returns 200 on the public root portal without a private capability', function () {
    $html = $this->get('/')
        ->assertOk()
        ->assertSee('ARUSKU')
        ->assertSee('Portal Arusku')
        ->assertSee('Pilih layanan yang ingin Anda buka.')
        ->assertSee('Belum ada layanan yang dapat dibuka.')
        ->getContent();

    expect($html)
        ->not->toContain('Keuangan Kita')
        ->not->toContain('Admin Login')
        ->not->toContain('/apps')
        ->not->toContain('/access/{token}')
        ->not->toContain('data-app-type="')
        ->toContain('Arusku · Created by @Yusril Mahendri')
        ->and(substr_count($html, 'class="portal-admin-link"'))->toBe(1)
        ->and($html)->toContain('href="'.url('/admin').'"');
});

it('does not expose private entities on the root portal without authorization', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Rahasia Root']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Rahasia Root']);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => '01GATEWAYPLANTATIONENTITY01',
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    $html = $this->get('/')
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('Keluarga Rahasia Root')
        ->not->toContain('Usaha Rahasia Root')
        ->not->toContain('Management Kebun')
        ->not->toContain($family->public_id)
        ->not->toContain($business->public_id)
        ->not->toContain((string) config('services.plantation.token'));
});

it('stores a live capability after a valid private access token', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Gateway']);
    [, $plain] = gatewayIssue($entity);

    $this->get(route('access.show', $plain))
        ->assertRedirect(route('entity.dashboard', $entity));

    expect(FinanceEntityAccess::hasCapability($entity))->toBeTrue()
        ->and(FinanceEntityAccess::isAuthorized($entity))->toBeTrue();
});

it('sends multiple destinations to the root portal with the authorized cards only', function () {
    Http::preventStrayRequests();

    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Gateway Dua']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Gateway Dua']);
    $foreign = FinanceEntity::factory()->business()->create(['name' => 'Usaha Tidak Diizinkan']);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $foreign->id,
        'plantation_entity_public_id' => '01GATEWAYPLANTATIONFOREIGN1',
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);
    [, $familyPlain] = gatewayIssue($family);
    [, $businessPlain] = gatewayIssue($business);

    $this->get(route('access.show', $familyPlain))->assertRedirect();
    $this->get(route('access.show', $businessPlain))
        ->assertRedirect(route('home'));

    $html = $this->get('/')
        ->assertOk()
        ->assertSee('Keluarga Gateway Dua')
        ->assertSee('Usaha Gateway Dua')
        ->assertDontSee('Usaha Tidak Diizinkan')
        ->assertDontSee('Management Kebun')
        ->getContent();

    expect($html)->toContain('data-card-count="2"')
        ->and(substr_count($html, 'data-app-type="'))->toBe(2);
});

it('keeps /admin on the existing admin flow', function () {
    $this->get('/admin')->assertRedirect(route('admin.login'));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});

it('does not keep /apps as a second portal', function () {
    $this->get('/apps')
        ->assertRedirect(route('home'))
        ->assertSessionMissing('danger');

    $this->post(route('apps.select'), [
        'context' => FinanceContext::PRIBADI,
    ])->assertRedirect(route('home'));

    $this->get('/portal')->assertRedirect('/');

    expect(session(FinanceContext::SESSION_KEY))->toBeNull();
});

it('shows a plantation card only for an authorized BUSINESS with ACTIVE integration', function () {
    Http::preventStrayRequests();

    $active = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun Aktif']);
    $inactive = FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun Nonaktif']);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $active->id,
        'plantation_entity_public_id' => '01GATEWAYPLANTATIONACTIVE01',
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $inactive->id,
        'plantation_entity_public_id' => '01GATEWAYPLANTATIONINACTIVE',
        'status' => PlantationIntegrationStatus::INACTIVE,
    ]);
    [, $activePlain] = gatewayIssue($active);
    [, $inactivePlain] = gatewayIssue($inactive);

    $this->get(route('access.show', $activePlain))->assertRedirect(route('home'));
    $this->get(route('access.show', $inactivePlain))->assertRedirect(route('home'));

    $html = $this->get('/')
        ->assertOk()
        ->assertSee('Usaha Kebun Aktif')
        ->assertSee('Usaha Kebun Nonaktif')
        ->getContent();

    expect(substr_count($html, 'data-app-type="plantation"'))->toBe(1)
        ->and($html)->toContain('Management Kebun')
        ->and($html)->toContain('Buka Management Kebun untuk Usaha Kebun Aktif')
        ->and($html)->not->toContain('Buka Management Kebun untuk Usaha Kebun Nonaktif')
        ->and($html)->not->toContain((string) config('services.plantation.token'));
});
