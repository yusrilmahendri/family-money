<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper tipis untuk OpenAI Chat Completion.
 * Tidak memuat package SDK supaya minim dependency.
 */
class AiService
{
    public function isConfigured(): bool
    {
        return !empty(config('services.openai.key'));
    }

    /**
     * Kirim percakapan ke OpenAI dan kembalikan teks balasan.
     *
     * @param  array  $messages  array of ['role' => system|user|assistant, 'content' => string]
     * @param  array  $options   override: model, temperature, max_tokens, response_format
     * @return array{ok: bool, text?: string, error?: string, raw?: array}
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'OpenAI API key belum diatur. Tambahkan OPENAI_API_KEY di file .env.',
            ];
        }

        $model = $options['model'] ?? config('services.openai.model', 'gpt-4o-mini');
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

        try {
            $resp = Http::withToken(config('services.openai.key'))
                ->timeout((int) config('services.openai.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post(rtrim(config('services.openai.base_url'), '/').'/chat/completions', $payload);

            if (!$resp->successful()) {
                Log::warning('OpenAI error', ['status' => $resp->status(), 'body' => $resp->body()]);
                return [
                    'ok' => false,
                    'error' => 'OpenAI gagal merespons ('.$resp->status().'): '.$resp->json('error.message', 'unknown'),
                ];
            }

            $text = (string) $resp->json('choices.0.message.content', '');

            return ['ok' => true, 'text' => $text, 'raw' => $resp->json()];
        } catch (\Throwable $e) {
            Log::warning('OpenAI exception', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Gangguan jaringan ke OpenAI: '.$e->getMessage()];
        }
    }
}
