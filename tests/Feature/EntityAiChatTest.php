<?php

use App\Enums\AuditAction;
use App\Enums\FinanceAccountType;
use App\Models\AuditLog;
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
