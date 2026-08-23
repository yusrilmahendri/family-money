<?php

use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('retires the apps portal and does not set FinanceContext', function () {
    $this->get('/apps')
        ->assertRedirect(route('home'))
        ->assertSessionHas('danger');

    $this->post(route('apps.select'), [
        'context' => FinanceContext::PRIBADI,
    ])->assertRedirect(route('home'));

    expect(session(FinanceContext::SESSION_KEY))->toBeNull();
});

it('retires the legacy dashboard route', function () {
    $this->get(route('dashboard'))->assertRedirect(route('home'));
});
