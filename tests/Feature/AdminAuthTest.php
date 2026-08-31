<?php

use App\Models\FinanceEntity;
use App\Models\User;
use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceEntityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function adminAccount(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

function memberAccount(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

it('allows a guest to open the admin login page', function () {
    $html = $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('ARUSKU')
        ->assertSee('Masuk Admin')
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertSee('Masuk')
        ->getContent();

    expect($html)
        ->not->toContain('is_admin')
        ->not->toContain('token_hash')
        ->not->toContain((string) config('services.plantation.token'));
});

it('allows a valid admin to login', function () {
    $admin = adminAccount(['email' => 'admin@example.com']);

    $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard');
});

it('rejects an invalid password', function () {
    adminAccount(['email' => 'admin@example.com']);

    $this->from(route('admin.login'))
        ->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects a non-admin user from admin login', function () {
    memberAccount(['email' => 'member@example.com']);

    $this->from(route('admin.login'))
        ->post(route('admin.login.store'), [
            'email' => 'member@example.com',
            'password' => 'password',
        ])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects a guest from /admin to login', function () {
    $this->get('/admin')
        ->assertRedirect(route('admin.login'));
});

it('redirects a guest from admin finance entities to login', function () {
    $this->get('/admin/finance-entities')
        ->assertRedirect(route('admin.login'));
});

it('redirects a guest from portal access to login', function () {
    $this->get('/admin/portal-access')
        ->assertRedirect(route('admin.login'));
});

it('redirects a guest from the admin dashboard alias to login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));

    $this->get('/admin/dashboard')
        ->assertRedirect(route('admin.login'));
});

it('does not treat a private access session as admin', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Private']);
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);

    $this->get(route('access.show', $plain))->assertRedirect();

    expect(Auth::check())->toBeFalse()
        ->and(FinanceEntityAccess::isAuthorized($entity))->toBeTrue();

    $this->get('/admin')
        ->assertRedirect(route('admin.login'));
    $this->get('/admin/finance-entities')
        ->assertRedirect(route('admin.login'));
    $this->get('/admin/portal-access')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
    $this->get(route('entity.dashboard', $entity))->assertOk();
});

it('forbids an authenticated non-admin from the admin panel', function () {
    $member = memberAccount();

    $this->actingAs($member)
        ->get('/admin')
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('admin.finance-entities.index'))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('admin.portal-access.index'))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('admin.plantation-integrations.index'))
        ->assertForbidden();
});

it('allows an admin to open /admin and admin sections', function () {
    $this->actingAs(adminAccount())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard');

    $this->get(route('admin.finance-entities.index'))
        ->assertOk();

    $this->get(route('admin.plantation-integrations.index'))
        ->assertOk()
        ->assertSee('Management Kebun');

    $this->get(route('admin.portal-access.index'))
        ->assertOk()
        ->assertSee('Portal Access');
});

it('sends an authenticated admin from /admin/dashboard to /admin', function () {
    $this->actingAs(adminAccount())
        ->get('/admin/dashboard')
        ->assertRedirect('/admin');
});

it('logs the admin out and blocks /admin afterwards', function () {
    $admin = adminAccount();

    $this->actingAs($admin)
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();

    $this->get('/admin')
        ->assertRedirect(route('admin.login'));
    $this->get('/admin/finance-entities')
        ->assertRedirect(route('admin.login'));
});
