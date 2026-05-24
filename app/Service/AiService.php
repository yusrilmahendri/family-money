<?php

namespace App\Service;

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
    public function provider(): string
    {
        return strtolower((string) config('services.ai.provider', 'gemini'));
    }

    public function isConfigured(): bool
    {
        $cfg = $this->cfg();
        if ($this->provider() === 'ollama') {
            return !empty($cfg['base_url']);
        }
        return !empty($cfg['key'] ?? null);
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
     * @param  array  $options   override: model, temperature, max_tokens
     * @return array{ok: bool, text?: string, error?: string, raw?: array}
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
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
            default  => $this->chatOpenAiCompatible($messages, $options),
        };
    }

    // ------------------------------------------------------------------
    // PROVIDER: Google Gemini
    // ------------------------------------------------------------------

    private function chatGemini(array $messages, array $options): array
    {
        $cfg = $this->cfg();
        $model = $options['model'] ?? ($cfg['model'] ?? 'gemini-2.0-flash');

        [$systemText, $contents] = $this->messagesToGemini($messages);

        $payload = [
            'contents' => $contents,
            'generationConfig' => array_filter([
                'temperature' => $options['temperature'] ?? 0.3,
                'maxOutputTokens' => isset($options['max_tokens']) ? (int) $options['max_tokens'] : 1024,
            ]),
        ];

        if ($systemText !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemText]],
            ];
        }

        $url = rtrim($cfg['base_url'], '/').'/models/'.$model.':generateContent';

        try {
            $resp = Http::withQueryParameters(['key' => $cfg['key']])
                ->timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if (!$resp->successful()) {
                Log::warning('Gemini error', ['status' => $resp->status(), 'body' => $resp->body()]);
                $msg = $resp->json('error.message') ?? $resp->body();
                return ['ok' => false, 'error' => 'Gemini gagal ('.$resp->status().'): '.$this->shorten($msg)];
            }

            $text = '';
            $parts = $resp->json('candidates.0.content.parts') ?? [];
            foreach ($parts as $p) {
                if (isset($p['text'])) $text .= $p['text'];
            }
            if ($text === '') {
                $finish = $resp->json('candidates.0.finishReason') ?? 'unknown';
                return ['ok' => false, 'error' => 'Gemini tidak mengembalikan teks (finishReason: '.$finish.').'];
            }

            return ['ok' => true, 'text' => $text, 'raw' => $resp->json()];
        } catch (\Throwable $e) {
            Log::warning('Gemini exception', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Gangguan jaringan ke Gemini: '.$e->getMessage()];
        }
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
            if (!empty($cfg['key'])) {
                $http = $http->withToken($cfg['key']);
            }
            if (!empty($headers)) {
                $http = $http->withHeaders($headers);
            }

            $resp = $http->post($url, $payload);

            if (!$resp->successful()) {
                Log::warning($this->providerLabel().' error', ['status' => $resp->status(), 'body' => $resp->body()]);
                $msg = $resp->json('error.message') ?? $resp->body();
                return [
                    'ok' => false,
                    'error' => $this->providerLabel().' gagal ('.$resp->status().'): '.$this->shorten($msg),
                ];
            }

            $text = (string) $resp->json('choices.0.message.content', '');
            if ($text === '') {
                return ['ok' => false, 'error' => $this->providerLabel().' tidak mengembalikan teks.'];
            }
            return ['ok' => true, 'text' => $text, 'raw' => $resp->json()];
        } catch (\Throwable $e) {
            Log::warning($this->providerLabel().' exception', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Gangguan jaringan: '.$e->getMessage()];
        }
    }

    // ------------------------------------------------------------------

    private function cfg(): array
    {
        return (array) config('services.ai.'.$this->provider(), []);
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
