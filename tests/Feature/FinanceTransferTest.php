<?php

use App\Enums\FinanceAccountType;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use App\Models\Income;
use App\Models\Transaction;
use App\Services\FinanceAccountService;
use App\Services\FinanceTransferService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('transfers between accounts in the same entity without changing entity total income expense or profit', function () {
    $entity = FinanceEntity::factory()->business()->create(['name' => 'Usaha Transfer']);
    $source = cashAccount($entity, 'BRI Kebun', 1_000_000);
    $destination = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Kebun',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 200_000,
    ]);
    $category = Category::factory()->create([
        'finance_entity_id' => $entity->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    Income::query()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $source->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'Panen',
        'amount' => 500_000,
        'income_date' => now(),
    ]);
    $budget = Budget::query()->create([
        'finance_entity_id' => $entity->id,
        'category_id' => $category->id,
        'amount' => 1_000_000,
        'amount_saldo' => 0,
        'periode' => now(),
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budget->id,
        'finance_account_id' => $source->id,
        'name' => 'Pupuk',
        'amount' => 100_000,
        'activity_date' => now(),
    ]);

    $before = [
        'source' => balanceService()->balance($source->fresh()),
        'destination' => balanceService()->balance($destination->fresh()),
        'entity' => balanceService()->balanceForEntity($entity),
        'income' => (float) $entity->incomes()->sum('amount'),
        'expense' => (float) BudgetActivity::query()->where('budget_id', $budget->id)->sum('amount'),
    ];
    expect($before['source'])->toBe(1_400_000.0)
        ->and($before['destination'])->toBe(200_000.0)
        ->and($before['entity'])->toBe(1_600_000.0)
        ->and($before['income'])->toBe(500_000.0)
        ->and($before['expense'])->toBe(100_000.0);

    grantEntityAccess($entity);
    $this->post(route('entity.transfers.store', $entity), [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => '300000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Geser kas',
        'finance_entity_id' => $entity->id,
    ])->assertSessionHasErrors('finance_entity_id');

    $this->post(route('entity.transfers.store', $entity), [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => '300000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Geser kas',
    ])->assertRedirect(route('entity.transfers.index', $entity));

    expect(FinanceTransfer::query()->count())->toBe(1)
        ->and(balanceService()->balance($source->fresh()))->toBe(1_100_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(500_000.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe($before['entity'])
        ->and((float) $entity->incomes()->sum('amount'))->toBe($before['income'])
        ->and((float) BudgetActivity::query()->where('budget_id', $budget->id)->sum('amount'))->toBe($before['expense']);

    $this->get(route('entity.profit-loss.index', $entity))
        ->assertOk()
        ->assertSee('Laba: Rp 400.000');

    $this->get(route('entity.dashboard', $entity))
        ->assertOk()
        ->assertSee('Total Saldo')
        ->assertSee('Rp 1.600.000')
        ->assertSee('Pemasukan')
        ->assertSee('Rp 500.000')
        ->assertSee('Biaya operasional')
        ->assertSee('Rp 100.000');

    $this->get(route('entity.transfers.index', $entity))
        ->assertOk()
        ->assertSee('BRI Kebun')
        ->assertSee('Kas Kebun')
        ->assertSee('Geser kas')
        ->assertSee('Rp 300.000');
});

it('reduces FAMILY source and increases destination while keeping entity total', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'BCA Keluarga', 800_000);
    $destination = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Rumah',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 50_000,
    ]);
    grantEntityAccess($entity);

    $this->post(route('entity.transfers.store', $entity), [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => '250000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Tarik tunai',
    ])->assertRedirect();

    expect(balanceService()->balance($source->fresh()))->toBe(550_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(300_000.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe(850_000.0)
        ->and((float) Transaction::query()->where('finance_entity_id', $entity->id)->sum('amount'))->toBe(0.0)
        ->and((float) Income::query()->where('finance_entity_id', $entity->id)->sum('amount'))->toBe(0.0);
});

it('rejects same destination, cross-entity, inactive, overdrawn, and non-positive amounts', function () {
    [$entityA, $entityB] = familyPair();
    $source = cashAccount($entityA, 'Kas A', 100_000);
    $other = app(FinanceAccountService::class)->create($entityA, [
        'name' => 'BCA A',
        'type' => FinanceAccountType::BANK,
        'opening_balance' => 0,
    ]);
    $inactive = app(FinanceAccountService::class)->create($entityA, [
        'name' => 'Kas Lama A',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 50_000,
    ]);
    app(FinanceAccountService::class)->deactivate($inactive);
    $foreign = cashAccount($entityB, 'Kas B', 500_000);

    grantEntityAccess($entityA);

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $source->id,
        'destination_account_id' => $source->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $source->id,
        'destination_account_id' => $foreign->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $inactive->id,
        'destination_account_id' => $other->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('source_account_id');

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $source->id,
        'destination_account_id' => $inactive->id,
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $source->id,
        'destination_account_id' => $other->id,
        'amount' => '150000',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $source->id,
        'destination_account_id' => $other->id,
        'amount' => '0',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    expect(FinanceTransfer::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(100_000.0)
        ->and(balanceService()->balance($foreign->fresh()))->toBe(500_000.0);
});

it('keeps private transfer history isolated to the route entity', function () {
    [$entityA, $entityB] = familyPair();
    $sourceA = cashAccount($entityA, 'Kas A1', 400_000);
    $destA = app(FinanceAccountService::class)->create($entityA, [
        'name' => 'Kas A2',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    $sourceB = cashAccount($entityB, 'Kas B1', 400_000);
    $destB = app(FinanceAccountService::class)->create($entityB, [
        'name' => 'Kas B2',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);

    grantEntityAccess($entityA);
    grantEntityAccess($entityB);

    $this->post(route('entity.transfers.store', $entityA), [
        'source_account_id' => $sourceA->id,
        'destination_account_id' => $destA->id,
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Transfer A',
    ])->assertRedirect();

    $this->post(route('entity.transfers.store', $entityB), [
        'source_account_id' => $sourceB->id,
        'destination_account_id' => $destB->id,
        'amount' => '35000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Transfer B',
    ])->assertRedirect();

    $this->get(route('entity.transfers.index', $entityA))
        ->assertOk()
        ->assertSee('Transfer A')
        ->assertDontSee('Transfer B');

    $this->get(route('entity.transfers.index', $entityB))
        ->assertOk()
        ->assertSee('Transfer B')
        ->assertDontSee('Transfer A');

    expect(Route::has('entity.transfers.edit'))->toBeFalse()
        ->and(Route::has('entity.transfers.destroy'))->toBeFalse()
        ->and(Route::has('admin.finance-entities.transfers.edit'))->toBeFalse()
        ->and(Route::has('admin.finance-entities.transfers.destroy'))->toBeFalse();
});

it('rolls back a transfer when the wrapping transaction fails', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'Kas Atomic', 500_000);
    $destination = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Atomic 2',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);

    expect(function () use ($entity, $source, $destination): void {
        DB::transaction(function () use ($entity, $source, $destination): void {
            app(FinanceTransferService::class)->create($entity, [
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => 80_000,
                'transaction_date' => now()->toDateString(),
                'description' => 'Harus rollback',
            ]);

            throw new RuntimeException('force rollback');
        });
    })->toThrow(RuntimeException::class);

    expect(FinanceTransfer::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(500_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(0.0);
});

it('allows an admin to list and create a transfer with the same service', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Admin Transfer']);
    $source = cashAccount($entity, 'BCA Admin', 250_000);
    $destination = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Admin',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 10_000,
    ]);

    actingAdmin()
        ->get(route('admin.finance-entities.transfers.index', $entity))
        ->assertOk()
        ->assertSee('Transfer')
        ->assertSee('Admin Transfer');

    actingAdmin()
        ->post(route('admin.finance-entities.transfers.store', $entity), [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'amount' => '40000',
            'transaction_date' => now()->toDateString(),
            'description' => 'Admin geser',
        ])
        ->assertRedirect(route('admin.finance-entities.transfers.index', $entity));

    expect(balanceService()->balance($source->fresh()))->toBe(210_000.0)
        ->and(balanceService()->balance($destination->fresh()))->toBe(50_000.0)
        ->and(balanceService()->balanceForEntity($entity))->toBe(260_000.0);

    $this->post(route('admin.logout'));
    $this->get(route('admin.finance-entities.transfers.index', $entity))
        ->assertRedirect(route('admin.login'));
});

it('detects invalid transfers in the read-only account audit', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'Audit Sumber', 100_000);
    $destination = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Audit Tujuan',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);
    $other = FinanceEntity::factory()->family()->create();
    $foreign = cashAccount($other, 'Audit Asing', 50_000);

    $entity->transfers()->create([
        'source_account_id' => $source->id,
        'destination_account_id' => $foreign->id,
        'amount' => 10_000,
        'transaction_date' => now(),
        'description' => 'Cross entity',
    ]);

    DB::table('finance_transfers')->insert([
        'public_id' => (string) Str::ulid(),
        'finance_entity_id' => $entity->id,
        'source_account_id' => $source->id,
        'destination_account_id' => $source->id,
        'amount' => 5_000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Same account',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('finance_transfers')->insert([
        'public_id' => (string) Str::ulid(),
        'finance_entity_id' => $entity->id,
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 0,
        'transaction_date' => now()->toDateString(),
        'description' => 'Zero',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = FinanceTransfer::query()->count();

    $this->artisan('finance:account-audit')
        ->expectsOutputToContain('Finance Transfer Audit')
        ->expectsOutputToContain('Source/destination not the same entity')
        ->expectsOutputToContain('Source equals destination')
        ->expectsOutputToContain('Invalid account relation')
        ->expectsOutputToContain('Orphan transfers')
        ->assertFailed();

    $audit = app(FinanceTransferService::class)->audit();

    expect(FinanceTransfer::query()->count())->toBe($before)
        ->and($audit['cross_entity_accounts'])->toBeGreaterThan(0)
        ->and($audit['same_source_and_destination'])->toBeGreaterThan(0)
        ->and($audit['non_positive_amount'])->toBeGreaterThan(0)
        ->and($audit)->toHaveKeys(['orphan_transfers', 'invalid_account_relation']);
});

it('rejects a service-level transfer when the source balance is insufficient', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $source = cashAccount($entity, 'Kas Tipis', 20_000);
    $destination = app(FinanceAccountService::class)->create($entity, [
        'name' => 'Kas Kosong',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 0,
    ]);

    expect(fn () => app(FinanceTransferService::class)->create($entity, [
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => 20_000.01,
        'transaction_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(FinanceTransfer::query()->count())->toBe(0)
        ->and(balanceService()->balance($source->fresh()))->toBe(20_000.0);
});
