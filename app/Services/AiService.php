<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper AI yang mendukung beberapa provider gratis:
 *  - gemini     (Google Gemini, gratis 1500 req/hari)
 *  - groq       (Groq Cloud, gratis & sangat cepat)
 *  - openrouter (banyak model gratis)
 *  - openai     (berbayar, untuk yang punya kredit)
 *  - ollama     (lokal, offline, gratis selamanya)
 *  - custom     (endpoint OpenAI-compatible apa saja)
 *
 * Cara ganti provider: edit AI_PROVIDER di .env
 */
class AiService
{
    public const INSIGHT_MAX_OUTPUT_TOKENS = 1600;

    public const MAX_OUTPUT_TOKEN_CAP = 8192;

    public function provider(): string
    {
        $fromConfig = config('services.ai.provider');
        $fromEnv = env('AI_PROVIDER');

        return strtolower((string) ($fromConfig ?: $fromEnv ?: 'gemini'));
    }

    public function isConfigured(): bool
    {
        $cfg = $this->cfg();
        if ($this->provider() === 'ollama') {
            return ! empty($cfg['base_url']);
        }

        return ! empty($cfg['key'] ?? null);
    }

    public function providerLabel(): string
    {
        return match ($this->provider()) {
            'gemini' => 'Google Gemini',
            'groq' => 'Groq',
            'openrouter' => 'OpenRouter',
            'openai' => 'OpenAI',
            'ollama' => 'Ollama (lokal)',
            'custom' => 'AI custom',
            default => $this->provider(),
        };
    }

    public function envKeyName(): string
    {
        return match ($this->provider()) {
            'gemini' => 'GEMINI_API_KEY',
            'groq' => 'GROQ_API_KEY',
            'openrouter' => 'OPENROUTER_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'ollama' => 'OLLAMA_BASE_URL',
            'custom' => 'AI_CUSTOM_KEY',
            default => 'AI_API_KEY',
        };
    }

    /**
     * Kirim percakapan ke AI dan kembalikan teks balasan.
     *
     * @param  array  $messages  array of ['role' => system|user|assistant, 'content' => string]
     * @param  array  $options  override: model, temperature, max_tokens
     * @return array{
     *     ok: bool,
     *     text?: string,
     *     error?: string,
     *     raw?: array,
     *     status?: int,
     *     model?: string,
     *     finish_reason?: string,
     *     truncated?: bool,
     *     usage?: array<string, mixed>|null
     * }
     */
    public function chat(array $messages, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'Fitur AI belum aktif. Tambahkan %s di file .env (provider sekarang: %s).',
                    $this->envKeyName(),
                    $this->providerLabel(),
                ),
            ];
        }

        return match ($this->provider()) {
            'gemini' => $this->chatGemini($messages, $options),
            default => $this->chatOpenAiCompatible($messages, $options),
        };
    }

    // ------------------------------------------------------------------
    // PROVIDER: Google Gemini
    // ------------------------------------------------------------------

    private function chatGemini(array $messages, array $options): array
    {
        $cfg = $this->cfg();
        $preferred = $options['model'] ?? ($cfg['model'] ?? 'gemini-2.0-flash-lite');

        // Coba model utama dulu, lalu fallback jika kuota habis (429) atau model tidak ada (404)
        $modelsToTry = array_values(array_unique(array_filter([
            $preferred,
            'gemini-2.0-flash-lite',
            'gemini-2.0-flash-001',
            'gemini-2.5-flash',
            'gemini-flash-latest',
        ])));

        $lastError = 'Gemini tidak merespons.';
        foreach ($modelsToTry as $model) {
            $result = $this->chatGeminiOnce($cfg, $model, $messages, $options);
            if ($result['ok']) {
                return $result;
            }
            $lastError = $result['error'] ?? $lastError;
            $retryable = in_array($result['status'] ?? 0, [429, 404, 503], true);
            if (! $retryable) {
                return $result;
            }
        }

        return ['ok' => false, 'error' => $lastError];
    }

    private function chatGeminiOnce(array $cfg, string $model, array $messages, array $options, bool $retriedMaxTokens = false): array
    {
        [$systemText, $contents] = $this->messagesToGemini($messages);
        $maxOutputTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : 2048;

        $payload = [
            'contents' => $contents,
            'generationConfig' => array_filter([
                'temperature' => $options['temperature'] ?? 0.3,
                'maxOutputTokens' => $maxOutputTokens,
            ]),
        ];

        if ($systemText !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemText]],
            ];
        }

        $url = rtrim((string) $cfg['base_url'], '/').'/models/'.$model.':generateContent';

        try {
            $resp = Http::withQueryParameters(['key' => $cfg['key']])
                ->timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            $status = $resp->status();

            if (! $resp->successful()) {
                $msg = $resp->json('error.message') ?? '';
                $this->logProvider('warning', [
                    'provider' => 'gemini',
                    'model' => $model,
                    'status' => $status,
                    'finish_reason' => null,
                    'truncated' => false,
                ]);

                if ($status === 429) {
                    return ['ok' => false, 'status' => 429, 'model' => $model, 'truncated' => false, 'error' => 'Kuota habis untuk model '.$model.'. Mencoba model lain...'];
                }
                if ($status === 400 && str_contains(strtolower((string) $msg), 'api key')) {
                    return ['ok' => false, 'status' => 400, 'model' => $model, 'truncated' => false, 'error' => 'API key Gemini tidak valid. Salin ulang dari aistudio.google.com/apikey (tombol Copy key) ke GEMINI_API_KEY di .env.'];
                }
                if ($status === 404) {
                    return ['ok' => false, 'status' => 404, 'model' => $model, 'truncated' => false, 'error' => 'Model '.$model.' tidak tersedia.'];
                }
                if ($status === 503) {
                    return ['ok' => false, 'status' => 503, 'model' => $model, 'truncated' => false, 'error' => 'Model '.$model.' sibuk (503). Mencoba model lain...'];
                }

                return ['ok' => false, 'status' => $status, 'model' => $model, 'truncated' => false, 'error' => 'Gemini gagal ('.$status.'): '.$this->shorten($msg)];
            }

            $json = $resp->json();
            $json = is_array($json) ? $json : [];
            $text = $this->geminiCandidateText($json);
            $finish = strtoupper((string) data_get($json, 'candidates.0.finishReason', 'unknown'));
            $usage = data_get($json, 'usageMetadata');
            $truncated = $finish === 'MAX_TOKENS';

            $this->logProvider('info', [
                'provider' => 'gemini',
                'model' => $model,
                'status' => $status,
                'finish_reason' => $finish,
                'truncated' => $truncated,
            ]);

            if ($truncated) {
                if (! $retriedMaxTokens) {
                    $retryTokens = $this->nextOutputTokenBudget($maxOutputTokens);
                    if ($retryTokens > $maxOutputTokens) {
                        $retryOptions = $options;
                        $retryOptions['max_tokens'] = $retryTokens;

                        return $this->chatGeminiOnce($cfg, $model, $messages, $retryOptions, true);
                    }
                }

                return [
                    'ok' => false,
                    'truncated' => true,
                    'model' => $model,
                    'finish_reason' => $finish,
                    'usage' => is_array($usage) ? $usage : null,
                    'error' => 'Jawaban AI terpotong sebelum selesai.',
                ];
            }

            if ($text === '') {
                return [
                    'ok' => false,
                    'truncated' => false,
                    'model' => $model,
                    'finish_reason' => $finish,
                    'usage' => is_array($usage) ? $usage : null,
                    'error' => 'Gemini tidak mengembalikan teks (finishReason: '.$finish.').',
                ];
            }

            return [
                'ok' => true,
                'text' => $text,
                'model' => $model,
                'finish_reason' => $finish,
                'truncated' => false,
                'usage' => is_array($usage) ? $usage : null,
                'raw' => $json,
            ];
        } catch (\Throwable) {
            $this->logProvider('warning', [
                'provider' => 'gemini',
                'model' => $model,
                'status' => null,
                'finish_reason' => null,
                'truncated' => false,
            ]);

            return ['ok' => false, 'model' => $model, 'truncated' => false, 'error' => 'Gangguan jaringan ke Gemini.'];
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function geminiCandidateText(array $json): string
    {
        $parts = data_get($json, 'candidates.0.content.parts', []);
        $text = '';

        foreach (is_array($parts) ? $parts : [] as $part) {
            if (is_array($part) && isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        return $text;
    }

    private function nextOutputTokenBudget(int $current): int
    {
        $current = max(1, $current);

        return min(max($current * 2, $current + 512), self::MAX_OUTPUT_TOKEN_CAP);
    }

    /**
     * @param  array{provider: string, model?: string|null, status?: int|null, finish_reason?: string|null, truncated?: bool}  $context
     */
    private function logProvider(string $level, array $context): void
    {
        $safe = [
            'provider' => $context['provider'],
            'model' => $context['model'] ?? null,
            'status' => $context['status'] ?? null,
            'finish_reason' => $context['finish_reason'] ?? null,
            'truncated' => (bool) ($context['truncated'] ?? false),
        ];

        if ($level === 'warning') {
            Log::warning('AI provider response', $safe);

            return;
        }

        Log::info('AI provider response', $safe);
    }

    /**
     * Konversi messages OpenAI-style → Gemini format.
     * - system messages digabung jadi systemInstruction (Gemini tidak ada role "system" di contents)
     * - role "assistant" → "model"
     */
    private function messagesToGemini(array $messages): array
    {
        $systemText = '';
        $contents = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = (string) ($m['content'] ?? '');
            if ($role === 'system') {
                $systemText .= ($systemText === '' ? '' : "\n\n").$content;

                continue;
            }
            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $content]],
            ];
        }

        return [$systemText, $contents];
    }

    // ------------------------------------------------------------------
    // PROVIDER: OpenAI / Groq / OpenRouter / Ollama / Custom (OpenAI-compatible)
    // ------------------------------------------------------------------

    private function chatOpenAiCompatible(array $messages, array $options): array
    {
        $cfg = $this->cfg();
        $model = $options['model'] ?? ($cfg['model'] ?? null);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
        ];
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $url = rtrim($cfg['base_url'], '/').'/chat/completions';

        $headers = [];
        if ($this->provider() === 'openrouter') {
            $headers['HTTP-Referer'] = config('app.url', 'http://localhost');
            $headers['X-Title'] = config('app.name', 'family-keuangan');
        }

        try {
            $http = Http::timeout($this->timeout())->acceptJson()->asJson();
            if (! empty($cfg['key'])) {
                $http = $http->withToken($cfg['key']);
            }
            if (! empty($headers)) {
                $http = $http->withHeaders($headers);
            }

            $resp = $http->post($url, $payload);

            if (! $resp->successful()) {
                $this->logProvider('warning', [
                    'provider' => $this->provider(),
                    'model' => is_string($model) ? $model : null,
                    'status' => $resp->status(),
                    'finish_reason' => null,
                    'truncated' => false,
                ]);
                $msg = $resp->json('error.message') ?? '';

                return [
                    'ok' => false,
                    'status' => $resp->status(),
                    'model' => is_string($model) ? $model : null,
                    'truncated' => false,
                    'error' => $this->providerLabel().' gagal ('.$resp->status().'): '.$this->shorten($msg),
                ];
            }

            $json = $resp->json();
            $json = is_array($json) ? $json : [];
            $text = (string) data_get($json, 'choices.0.message.content', '');
            $finish = strtoupper((string) data_get($json, 'choices.0.finish_reason', 'unknown'));
            $truncated = $finish === 'LENGTH';
            $usage = data_get($json, 'usage');

            $this->logProvider('info', [
                'provider' => $this->provider(),
                'model' => is_string($model) ? $model : null,
                'status' => $resp->status(),
                'finish_reason' => $finish,
                'truncated' => $truncated,
            ]);

            if ($truncated) {
                return [
                    'ok' => false,
                    'truncated' => true,
                    'model' => is_string($model) ? $model : null,
                    'finish_reason' => $finish,
                    'usage' => is_array($usage) ? $usage : null,
                    'error' => 'Jawaban AI terpotong sebelum selesai.',
                ];
            }

            if ($text === '') {
                return [
                    'ok' => false,
                    'truncated' => false,
                    'model' => is_string($model) ? $model : null,
                    'finish_reason' => $finish,
                    'error' => $this->providerLabel().' tidak mengembalikan teks.',
                ];
            }

            return [
                'ok' => true,
                'text' => $text,
                'model' => is_string($model) ? $model : null,
                'finish_reason' => $finish,
                'truncated' => false,
                'usage' => is_array($usage) ? $usage : null,
                'raw' => $json,
            ];
        } catch (\Throwable) {
            $this->logProvider('warning', [
                'provider' => $this->provider(),
                'model' => is_string($model) ? $model : null,
                'status' => null,
                'finish_reason' => null,
                'truncated' => false,
            ]);

            return ['ok' => false, 'truncated' => false, 'error' => 'Gangguan jaringan ke penyedia AI.'];
        }
    }

    // ------------------------------------------------------------------

    private function cfg(): array
    {
        $provider = $this->provider();
        $cfg = (array) config('services.ai.'.$provider, []);

        // Fallback: baca langsung .env (penting di shared hosting setelah edit .env tanpa config:clear)
        $envFallback = [
            'gemini' => [
                'key' => 'GEMINI_API_KEY',
                'model' => 'GEMINI_MODEL',
                'base_url' => 'GEMINI_BASE_URL',
                'default_model' => 'gemini-2.0-flash-lite',
                'default_base' => 'https://generativelanguage.googleapis.com/v1beta',
            ],
            'groq' => [
                'key' => 'GROQ_API_KEY',
                'model' => 'GROQ_MODEL',
                'base_url' => 'GROQ_BASE_URL',
                'default_model' => 'llama-3.3-70b-versatile',
                'default_base' => 'https://api.groq.com/openai/v1',
            ],
            'openrouter' => [
                'key' => 'OPENROUTER_API_KEY',
                'model' => 'OPENROUTER_MODEL',
                'base_url' => 'OPENROUTER_BASE_URL',
                'default_model' => 'deepseek/deepseek-chat-v3-0324:free',
                'default_base' => 'https://openrouter.ai/api/v1',
            ],
            'openai' => [
                'key' => 'OPENAI_API_KEY',
                'model' => 'OPENAI_MODEL',
                'base_url' => 'OPENAI_BASE_URL',
                'default_model' => 'gpt-4o-mini',
                'default_base' => 'https://api.openai.com/v1',
            ],
            'ollama' => [
                'key' => 'OLLAMA_API_KEY',
                'model' => 'OLLAMA_MODEL',
                'base_url' => 'OLLAMA_BASE_URL',
                'default_model' => 'llama3.1',
                'default_base' => 'http://localhost:11434/v1',
            ],
            'custom' => [
                'key' => 'AI_CUSTOM_KEY',
                'model' => 'AI_CUSTOM_MODEL',
                'base_url' => 'AI_CUSTOM_BASE_URL',
                'default_model' => null,
                'default_base' => null,
            ],
        ];

        if (isset($envFallback[$provider])) {
            $fb = $envFallback[$provider];
            if (empty($cfg['key'])) {
                $cfg['key'] = trim((string) env($fb['key'], ''));
            }
            if (empty($cfg['model'])) {
                $cfg['model'] = env($fb['model'], $fb['default_model']);
            }
            if (empty($cfg['base_url'])) {
                $cfg['base_url'] = env($fb['base_url'], $fb['default_base']);
            }
        }

        return $cfg;
    }

    private function timeout(): int
    {
        return (int) config('services.ai.timeout', 45);
    }

    private function shorten($s, int $max = 300): string
    {
        $s = is_string($s) ? $s : json_encode($s);

        return strlen($s) > $max ? substr($s, 0, $max).'…' : $s;
    }
}
