<?php

use App\Enums\AuditAction;
use App\Enums\FinanceAccountType;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Models\Income;
use App\Models\Transaction;
use App\Services\AiService;
use App\Services\BusinessProfitService;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\Insight\EntityAiChatService;
use App\Services\Insight\EntityInsightDataService;
use App\Support\FinanceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function chatGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

function chatCash(FinanceEntity $entity, string $name, float $opening = 0, ?string $number = null)
{
    return app(FinanceAccountService::class)->create($entity, [
        'name' => $name,
        'type' => $number ? FinanceAccountType::BANK : FinanceAccountType::CASH,
        'opening_balance' => $opening,
        'account_number' => $number,
    ]);
}

function chatInsight(): EntityInsightDataService
{
    return app(EntityInsightDataService::class);
}

it('builds FAMILY AI context only from the route family entity', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga ChatA']);
    $other = FinanceEntity::factory()->family()->create(['name' => 'Keluarga ChatB']);
    $account = chatCash($family, 'BCA ChatA', 200_000, '1111222233334444');
    $otherAccount = chatCash($other, 'Kas ChatB', 9_000_000);
    Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => Category::factory()->create(['finance_entity_id' => $family->id])->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 25_000,
        'transaction_date' => now(),
        'description' => 'BelanjaChatA',
    ]);
    Income::query()->create([
        'finance_entity_id' => $other->id,
        'finance_account_id' => $otherAccount->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $other->id,
            'context' => FinanceContext::PRIBADI,
        ])->id,
        'context' => FinanceContext::PRIBADI,
        'source' => 'GajiChatB',
        'amount' => 777_000,
        'income_date' => now(),
    ]);

    $payload = chatInsight()->payload($family);
    $context = chatInsight()->chatContext($family, 'Berapa saldo yang tersedia?');
    $blob = json_encode([$payload, $context]) ?: '';

    expect($payload['balance_summary']['total'])
        ->toBe(app(FinanceAccountBalanceService::class)->balanceForEntity($family))
        ->and($blob)->toContain('Keluarga ChatA')
        ->toContain('BelanjaChatA')
        ->not->toContain('Keluarga ChatB')
        ->not->toContain('GajiChatB')
        ->not->toContain('1111222233334444')
        ->not->toContain('password')
        ->not->toContain('token_hash')
        ->and(chatInsight()->containsSensitiveValue($context))->toBeFalse();
});

it('builds BUSINESS AI context from profit service not a new formula', function () {
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha ChatA']);
    $other = FinanceEntity::factory()->business()->create(['name' => 'Usaha ChatB']);
    $account = chatCash($business, 'Kas Usaha ChatA', 0);
    $category = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    Income::query()->create([
        'finance_entity_id' => $business->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'PanenChatA',
        'amount' => 400_000,
        'income_date' => now(),
    ]);
    Income::query()->create([
        'finance_entity_id' => $other->id,
        'finance_account_id' => chatCash($other, 'Kas Usaha ChatB', 0)->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $other->id,
            'context' => FinanceContext::USAHA_KEBUN,
        ])->id,
        'context' => FinanceContext::USAHA_KEBUN,
        'source' => 'PanenChatB',
        'amount' => 888_000,
        'income_date' => now(),
    ]);

    $payload = chatInsight()->payload($business);
    $profit = app(BusinessProfitService::class)->calculate($business);

    expect($payload['profit']['profit'])->toBe($profit['profit'])
        ->and(json_encode($payload))->toContain('Usaha ChatA')
        ->not->toContain('Usaha ChatB')
        ->not->toContain('PanenChatB');
});

it('rejects the entity chat endpoint without capability', function () {
    $entity = FinanceEntity::factory()->family()->create();

    $this->postJson(route('entity.ai.chat', $entity), [
        'message' => 'Berapa saldo?',
    ])->assertNotFound();
});

it('does not let a forged finance_entity_id change AI ownership', function () {
    $familyA = FinanceEntity::factory()->family()->create(['name' => 'Keluarga OwnerA']);
    $familyB = FinanceEntity::factory()->family()->create(['name' => 'Keluarga OwnerB']);
    chatCash($familyA, 'Kas OwnerA', 50_000);
    chatCash($familyB, 'Kas OwnerB', 9_999_000);
    chatGrant($familyA);

    $ai = Mockery::mock(AiService::class);
    $seen = null;
    $ai->shouldReceive('isConfigured')->andReturn(true);
    $ai->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$seen) {
        $seen = json_encode($messages) ?: '';

        return ['ok' => true, 'text' => 'Saldo entity ini tersedia.'];
    });
    app()->instance(AiService::class, $ai);
    app()->forgetInstance(EntityAiChatService::class);

    $this->postJson(route('entity.ai.chat', $familyA), [
        'message' => 'Berapa saldo yang tersedia?',
        'finance_entity_id' => $familyB->id,
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.entity', 'Keluarga OwnerA')
        ->assertJsonPath('message', 'Saldo entity ini tersedia.');

    expect($seen)->toBeString()
        ->toContain('Keluarga OwnerA')
        ->toContain('Kas OwnerA')
        ->not->toContain('Keluarga OwnerB')
        ->not->toContain('Kas OwnerB')
        ->not->toContain('token_hash')
        ->not->toContain('"password"');
});

it('shows different suggested questions for FAMILY and BUSINESS', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Prompt']);
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha Prompt']);
    chatGrant($family);
    chatGrant($business);

    $this->get(route('entity.insight.index', $family))
        ->assertOk()
        ->assertSee('Insight AI')
        ->assertSee('Asisten Keuangan Keluarga')
        ->assertSee('Analisis pengeluaran bulan ini')
        ->assertSee('Bagaimana kondisi hutang dan piutang?')
        ->assertDontSee('Berapa laba bulan ini?');

    $this->get(route('entity.insight.index', $business))
        ->assertOk()
        ->assertSee('Asisten Keuangan Usaha')
        ->assertSee('Berapa laba bulan ini?')
        ->assertSee('Bagaimana kondisi modal dan prive?')
        ->assertDontSee('Bagaimana kondisi hutang dan piutang?');
});

it('returns a safe message when the AI provider fails', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Error']);
    chatCash($entity, 'Kas Error', 10_000);
    chatGrant($entity);

    $this->mock(AiService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('chat')->once()->andReturn([
            'ok' => false,
            'error' => 'SQLSTATE secret stack',
        ]);
    });

    $response = $this->postJson(route('entity.ai.chat', $entity), [
        'message' => 'Bagaimana kondisi saldo saya?',
    ])->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', EntityAiChatService::SAFE_ERROR);

    expect($response->getContent())->not->toContain('SQLSTATE')
        ->not->toContain('secret stack');
});

it('records a light AI_CHAT_REQUESTED audit without the user prompt', function () {
    $entity = FinanceEntity::factory()->family()->create(['name' => 'Keluarga AuditChat']);
    chatGrant($entity);

    $this->mock(AiService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('chat')->once()->andReturn(['ok' => true, 'text' => 'Baik.']);
    });

    $this->postJson(route('entity.ai.chat', $entity), [
        'message' => 'Rahasia pribadi jangan disimpan',
    ])->assertOk();

    $log = AuditLog::query()->where('action', AuditAction::AI_CHAT_REQUESTED)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and(json_encode($log->new_values) ?: '')->not->toContain('Rahasia pribadi')
        ->and($log->new_values)->toHaveKey('period');
});

it('keeps FAMILY and BUSINESS chat history scoped by public_id', function () {
    $family = FinanceEntity::factory()->family()->create();
    $business = FinanceEntity::factory()->business()->create();

    expect(EntityAiChatService::SESSION_PREFIX.$family->public_id)
        ->not->toBe(EntityAiChatService::SESSION_PREFIX.$business->public_id);
});

it('resolves the asked financial period from the user message', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-23 10:00:00', config('app.timezone')));

    $month = chatInsight()->resolvePeriod('Berapa pengeluaran terbesar bulan ini?');
    $last = chatInsight()->resolvePeriod('Bagaimana kondisi bulan lalu?');
    $year = chatInsight()->resolvePeriod('Berapa laba tahun ini?');
    $named = chatInsight()->resolvePeriod('Bagaimana Agustus 2026?');
    $default = chatInsight()->resolvePeriod('Berapa saldo yang tersedia?');

    expect($month['from'])->toBe('2026-08-01')
        ->and($month['to'])->toBe('2026-08-31')
        ->and($last['from'])->toBe('2026-07-01')
        ->and($year['from'])->toBe('2026-01-01')
        ->and($named['from'])->toBe('2026-08-01')
        ->and($named['to'])->toBe('2026-08-31')
        ->and($default['from'])->toBe('2026-08-01');
});

it('builds FAMILY category breakdown from the route entity transactions only', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-23 10:00:00', config('app.timezone')));
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga KategoriA']);
    $other = FinanceEntity::factory()->family()->create(['name' => 'Keluarga KategoriB']);
    $account = chatCash($family, 'Kas KategoriA', 1_000_000);
    $otherAccount = chatCash($other, 'Kas KategoriB', 1_000_000);
    $makanan = Category::factory()->create(['finance_entity_id' => $family->id, 'name' => 'MakananBoros']);
    $transport = Category::factory()->create(['finance_entity_id' => $family->id, 'name' => 'TransportKecil']);
    $rahasia = Category::factory()->create(['finance_entity_id' => $other->id, 'name' => 'RahasiaBorosB']);

    Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $makanan->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 250_000,
        'transaction_date' => '2026-08-10',
        'description' => 'MakanA',
    ]);
    Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $transport->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 40_000,
        'transaction_date' => '2026-08-11',
        'description' => 'BensinA',
    ]);
    Transaction::query()->create([
        'finance_entity_id' => $other->id,
        'finance_account_id' => $otherAccount->id,
        'category_id' => $rahasia->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 9_000_000,
        'transaction_date' => '2026-08-12',
        'description' => 'BelanjaB',
    ]);

    $context = chatInsight()->chatContext($family, 'Kategori apa yang paling boros?');

    expect($context['kategori'][0]['name'])->toBe('MakananBoros')
        ->and($context['kategori'][0]['total'])->toBe(250_000.0)
        ->and($context['facts_relevant_to_question']['top_category']['name'])->toBe('MakananBoros')
        ->and(json_encode($context))->not->toContain('RahasiaBorosB')
        ->not->toContain('Keluarga KategoriB');
});

it('builds BUSINESS category breakdown from operational activities of the route entity', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-23 10:00:00', config('app.timezone')));
    $business = FinanceEntity::factory()->business()->create(['name' => 'Usaha KategoriA']);
    $other = FinanceEntity::factory()->business()->create(['name' => 'Usaha KategoriB']);
    $account = chatCash($business, 'Kas Usaha KategoriA', 0);
    $pupuk = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'name' => 'PupukBoros',
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $upah = Category::factory()->create([
        'finance_entity_id' => $business->id,
        'name' => 'UpahKecil',
        'context' => FinanceContext::USAHA_KEBUN,
    ]);
    $budgetPupuk = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $pupuk->id,
        'amount' => 500_000,
        'amount_saldo' => 0,
        'periode' => '2026-08-01',
    ]);
    $budgetUpah = Budget::query()->create([
        'finance_entity_id' => $business->id,
        'category_id' => $upah->id,
        'amount' => 200_000,
        'amount_saldo' => 0,
        'periode' => '2026-08-01',
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budgetPupuk->id,
        'finance_account_id' => $account->id,
        'name' => 'Beli pupuk',
        'amount' => 180_000,
        'activity_date' => '2026-08-08',
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $budgetUpah->id,
        'finance_account_id' => $account->id,
        'name' => 'Upah harian',
        'amount' => 70_000,
        'activity_date' => '2026-08-09',
    ]);
    $otherBudget = Budget::query()->create([
        'finance_entity_id' => $other->id,
        'category_id' => Category::factory()->create([
            'finance_entity_id' => $other->id,
            'name' => 'RahasiaOpexB',
            'context' => FinanceContext::USAHA_KEBUN,
        ])->id,
        'amount' => 1,
        'amount_saldo' => 0,
        'periode' => '2026-08-01',
    ]);
    BudgetActivity::query()->create([
        'budget_id' => $otherBudget->id,
        'finance_account_id' => chatCash($other, 'Kas Usaha KategoriB', 0)->id,
        'name' => 'OpexB',
        'amount' => 777_000,
        'activity_date' => '2026-08-08',
    ]);

    $context = chatInsight()->chatContext($business, 'Biaya operasional terbesar apa?');

    expect($context['kategori'][0]['name'])->toBe('PupukBoros')
        ->and($context['kategori'][0]['total'])->toBe(180_000.0)
        ->and($context['facts_relevant_to_question']['top_category']['name'])->toBe('PupukBoros')
        ->and($context['facts_relevant_to_question'])->toHaveKey('period_operational_expense')
        ->and(json_encode($context))->not->toContain('RahasiaOpexB')
        ->not->toContain('Usaha KategoriB');
});

it('sends top category evidence for the most expensive category question', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-23 10:00:00', config('app.timezone')));
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Evidence']);
    $account = chatCash($family, 'Kas Evidence', 500_000);
    $category = Category::factory()->create(['finance_entity_id' => $family->id, 'name' => 'BelanjaTerbesar']);
    Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $category->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 88_000,
        'transaction_date' => '2026-08-15',
        'description' => 'BelanjaEvidence',
    ]);
    chatGrant($family);

    $seen = null;
    $ai = Mockery::mock(AiService::class);
    $ai->shouldReceive('isConfigured')->andReturn(true);
    $ai->shouldReceive('chat')->once()->andReturnUsing(function (array $messages, array $options) use (&$seen) {
        $seen = json_encode([$messages, $options]) ?: '';

        return ['ok' => true, 'text' => 'BelanjaTerbesar adalah kategori paling boros.', 'truncated' => false];
    });
    app()->instance(AiService::class, $ai);
    app()->forgetInstance(EntityAiChatService::class);

    $this->postJson(route('entity.ai.chat', $family), [
        'message' => 'Kategori apa yang paling boros?',
    ])->assertOk()->assertJsonPath('success', true);

    expect($seen)->toBeString()
        ->toContain('BelanjaTerbesar')
        ->toContain('facts_relevant_to_question')
        ->toContain('top_category')
        ->toContain('"max_tokens":'.EntityAiChatService::OUTPUT_TOKENS)
        ->not->toContain('token_hash')
        ->not->toContain('"password"');
});

it('does not invent a house cost when planning has no target amount', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Rumah']);
    chatCash($family, 'Kas Rumah', 100_000);

    $context = chatInsight()->chatContext($family, 'Jika saya ingin membangun rumah, perlu dana berapa tabungannya?');
    $prompt = app(EntityAiChatService::class)->systemPrompt($family, $context);
    $blob = json_encode($context).$prompt;

    expect($context['facts_relevant_to_question']['planning']['user_provided_target_amount'])->toBeFalse()
        ->and($blob)->toContain('Jangan mengarang nominal biaya')
        ->toContain('Fakta dari data')
        ->toContain('Simulasi/Asumsi')
        ->not->toContain('500000000')
        ->not->toContain('harga rumah');
});

it('answers month questions from period fields rather than lifetime totals', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-23 10:00:00', config('app.timezone')));
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Periode']);
    $account = chatCash($family, 'Kas Periode', 2_000_000);
    $lalu = Category::factory()->create(['finance_entity_id' => $family->id, 'name' => 'BelanjaJuli']);
    $kini = Category::factory()->create(['finance_entity_id' => $family->id, 'name' => 'BelanjaAgustus']);
    Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $lalu->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 400_000,
        'transaction_date' => '2026-07-12',
        'description' => 'JuliBesar',
    ]);
    Transaction::query()->create([
        'finance_entity_id' => $family->id,
        'finance_account_id' => $account->id,
        'category_id' => $kini->id,
        'context' => FinanceContext::PRIBADI,
        'amount' => 55_000,
        'transaction_date' => '2026-08-12',
        'description' => 'AgustusKecil',
    ]);

    $context = chatInsight()->chatContext($family, 'Berapa pengeluaran bulan ini?');

    expect($context['period_expense'])->toBe(55_000.0)
        ->and($context['lifetime_expense'])->toBe(455_000.0)
        ->and($context['kategori'][0]['name'])->toBe('BelanjaAgustus')
        ->and($context['facts_relevant_to_question']['period_expense'])->toBe(55_000.0)
        ->and($context['facts_relevant_to_question']['use_period_fields_first'])->toBeTrue()
        ->and($context['data_priority']['primary'])->toBe('period');
});

it('sends the current user message only once even if history already contains it', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga History']);
    chatCash($family, 'Kas History', 10_000);
    chatGrant($family);
    $question = 'Berapa saldo yang tersedia?';
    $roles = [];

    $ai = Mockery::mock(AiService::class);
    $ai->shouldReceive('isConfigured')->andReturn(true);
    $ai->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$roles) {
        $roles = array_map(fn (array $turn) => ($turn['role'] ?? '').':'.($turn['content'] ?? ''), $messages);

        return ['ok' => true, 'text' => 'Saldo tersedia.', 'truncated' => false];
    });
    app()->instance(AiService::class, $ai);
    app()->forgetInstance(EntityAiChatService::class);

    $this->postJson(route('entity.ai.chat', $family), [
        'message' => $question,
        'history' => [
            ['role' => 'user', 'content' => 'Pertanyaan lama'],
            ['role' => 'assistant', 'content' => 'Jawaban lama'],
            ['role' => 'user', 'content' => $question],
        ],
    ])->assertOk()->assertJsonPath('success', true);

    $userTurns = array_values(array_filter($roles, fn (string $turn) => str_starts_with($turn, 'user:'.$question)));

    expect($userTurns)->toHaveCount(1)
        ->and(implode("\n", $roles))->toContain('user:Pertanyaan lama')
        ->toContain('assistant:Jawaban lama');
});

it('stores a complete answer in history and skips truncated or failed replies', function () {
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Simpan']);
    chatCash($family, 'Kas Simpan', 10_000);
    chatGrant($family);

    $ai = Mockery::mock(AiService::class);
    $ai->shouldReceive('isConfigured')->andReturn(true);
    $ai->shouldReceive('chat')->once()->andReturn([
        'ok' => false,
        'truncated' => true,
        'finish_reason' => 'MAX_TOKENS',
        'text' => '**Ringkasan Kond',
        'error' => 'Jawaban AI terpotong sebelum selesai.',
    ]);
    $ai->shouldReceive('chat')->once()->andReturn([
        'ok' => true,
        'truncated' => false,
        'text' => 'Saldo entity ini Rp 10.000.',
    ]);
    app()->instance(AiService::class, $ai);
    app()->forgetInstance(EntityAiChatService::class);

    $this->postJson(route('entity.ai.chat', $family), [
        'message' => 'Jelaskan kondisi keuangan saya',
    ])->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', EntityAiChatService::SAFE_ERROR);

    expect(app(EntityAiChatService::class)->history($family))->toBe([]);

    $this->postJson(route('entity.ai.chat', $family), [
        'message' => 'Berapa saldo?',
    ])->assertOk()->assertJsonPath('success', true);

    $stored = app(EntityAiChatService::class)->history($family);

    expect($stored)->toHaveCount(2)
        ->and($stored[0]['content'])->toBe('Berapa saldo?')
        ->and($stored[1]['content'])->toBe('Saldo entity ini Rp 10.000.')
        ->and(json_encode($stored))->not->toContain('**Ringkasan Kond');
});
