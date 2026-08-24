<?php

use App\Models\FinanceEntity;
use App\Models\User;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantFormLayoutAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

it('renders FAMILY and BUSINESS create forms inside the responsive entity card', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();
    grantFormLayoutAccess($family);
    grantFormLayoutAccess($business);

    $familyPages = [
        route('entity.accounts.create', $family),
        route('entity.transfers.create', $family),
        route('entity.incomes.create', $family),
        route('entity.transactions.create', $family),
        route('entity.debts.create', $family),
        route('entity.savings-goals.create', $family),
        route('entity.receivables.create', $family),
        route('entity.categories.create', $family),
        route('entity.recurring.create', $family),
    ];

    foreach ($familyPages as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('entity-page-card', false)
            ->assertSee('form-control', false)
            ->assertSee('entity-form-actions', false)
            ->assertDontSee('min-width: 400px')
            ->assertDontSee('width: 500px');
    }

    $this->get(route('entity.budgets.create', $business))
        ->assertOk()
        ->assertSee('form-control', false);
    $this->get(route('entity.operational.create', $business))
        ->assertOk()
        ->assertSee('form-control', false);
});

it('keeps admin finance forms full-width capable via admin-form', function () {
    $admin = User::factory()->admin()->create();
    $entity = FinanceEntity::factory()->family()->create();

    $this->actingAs($admin)
        ->get(route('admin.finance-entities.create'))
        ->assertOk()
        ->assertSee('admin-form', false)
        ->assertSee('admin-form-actions', false)
        ->assertSee('form-control', false);

    $this->actingAs($admin)
        ->get(route('admin.finance-entities.accounts.create', $entity))
        ->assertOk()
        ->assertSee('admin-form', false);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('table-responsive', false)
        ->assertSee('form-inline', false);
});
