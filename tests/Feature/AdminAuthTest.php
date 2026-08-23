<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin Login');
});

it('allows a valid admin to login', function () {
    $admin = adminAccount(['email' => 'admin@example.com']);

    $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
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

it('redirects a guest from the admin dashboard to login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('forbids an authenticated non-admin from the admin panel', function () {
    $member = memberAccount();

    $this->actingAs($member)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('admin.finance-entities.index'))
        ->assertForbidden();
});

it('allows an admin to open the admin dashboard', function () {
    $this->actingAs(adminAccount())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});

it('logs the admin out and ends the session', function () {
    $admin = adminAccount();

    $this->actingAs($admin)
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});
