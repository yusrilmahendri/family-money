<?php

use App\Enums\FinanceAccountType;
use App\Models\Category;
use App\Models\Debt;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rupiahFmtGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function rupiahFmtAccount(FinanceEntity $entity, string $name = 'Kas Utama', float $opening = 0): FinanceAccount
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::BANK,
        'opening_balance' => $opening,
    ]);
}

function rupiahFmtCategory(FinanceEntity $entity): Category
{
    return Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'name' => 'Gaji',
        'context' => FinanceContext::PRIBADI,
    ]);
}

it('stores a formatted rupiah income as a numeric database value', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = rupiahFmtAccount($family);
    $category = rupiahFmtCategory($family);
    rupiahFmtGrant($family);

    $this->post(route('entity.incomes.store', $family), [
        'source' => 'Gaji',
        'amount' => 'Rp 1.000.000',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect(route('entity.incomes.index', $family));

    $income = Income::query()->first();

    expect($income)->not->toBeNull()
        ->and((float) $income->amount)->toBe(1_000_000.0);

    $this->get(route('entity.incomes.index', $family))
        ->assertOk()
        ->assertSee('Rp 1.000.000')
        ->assertDontSee('1000000.00')
        ->assertDontSee('Rp1.000.000');
});

it('displays database amounts in rupiah on show edit and summary screens', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = rupiahFmtAccount($family, 'BCA', 0);
    $category = rupiahFmtCategory($family);
    rupiahFmtGrant($family);

    $this->get(route('entity.accounts.create', $family))
        ->assertOk()
        ->assertSee('value="Rp 0"', false);

    $this->get(route('entity.accounts.index', $family))
        ->assertOk()
        ->assertSee('Rp 0');

    $income = Income::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Bonus',
        'amount' => 22_500_000,
        'income_date' => now()->toDateString(),
    ]);

    $this->get(route('entity.incomes.edit', [$family, $income]))
        ->assertOk()
        ->assertSee('value="Rp 22.500.000"', false)
        ->assertSee('js-rupiah', false)
        ->assertDontSee('value="22500000.00"', false);

    Income::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'Proyek',
        'amount' => 200_000_000,
        'income_date' => now()->toDateString(),
    ]);

    $this->get(route('entity.incomes.index', $family))
        ->assertOk()
        ->assertSee('Rp 22.500.000')
        ->assertSee('Rp 200.000.000')
        ->assertDontSee('22500000.00')
        ->assertDontSee('200000000.00');
});

it('parses decimal-looking database strings without multiplying digits', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = rupiahFmtAccount($family);
    $category = rupiahFmtCategory($family);
    rupiahFmtGrant($family);

    $this->post(route('entity.incomes.store', $family), [
        'source' => 'Transfer gaji',
        'amount' => '22500000.00',
        'income_date' => now()->toDateString(),
        'category_id' => $category->id,
        'finance_account_id' => $account->id,
    ])->assertRedirect(route('entity.incomes.index', $family));

    expect((float) Income::query()->first()->amount)->toBe(22_500_000.0);
});

it('stores a formatted opening balance as a numeric value', function () {
    $family = FinanceEntity::factory()->family()->create();
    rupiahFmtGrant($family);

    $this->post(route('entity.accounts.store', $family), [
        'name' => 'BCA Utama',
        'type' => FinanceAccountType::BANK->value,
        'opening_balance' => 'Rp 1.000.000',
    ])->assertRedirect(route('entity.accounts.index', $family));

    $account = FinanceAccount::query()->where('name', 'BCA Utama')->first();

    expect($account)->not->toBeNull()
        ->and((float) $account->opening_balance)->toBe(1_000_000.0);

    $this->get(route('entity.accounts.edit', [$family, $account]))
        ->assertOk()
        ->assertSee('value="Rp 1.000.000"', false);
});

it('rejects manipulated money input without javascript formatting', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = rupiahFmtAccount($family);
    $category = rupiahFmtCategory($family);
    rupiahFmtGrant($family);

    $this->from(route('entity.incomes.create', $family))
        ->post(route('entity.incomes.store', $family), [
            'source' => 'Gaji',
            'amount' => 'bukan-angka',
            'income_date' => now()->toDateString(),
            'category_id' => $category->id,
            'finance_account_id' => $account->id,
        ])
        ->assertRedirect(route('entity.incomes.create', $family))
        ->assertSessionHasErrors('amount');

    $this->from(route('entity.incomes.create', $family))
        ->post(route('entity.incomes.store', $family), [
            'source' => 'Gaji',
            'amount' => 'Rp 0',
            'income_date' => now()->toDateString(),
            'category_id' => $category->id,
            'finance_account_id' => $account->id,
        ])
        ->assertRedirect(route('entity.incomes.create', $family))
        ->assertSessionHasErrors('amount');

    expect(Income::query()->count())->toBe(0);
});

it('includes formatted remaining balance in debt over-limit validation', function () {
    $family = FinanceEntity::factory()->family()->create();
    $account = rupiahFmtAccount($family, 'Kas', 100_000_000);
    $debt = Debt::query()->create([
        'finance_entity_id' => $family->id,
        'title' => 'Hutang Bank',
        'principal_total' => 60_000_000,
        'remaining_balance' => 10_000_000,
    ]);
    rupiahFmtGrant($family);

    $this->from(route('entity.debts.show', [$family, $debt]))
        ->post(route('entity.debts.payments.store', [$family, $debt]), [
            'amount' => '15000000',
            'paid_on' => now()->toDateString(),
            'finance_account_id' => $account->id,
        ])
        ->assertRedirect(route('entity.debts.show', [$family, $debt]))
        ->assertSessionHasErrors([
            'amount' => 'Jumlah pembayaran tidak boleh melebihi sisa hutang. Sisa hutang hanya Rp 10.000.000.',
        ]);
});
