<?php

use App\Enums\FinanceAccountType;
use App\Models\FinanceEntity;
use App\Services\AiService;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityAccessTokenService;
use App\Services\Insight\EntityAiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function geminiTestConfig(): void
{
    config([
        'services.ai.provider' => 'gemini',
        'services.ai.gemini.key' => 'test-gemini-key-secret',
        'services.ai.gemini.model' => 'gemini-2.0-flash-lite',
        'services.ai.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
    ]);
}

function geminiCandidate(string $text, string $finish = 'STOP'): array
{
    return [
        'candidates' => [[
            'finishReason' => $finish,
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ]],
        'usageMetadata' => [
            'promptTokenCount' => 120,
            'candidatesTokenCount' => 80,
        ],
    ];
}

function geminiGrant(FinanceEntity $entity): void
{
    [, $plain] = app(FinanceEntityAccessTokenService::class)->issue($entity);
    test()->get(route('access.show', $plain))->assertRedirect();
}

it('retries the same Gemini model once after MAX_TOKENS and does not treat the partial as success', function () {
    geminiTestConfig();
    $logs = [];
    \Illuminate\Support\Facades\Log::listen(function (MessageLogged $event) use (&$logs) {
        $logs[] = json_encode([$event->message, $event->context]);
    });
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(geminiCandidate('**Ringkasan Kond', 'MAX_TOKENS'))
            ->push(geminiCandidate('Saldo tersedia Rp 10.000.', 'STOP')),
    ]);

    $result = app(AiService::class)->chat([
        ['role' => 'user', 'content' => 'Berapa saldo?'],
    ], ['max_tokens' => EntityAiChatService::OUTPUT_TOKENS]);

    $requests = Http::recorded();
    $urls = $requests->map(fn ($pair) => $pair[0]->url())->all();
    $tokens = $requests->map(fn ($pair) => $pair[0]->data()['generationConfig']['maxOutputTokens'] ?? null)->all();
    $logBlob = implode("\n", $logs);

    expect($result['ok'])->toBeTrue()
        ->and($result['truncated'])->toBeFalse()
        ->and($result['text'])->toBe('Saldo tersedia Rp 10.000.')
        ->and($result['finish_reason'])->toBe('STOP')
        ->and($result['model'])->toBe('gemini-2.0-flash-lite')
        ->and($requests)->toHaveCount(2)
        ->and($tokens)->toBe([EntityAiChatService::OUTPUT_TOKENS, EntityAiChatService::OUTPUT_TOKENS * 2])
        ->and($urls[0])->toContain('models/gemini-2.0-flash-lite:generateContent')
        ->and($urls[1])->toContain('models/gemini-2.0-flash-lite:generateContent')
        ->and(implode("\n", $urls))->not->toContain('gemini-2.5-flash')
        ->and($logBlob)->toContain('AI provider response')
        ->not->toContain('test-gemini-key-secret')
        ->not->toContain('**Ringkasan Kond')
        ->not->toContain('Berapa saldo?');
});

it('does not mark a still-truncated MAX_TOKENS reply as a final success', function () {
    geminiTestConfig();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(geminiCandidate('**Ringkasan Kond', 'MAX_TOKENS'))
            ->push(geminiCandidate('**Ringkasan Kondisi keu', 'MAX_TOKENS')),
    ]);

    $result = app(AiService::class)->chat([
        ['role' => 'user', 'content' => 'Jelaskan kondisi keuangan'],
    ], ['max_tokens' => EntityAiChatService::OUTPUT_TOKENS]);

    expect($result['ok'])->toBeFalse()
        ->and($result['truncated'])->toBeTrue()
        ->and($result['finish_reason'])->toBe('MAX_TOKENS')
        ->and($result)->not->toHaveKey('text')
        ->and(Http::recorded())->toHaveCount(2);
});

it('does not persist a truncated Gemini reply to entity chat history', function () {
    geminiTestConfig();
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga Trunc']);
    app(FinanceAccountService::class)->create($family, [
        'name' => 'Kas Trunc',
        'type' => FinanceAccountType::CASH,
        'opening_balance' => 10_000,
    ]);
    geminiGrant($family);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(geminiCandidate('**Ringkasan Kond', 'MAX_TOKENS'))
            ->push(geminiCandidate('**Ringkasan Kond', 'MAX_TOKENS')),
    ]);

    $this->postJson(route('entity.ai.chat', $family), [
        'message' => 'Jelaskan kondisi keuangan saya berdasarkan ringkasan dan anomali ini',
    ])->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', EntityAiChatService::SAFE_ERROR);

    expect(app(EntityAiChatService::class)->history($family))->toBe([]);
});

it('does not return a truncated entity insight summary as success', function () {
    geminiTestConfig();
    $family = FinanceEntity::factory()->family()->create(['name' => 'Keluarga SummaryTrunc']);
    geminiGrant($family);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(geminiCandidate('**Ringkasan Kond', 'MAX_TOKENS'))
            ->push(geminiCandidate('**Ringkasan Kond', 'MAX_TOKENS')),
    ]);

    $this->postJson(route('entity.insight.summary', $family))
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('payload.entity.name', 'Keluarga SummaryTrunc')
        ->assertJsonPath('summary', '');
});
