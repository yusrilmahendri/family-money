<?php

use App\Models\FinanceEntity;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantDashboardAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function dashboardKpiMarkup(string $html): string
{
    preg_match('/<div class="entity-stat-grid">(.*?)<div class="entity-insight-preview"/s', $html, $matches);

    return $matches[1] ?? '';
}

it('keeps dashboard summary cards on a 4 / 2 / 1 column grid', function () {
    $css = file_get_contents(public_path('css/entity.css'));

    expect($css)->toMatch('/\.entity-stat-grid\s*\{[^}]*repeat\(4, minmax\(0, 1fr\)\)/s')
        ->and($css)->toMatch('/@media \(max-width: 1199\.98px\)\s*\{\s*\.entity-stat-grid\s*\{[^}]*repeat\(2, minmax\(0, 1fr\)\)/s')
        ->and($css)->toMatch('/@media \(max-width: 767\.98px\)[\s\S]*?\.entity-stat-grid\s*\{[^}]*minmax\(0, 1fr\)/s')
        ->and($css)->not->toMatch('/@media \(max-width: 1199\.98px\)\s*\{\s*\.entity-stat-grid\s*\{[^}]*repeat\(3,/s');
});

it('renders FAMILY and BUSINESS kpi cards as siblings in one auto-flow grid above insight', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Layout']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Layout']);
    grantDashboardAccess($family);
    grantDashboardAccess($business);

    $familyPage = $this->get(route('entity.dashboard', $family))->assertOk();
    $businessPage = $this->get(route('entity.dashboard', $business))->assertOk();
    $familyGrid = dashboardKpiMarkup($familyPage->getContent());
    $businessGrid = dashboardKpiMarkup($businessPage->getContent());

    expect($familyGrid)->not->toBe('')
        ->and($familyGrid)->not->toContain('col-lg-3')
        ->and($familyGrid)->not->toContain('col-sm-6')
        ->and($familyGrid)->not->toContain('class="row')
        ->and(substr_count($familyGrid, 'class="entity-stat"'))->toBe(10)
        ->and($businessGrid)->not->toContain('col-lg-3')
        ->and(substr_count($businessGrid, 'class="entity-stat"'))->toBe(16);

    $familyPage
        ->assertSeeInOrder([
            'entity-stat-grid',
            'Total Saldo',
            'Pemasukan',
            'Pengeluaran',
            'Pengeluaran Bulan Ini',
            'Insight Keuangan',
            'Arus Kas Bulan Ini',
        ])
        ->assertDontSee('col-lg-3');

    $businessPage
        ->assertSeeInOrder([
            'entity-stat-grid',
            'Total Saldo',
            'Biaya operasional',
            'Laba / Rugi',
            'Insight Keuangan',
        ])
        ->assertDontSee('col-lg-3');
});
