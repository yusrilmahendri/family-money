<?php

use App\Enums\FinanceAccountType;
use App\Models\FinanceEntity;
use App\Models\GoalContribution;
use App\Models\SavingsGoal;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantSavingsAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function savingsAccount(FinanceEntity $entity, string $name, float $opening = 100_000_000)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::BANK,
        'opening_balance' => $opening,
    ]);
}

it('shows 100 percent progress remaining zero and chronological cumulative history', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Tabungan']);
    $kasUtama = savingsAccount($family, 'Kas Utama');
    $bca = savingsAccount($family, 'BCA');
    $tabungan = savingsAccount($family, 'Tabungan');
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Bikin Rumah Akhir Tahun 2026',
        'target_amount' => 60_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $kasUtama->id,
        'amount' => 30_000_000,
        'contributed_on' => '2026-08-01',
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $bca->id,
        'amount' => 20_000_000,
        'contributed_on' => '2026-08-15',
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $tabungan->id,
        'amount' => 10_000_000,
        'contributed_on' => '2026-08-24',
    ]);
    grantSavingsAccess($family);

    $this->get(route('entity.savings-goals.show', [$family, $goal]))
        ->assertOk()
        ->assertSee('Bikin Rumah Akhir Tahun 2026')
        ->assertSee('Target tercapai')
        ->assertSee('100%')
        ->assertSee('width: 100%')
        ->assertSee('Rp 60.000.000')
        ->assertSee('3 transaksi')
        ->assertSee('Total Rp 60.000.000')
        ->assertSeeInOrder([
            '24 Agt 2026',
            'Tabungan',
            'Rp 10.000.000',
            'Rp 60.000.000',
            '15 Agt 2026',
            'BCA',
            'Rp 20.000.000',
            'Rp 50.000.000',
            '01 Agt 2026',
            'Kas Utama',
            'Rp 30.000.000',
        ])
        ->assertSee('entity-table--stackable')
        ->assertDontSee('-Rp');
});

it('shows 50 percent in progress and never a negative remaining', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = savingsAccount($family, 'BCA Keluarga');
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Liburan',
        'target_amount' => 60_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $account->id,
        'amount' => 30_000_000,
        'contributed_on' => '2026-08-10',
    ]);
    grantSavingsAccess($family);

    $this->get(route('entity.savings-goals.show', [$family, $goal]))
        ->assertOk()
        ->assertSee('Dalam proses')
        ->assertSee('50%')
        ->assertSee('width: 50%')
        ->assertSee('Rp 30.000.000')
        ->assertSee('Rp 60.000.000')
        ->assertDontSee('Target tercapai')
        ->assertDontSee('Melebihi target')
        ->assertDontSee('-Rp');
});

it('shows 110 percent with a capped bar zero remaining and excess copy', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = savingsAccount($family, 'Tabungan');
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Dana Darurat',
        'target_amount' => 60_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => $account->id,
        'amount' => 66_000_000,
        'contributed_on' => '2026-08-20',
    ]);
    grantSavingsAccess($family);

    $html = $this->get(route('entity.savings-goals.show', [$family, $goal]))
        ->assertOk()
        ->assertSee('Target tercapai')
        ->assertSee('110%')
        ->assertSee('Melebihi target Rp 6.000.000')
        ->assertSee('Rp 66.000.000')
        ->assertSee('width: 100%')
        ->getContent();

    expect($html)->not->toContain('width: 110%')
        ->and($html)->not->toContain('-Rp');
});

it('keeps contribution history isolated to the active family goal', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    $accountA = savingsAccount($familyA, 'Kas A');
    $accountB = savingsAccount($familyB, 'Kas B Rahasia');
    $goalA = SavingsGoal::query()->create([
        'finance_entity_id' => $familyA->id,
        'title' => 'Goal A',
        'target_amount' => 10_000_000,
    ]);
    $goalAOther = SavingsGoal::query()->create([
        'finance_entity_id' => $familyA->id,
        'title' => 'Goal A Lain',
        'target_amount' => 5_000_000,
    ]);
    $goalB = SavingsGoal::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'Goal B',
        'target_amount' => 8_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goalA->id,
        'finance_account_id' => $accountA->id,
        'amount' => 1_000_000,
        'contributed_on' => '2026-08-12',
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goalAOther->id,
        'finance_account_id' => $accountA->id,
        'amount' => 4_000_000,
        'contributed_on' => '2026-08-12',
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goalB->id,
        'finance_account_id' => $accountB->id,
        'amount' => 7_000_000,
        'contributed_on' => '2026-08-12',
    ]);
    grantSavingsAccess($familyA);
    grantSavingsAccess($familyB);

    $this->get(route('entity.savings-goals.show', [$familyA, $goalA]))
        ->assertOk()
        ->assertSee('Kas A')
        ->assertSee('Rp 1.000.000')
        ->assertDontSee('Kas B Rahasia')
        ->assertDontSee('Rp 7.000.000')
        ->assertDontSee('Rp 4.000.000');

    $this->get(route('entity.savings-goals.show', [$familyA, $goalB]))->assertNotFound();
    $this->get(route('entity.savings-goals.show', [$familyB, $goalA]))->assertNotFound();
});

it('shows an empty state then records a contribution and refreshes progress', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = savingsAccount($family, 'BCA Keluarga');
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Motor Baru',
        'target_amount' => 20_000_000,
    ]);
    grantSavingsAccess($family);

    $this->get(route('entity.savings-goals.show', [$family, $goal]))
        ->assertOk()
        ->assertSee('Belum ada setoran untuk target tabungan ini.')
        ->assertSee('Dalam proses')
        ->assertSee('0%')
        ->assertDontSee('Akumulasi');

    $this->post(route('entity.savings-goals.contributions.store', [$family, $goal]), [
        'amount' => 'Rp 5.000.000',
        'contributed_on' => '2026-08-24',
        'finance_account_id' => $account->id,
    ])->assertRedirect(route('entity.savings-goals.show', [$family, $goal]));

    $this->get(route('entity.savings-goals.show', [$family, $goal]))
        ->assertOk()
        ->assertSee('Setoran tabungan berhasil dicatat.')
        ->assertSee('BCA Keluarga')
        ->assertSee('Rp 5.000.000')
        ->assertSee('25%')
        ->assertSee('1 transaksi')
        ->assertSee('Akumulasi')
        ->assertDontSee('Belum ada setoran untuk target tabungan ini.');
});

it('falls back when the source account is missing', function () {
    $family = FinanceEntity::factory()->family()->create();
    $goal = SavingsGoal::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Goal Tanpa Rekening',
        'target_amount' => 2_000_000,
    ]);
    GoalContribution::query()->create([
        'savings_goal_id' => $goal->id,
        'finance_account_id' => null,
        'amount' => 500_000,
        'contributed_on' => '2026-08-08',
    ]);
    grantSavingsAccess($family);

    $this->get(route('entity.savings-goals.show', [$family, $goal]))
        ->assertOk()
        ->assertSee('Rekening tidak tersedia');
});
