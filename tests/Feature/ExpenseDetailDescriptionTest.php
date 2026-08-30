<?php

use App\Enums\FinanceAccountType;
use App\Enums\IntegrationEventType;
use App\Enums\PlantationIntegrationStatus;
use App\Exports\EntityReportExport;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\Transaction;
use App\Services\EntityReportService;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Services\Insight\EntityInsightDataService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function expenseDetailAccount(FinanceEntity $entity, string $name = 'Kas Utama Keluarga', float $opening = 5_000_000)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => FinanceAccountType::CASH,
        'opening_balance' => $opening,
    ]);
}

it('creates an expense with detail_description', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity);
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '1000000',
        'transaction_date' => '2026-07-06',
        'description' => 'PENGELUARAN',
        'detail_description' => 'ipong bayar ke 3',
        'finance_account_id' => $account->id,
    ])->assertRedirect(route('entity.transactions.index', $entity));

    $this->assertDatabaseHas('transactions', [
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'description' => 'PENGELUARAN',
        'detail_description' => 'ipong bayar ke 3',
        'amount' => 1_000_000,
        'context' => FinanceContext::PRIBADI,
    ]);
});

it('creates an expense without detail_description', function () {
    $entity = FinanceEntity::factory()->family()->create();
    expenseDetailAccount($entity);
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '25000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Belanja tanpa detail',
    ])->assertRedirect(route('entity.transactions.index', $entity));

    $transaction = Transaction::query()->where('description', 'Belanja tanpa detail')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->detail_description)->toBeNull()
        ->and($transaction->resolvedDetailDescription())->toBeNull();
});

it('updates detail_description without changing amount or account', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity, 'Kas A', 2_000_000);
    grantEntityAccess($entity);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '150000',
        'transaction_date' => '2026-07-01',
        'description' => 'PENGELUARAN',
        'detail_description' => 'catatan lama',
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    $transaction = Transaction::query()->first();
    $balanceBefore = app(FinanceAccountBalanceService::class)->balance($account->fresh());

    $this->put(route('entity.transactions.update', [$entity, $transaction]), [
        'amount' => '150000',
        'transaction_date' => '2026-07-01',
        'description' => 'PENGELUARAN',
        'detail_description' => 'ipong bayar ke 3',
        'finance_account_id' => $account->id,
    ])->assertRedirect(route('entity.transactions.index', $entity));

    $transaction->refresh();
    expect($transaction->detail_description)->toBe('ipong bayar ke 3')
        ->and((float) $transaction->amount)->toBe(150_000.0)
        ->and((int) $transaction->finance_account_id)->toBe((int) $account->id)
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe($balanceBefore);
});

it('shows detail on the expense index and a dash when null', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity);
    grantEntityAccess($entity);

    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 1_000_000,
        'description' => 'PENGELUARAN',
        'detail_description' => 'ipong bayar ke 3',
        'transaction_date' => '2026-07-06',
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 50_000,
        'description' => 'TanpaDetailLama',
        'detail_description' => null,
        'keterangan_detail' => null,
        'transaction_date' => '2026-07-05',
    ]);

    $this->get(route('entity.transactions.index', $entity))
        ->assertOk()
        ->assertSee('Detail Pengeluaran')
        ->assertSee('PENGELUARAN')
        ->assertSee('ipong bayar ke 3')
        ->assertSee('TanpaDetailLama')
        ->assertSee('Kas Utama Keluarga')
        ->assertSee('—');
});

it('rejects detail_description longer than 2000 characters', function () {
    $entity = FinanceEntity::factory()->family()->create();
    expenseDetailAccount($entity);
    grantEntityAccess($entity);

    $this->from(route('entity.transactions.create', $entity))
        ->post(route('entity.transactions.store', $entity), [
            'amount' => '10000',
            'transaction_date' => now()->toDateString(),
            'description' => 'PENGELUARAN',
            'detail_description' => str_repeat('a', 2001),
        ])->assertSessionHasErrors('detail_description');

    expect(Transaction::query()->count())->toBe(0);
});

it('keeps expense detail isolated per finance entity', function () {
    [$entityA, $entityB] = familyPair();
    $accountA = expenseDetailAccount($entityA, 'Kas A');
    $accountB = expenseDetailAccount($entityB, 'Kas B');
    grantEntityAccess($entityA);
    grantEntityAccess($entityB);

    $this->post(route('entity.transactions.store', $entityA), [
        'amount' => '10000',
        'transaction_date' => now()->toDateString(),
        'description' => 'PENGELUARAN',
        'detail_description' => 'DetailMilikA',
        'finance_account_id' => $accountA->id,
    ])->assertRedirect();

    $foreign = Transaction::factory()->create([
        'finance_entity_id' => $entityB->id,
        'finance_account_id' => $accountB->id,
        'description' => 'Milik B',
        'detail_description' => 'DetailMilikB',
    ]);

    $this->get(route('entity.transactions.index', $entityA))
        ->assertOk()
        ->assertSee('DetailMilikA')
        ->assertDontSee('DetailMilikB');

    $this->get(route('entity.transactions.edit', [$entityA, $foreign]))->assertNotFound();
    $this->put(route('entity.transactions.update', [$entityA, $foreign]), [
        'amount' => '1000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Hacked',
        'detail_description' => 'HackedDetail',
    ])->assertNotFound();

    expect($foreign->fresh()->detail_description)->toBe('DetailMilikB');
});

it('includes detail_description in HTML Excel and PDF reports', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga DetailReport']);
    $account = expenseDetailAccount($entity, 'Kas Report');
    grantEntityAccess($entity);

    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 75_000,
        'description' => 'PENGELUARAN',
        'detail_description' => 'biaya perjalanan Jogja',
        'transaction_date' => now(),
    ]);

    $report = app(EntityReportService::class)->report($entity);
    $expenseMovement = collect($report['movements'])->firstWhere('type', 'Pengeluaran');

    expect($expenseMovement['description'])->toBe('PENGELUARAN')
        ->and($expenseMovement['detail_description'])->toBe('biaya perjalanan Jogja');

    $this->get(route('entity.reports.index', $entity))
        ->assertOk()
        ->assertSee('Detail Pengeluaran')
        ->assertSee('biaya perjalanan Jogja');

    $exportText = (new EntityReportExport($report))->plainText();
    expect($exportText)->toContain('biaya perjalanan Jogja');

    $excel = $this->get(route('entity.reports.excel', $entity))->assertOk();
    $sheet = downloadedSpreadsheetText($excel);
    expect($sheet)->toContain('biaya perjalanan Jogja')
        ->toContain('Detail Pengeluaran');

    $pdfHtml = view('entity.reports.pdf', [
        'entity' => $entity,
        'report' => $report,
    ])->render();
    expect($pdfHtml)->toContain('Detail Pengeluaran')
        ->toContain('biaya perjalanan Jogja');
});

it('finds expenses by searching detail_description', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity);
    grantEntityAccess($entity);

    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'description' => 'PENGELUARAN',
        'detail_description' => 'pengeluaran jogja',
        'amount' => 80_000,
        'transaction_date' => now(),
    ]);
    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'description' => 'PENGELUARAN',
        'detail_description' => 'ipong bayar ke 3',
        'amount' => 90_000,
        'transaction_date' => now(),
    ]);

    $this->get(route('entity.transactions.index', ['financeEntity' => $entity, 'q' => 'jogja']))
        ->assertOk()
        ->assertSee('pengeluaran jogja')
        ->assertDontSee('ipong bayar ke 3');
});

it('falls back to legacy keterangan_detail without inventing new copy from description', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity);
    grantEntityAccess($entity);

    $legacy = Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'description' => 'PENGELUARAN',
        'keterangan_detail' => 'dari kolom lama',
        'detail_description' => null,
        'amount' => 40_000,
        'transaction_date' => now(),
    ]);

    expect($legacy->resolvedDetailDescription())->toBe('dari kolom lama');

    $this->get(route('entity.transactions.index', $entity))
        ->assertOk()
        ->assertSee('dari kolom lama')
        ->assertSee('PENGELUARAN');
});

it('keeps plantation generated expenses valid and does not change amount', function () {
    $entity = FinanceEntity::factory()->business()->create();
    $account = expenseDetailAccount($entity, 'Kas Usaha', 1_000_000);
    PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => INTEGRATION_PLANTATION_ID,
        'status' => PlantationIntegrationStatus::ACTIVE,
    ]);

    $source = (string) Str::ulid();
    $this->postJson('/api/internal/plantation/events', integrationEnvelope(IntegrationEventType::PLANTATION_PURCHASE_POSTED, $source, [
        'purchase_public_id' => $source,
        'purchase_date' => now()->toDateString(),
        'amount' => '1500000.00',
        'description' => 'Pupuk',
        'supplier' => ['public_id' => '01SUP', 'name' => 'CV Tani'],
        'category' => 'FERTILIZER',
    ], $entity), integrationHeaders())->assertOk();

    $transaction = Transaction::query()->first();
    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(1_500_000.0)
        ->and($transaction->detail_description)->toBe('Pembelian Pupuk dari CV Tani')
        ->and($transaction->description)->toContain('Pembelian kebun')
        ->and(app(FinanceAccountBalanceService::class)->balance($account->fresh()))->toBe(-500_000.0);
});

it('does not change expense balance calculation when only detail is present', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity, 'Kas Hitung', 1_000_000);
    grantEntityAccess($entity);
    $balances = app(FinanceAccountBalanceService::class);

    expect($balances->balance($account))->toBe(1_000_000.0);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '200000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Dengan detail',
        'detail_description' => 'tidak boleh mengubah saldo',
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    expect($balances->balance($account->fresh()))->toBe(800_000.0);

    $this->post(route('entity.transactions.store', $entity), [
        'amount' => '200000',
        'transaction_date' => now()->toDateString(),
        'description' => 'Tanpa detail',
        'finance_account_id' => $account->id,
    ])->assertRedirect();

    expect($balances->balance($account->fresh()))->toBe(600_000.0)
        ->and((float) Transaction::query()->sum('amount'))->toBe(400_000.0);
});

it('includes detail_description in insight context without double-counting', function () {
    $entity = FinanceEntity::factory()->family()->create();
    $account = expenseDetailAccount($entity, 'Kas Insight', 500_000);

    Transaction::factory()->create([
        'finance_entity_id' => $entity->id,
        'finance_account_id' => $account->id,
        'amount' => 80_000,
        'description' => 'PENGELUARAN',
        'detail_description' => 'pengeluaran jogja',
        'transaction_date' => now(),
    ]);

    $context = app(EntityInsightDataService::class)->chatContext($entity, 'Analisis pengeluaran bulan ini');
    $blob = json_encode($context, JSON_UNESCAPED_UNICODE) ?: '';

    expect($blob)->toContain('pengeluaran jogja')
        ->toContain('Deskripsi: PENGELUARAN')
        ->and((float) $context['period_expense'])->toBe(80_000.0)
        ->and(collect($context['aktivitas_terbaru'])->where('type', 'Pengeluaran')->count())->toBe(1);
});
