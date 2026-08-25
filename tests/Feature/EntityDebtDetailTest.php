<?php

use App\Enums\FinanceAccountType;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\FinanceEntity;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantDebtAccess(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function debtAccount(FinanceEntity $entity, string $name = 'Kas Utama', float $opening = 100_000_000)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::BANK,
        'opening_balance' => $opening,
    ]);
}

it('shows 83.3 percent progress remaining and paid totals from remaining_balance', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = debtAccount($family, 'BCA');
    $debt = Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'KPR Rumah',
        'principal_total' => 60_000_000,
        'remaining_balance' => 10_000_000,
    ]);
    DebtPayment::query()->create([
        'debt_id' => $debt->id,
        'finance_account_id' => $account->id,
        'amount' => 50_000_000,
        'paid_on' => '2026-08-10',
    ]);
    grantDebtAccess($family);

    $this->get(route('entity.debts.show', [$family, $debt]))
        ->assertOk()
        ->assertSee('KPR Rumah')
        ->assertSee('Dalam proses')
        ->assertSee('83,3%')
        ->assertSee('width: 83.3%')
        ->assertSee('Rp 50.000.000')
        ->assertSee('Rp 60.000.000')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Total hutang')
        ->assertSee('Sudah dibayar')
        ->assertSee('Sisa')
        ->assertSee('Catat pembayaran')
        ->assertSee('10 Agt 2026')
        ->assertSee('BCA')
        ->assertDontSee('Lunas')
        ->assertDontSee('-Rp');
});

it('shows paid off status at 100 percent with zero remaining and hides the payment form', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = debtAccount($family);
    $debt = Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Cicilan Motor',
        'principal_total' => 20_000_000,
        'remaining_balance' => 0,
    ]);
    DebtPayment::query()->create([
        'debt_id' => $debt->id,
        'finance_account_id' => $account->id,
        'amount' => 20_000_000,
        'paid_on' => '2026-08-20',
    ]);
    grantDebtAccess($family);

    $this->get(route('entity.debts.show', [$family, $debt]))
        ->assertOk()
        ->assertSee('Lunas')
        ->assertSee('100%')
        ->assertSee('width: 100%')
        ->assertSee('Hutang ini sudah lunas.')
        ->assertSee('Rp 20.000.000')
        ->assertSee('20 Agt 2026')
        ->assertDontSee('Dalam proses')
        ->assertDontSee('Catat pembayaran')
        ->assertDontSee('-Rp');
});

it('rejects a payment that exceeds the remaining balance', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = debtAccount($family);
    $debt = Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Hutang Bank',
        'principal_total' => 60_000_000,
        'remaining_balance' => 10_000_000,
    ]);
    grantDebtAccess($family);

    $this->from(route('entity.debts.show', [$family, $debt]))
        ->post(route('entity.debts.payments.store', [$family, $debt]), [
            'amount' => '15000000',
            'paid_on' => now()->toDateString(),
            'finance_account_id' => $account->id,
        ])
        ->assertRedirect(route('entity.debts.show', [$family, $debt]))
        ->assertSessionHasErrors('amount');

    expect((float) $debt->fresh()->remaining_balance)->toBe(10_000_000.0)
        ->and(DebtPayment::query()->where('debt_id', $debt->id)->count())->toBe(0);
});

it('rejects a new payment on a paid off debt', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = debtAccount($family);
    $debt = Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Hutang Lunas',
        'principal_total' => 5_000_000,
        'remaining_balance' => 0,
    ]);
    grantDebtAccess($family);

    $this->from(route('entity.debts.show', [$family, $debt]))
        ->post(route('entity.debts.payments.store', [$family, $debt]), [
            'amount' => '100000',
            'paid_on' => now()->toDateString(),
            'finance_account_id' => $account->id,
        ])
        ->assertRedirect(route('entity.debts.show', [$family, $debt]))
        ->assertSessionHasErrors('amount');

    expect((float) $debt->fresh()->remaining_balance)->toBe(0.0)
        ->and(DebtPayment::query()->where('debt_id', $debt->id)->count())->toBe(0);
});

it('rejects show and payment from another finance entity', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga A']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga B']);
    $accountA = debtAccount($familyA, 'Kas A');
    $debtB = Debt::query()->create([
        'finance_entity_id' => $familyB->id,
        'title' => 'Hutang B',
        'principal_total' => 8_000_000,
        'remaining_balance' => 8_000_000,
    ]);
    grantDebtAccess($familyA);
    grantDebtAccess($familyB);

    $this->get(route('entity.debts.show', [$familyA, $debtB]))->assertNotFound();
    $this->post(route('entity.debts.payments.store', [$familyA, $debtB]), [
        'amount' => '100000',
        'paid_on' => now()->toDateString(),
        'finance_account_id' => $accountA->id,
    ])->assertNotFound();

    expect((float) $debtB->fresh()->remaining_balance)->toBe(8_000_000.0)
        ->and(DebtPayment::query()->where('debt_id', $debtB->id)->count())->toBe(0);
});
