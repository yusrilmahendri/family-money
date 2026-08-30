<?php

use App\Enums\AuditAction;
use App\Enums\PlantationIntegrationStatus;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const PLANTATION_PLAIN_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PLANTATION_ACCESS_URL = 'http://plantation.test/access/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PLANTATION_PUBLIC_ID = '01PLANTATIONENTITYTEST00001';

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakePlantationSuccess(): void
{
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
        $method = $request->method();

        if ($method === 'POST' && $path === '/api/internal/plantation-entities') {
            return Http::response([
                'data' => [
                    'public_id' => PLANTATION_PUBLIC_ID,
                    'name' => $request['name'] ?? 'Kebun',
                    'finance_entity_public_id' => $request['finance_entity_public_id'] ?? null,
                ],
            ], 201);
        }

        if ($method === 'GET' && str_ends_with($path, '/access-links')) {
            return Http::response([
                'data' => [[
                    'id' => 9,
                    'label' => 'Mandor',
                    'is_active' => true,
                    'expires_at' => null,
                    'last_used_at' => null,
                    'created_at' => now()->toIso8601String(),
                ]],
            ]);
        }

        if ($method === 'POST' && preg_match('#/access-links$#', $path)) {
            return Http::response([
                'data' => [
                    'id' => 11,
                    'label' => $request['label'] ?? 'Mandor',
                    'token' => PLANTATION_PLAIN_TOKEN,
                    'access_url' => PLANTATION_ACCESS_URL,
                    'is_active' => true,
                ],
            ], 201);
        }

        if ($method === 'POST' && str_contains($path, '/regenerate')) {
            return Http::response([
                'data' => [
                    'id' => 12,
                    'token' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    'access_url' => 'http://plantation.test/access/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    'is_active' => true,
                ],
            ]);
        }

        return Http::response(['data' => ['ok' => true, 'is_active' => true]]);
    });
}

it('forbids a non-admin from opening Management Kebun', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('admin.plantation-integrations.index'))
        ->assertForbidden();
});

it('allows an admin to open the Management Kebun page', function () {
    FinanceEntity::factory()->business()->create(['name' => 'Usaha Kebun Test']);

    actingAdmin()
        ->get(route('admin.plantation-integrations.index'))
        ->assertOk()
        ->assertSee('Management Kebun')
        ->assertSee('Usaha Kebun Test');
});

it('does not allow a FAMILY entity to be integrated', function () {
    fakePlantationSuccess();
    $family = FinanceEntity::factory()->family()->create();

    actingAdmin()
        ->post(route('admin.plantation-integrations.activate', $family))
        ->assertRedirect(route('admin.plantation-integrations.index'))
        ->assertSessionHas('danger');

    expect(PlantationIntegration::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('allows a BUSINESS entity to activate plantation management', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Kebun Alpha']);

    actingAdmin()
        ->post(route('admin.plantation-integrations.activate', $business))
        ->assertRedirect(route('admin.plantation-integrations.show', $business))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('plantation_integrations', [
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE->value,
    ]);
});

it('creates a local mapping after a successful remote create', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    expect(PlantationIntegration::query()->where('finance_entity_id', $business->id)->exists())->toBeTrue();
});

it('does not create a mapping when the remote create fails', function () {
    Http::fake([
        'http://plantation.test/*' => Http::response(['message' => 'Unavailable'], 503),
    ]);

    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()
        ->post(route('admin.plantation-integrations.activate', $business))
        ->assertRedirect(route('admin.plantation-integrations.index'))
        ->assertSessionHas('danger');

    expect(PlantationIntegration::query()->count())->toBe(0);
});

it('rejects duplicate activation', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();

    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => '01ALREADYLINKED000000000000',
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.activate', $business))
        ->assertSessionHas('danger');

    expect(PlantationIntegration::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('calls the remote deactivate endpoint', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    $integration = PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.deactivate', $business))
        ->assertRedirect(route('admin.plantation-integrations.index'));

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'POST'
        && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/deactivate'));

    expect($integration->fresh()->status)->toBe(PlantationIntegrationStatus::INACTIVE);
});

it('can reactivate a deactivated integration', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    $integration = PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::INACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.reactivate', $business))
        ->assertSessionHas('success');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'POST'
        && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/activate')
        && ! str_contains($request->url(), 'access-links'));

    expect($integration->fresh()->status)->toBe(PlantationIntegrationStatus::ACTIVE);
});

it('syncs metadata to plantation', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create(['name' => 'Nama Sync']);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.sync', $business))
        ->assertSessionHas('success');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'PUT'
        && $request['name'] === 'Nama Sync');
});

it('lists access links from the remote api', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->get(route('admin.plantation-integrations.access-links.index', $business))
        ->assertOk()
        ->assertSee('Mandor')
        ->assertDontSee(PLANTATION_PLAIN_TOKEN);
});

it('shows plaintext access url once when issuing a link', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.access-links.store', $business), [
            'label' => 'Mandor',
        ])
        ->assertOk()
        ->assertSee(PLANTATION_ACCESS_URL)
        ->assertSee('hanya dapat dilihat sekarang');
});

it('does not persist plaintext plantation tokens', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()->post(route('admin.plantation-integrations.access-links.store', $business), [
        'label' => 'Mandor',
    ]);

    $dump = DB::table('plantation_integrations')->get()->toJson()
        .DB::table('audit_logs')->get()->toJson();

    expect($dump)->not->toContain(PLANTATION_PLAIN_TOKEN)
        ->and($dump)->not->toContain(PLANTATION_ACCESS_URL);
});

it('calls the remote revoke endpoint', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.access-links.revoke', [$business, 9]))
        ->assertRedirect(route('admin.plantation-integrations.access-links.index', $business));

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/access-links/9/revoke'));
});

it('shows a new link after regenerate', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->post(route('admin.plantation-integrations.access-links.regenerate', [$business, 9]))
        ->assertOk()
        ->assertSee('http://plantation.test/access/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
});

it('deletes an access link via the remote api', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();
    PlantationIntegration::query()->create([
        'finance_entity_id' => $business->id,
        'plantation_entity_public_id' => PLANTATION_PUBLIC_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    actingAdmin()
        ->delete(route('admin.plantation-integrations.access-links.destroy', [$business, 9]))
        ->assertRedirect(route('admin.plantation-integrations.access-links.index', $business));

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/access-links/9'));
});

it('keeps finance usable when plantation times out', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()
        ->get(route('admin.dashboard'))
        ->assertOk();

    actingAdmin()
        ->get(route('admin.plantation-integrations.index'))
        ->assertOk()
        ->assertSee('Management Kebun');

    actingAdmin()
        ->post(route('admin.plantation-integrations.activate', $business))
        ->assertSessionHas('danger');

    expect(PlantationIntegration::query()->count())->toBe(0);
});

it('sends the plantation bearer token', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->hasHeader('Authorization', 'Bearer testing-plantation-service-token')
        && $request->hasHeader('Accept', 'application/json'));
});

it('does not expose the service token in admin responses', function () {
    fakePlantationSuccess();
    FinanceEntity::factory()->business()->create(['name' => 'Usaha Token Check']);

    actingAdmin()
        ->get(route('admin.plantation-integrations.index'))
        ->assertOk()
        ->assertDontSee('testing-plantation-service-token');
});

it('only treats BUSINESS entities as valid plantation targets', function () {
    fakePlantationSuccess();
    $family = FinanceEntity::factory()->family()->create();
    $inactive = FinanceEntity::factory()->business()->create(['is_active' => false]);

    actingAdmin()->get(route('admin.plantation-integrations.index'))
        ->assertOk()
        ->assertDontSee($family->name);

    actingAdmin()->post(route('admin.plantation-integrations.activate', $inactive))
        ->assertSessionHas('danger');

    expect(PlantationIntegration::query()->count())->toBe(0);
});

it('records plantation integration activation in the audit log without secrets', function () {
    fakePlantationSuccess();
    $business = FinanceEntity::factory()->business()->create();

    actingAdmin()->post(route('admin.plantation-integrations.activate', $business));

    $this->assertDatabaseHas('audit_logs', [
        'action' => AuditAction::PLANTATION_INTEGRATION_ACTIVATED->value,
        'finance_entity_id' => $business->id,
    ]);

    $payload = DB::table('audit_logs')->latest('id')->first();
    expect($payload->new_values)->not->toContain('testing-plantation-service-token')
        ->and($payload->new_values)->not->toContain(PLANTATION_PLAIN_TOKEN);
});
