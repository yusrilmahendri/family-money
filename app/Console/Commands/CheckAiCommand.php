<?php

namespace App\Console\Commands;

use App\Service\AiService;
use Illuminate\Console\Command;

class CheckAiCommand extends Command
{
    protected $signature = 'ai:check';
    protected $description = 'Verifikasi konfigurasi AI (provider + key + model)';

    public function handle(AiService $ai): int
    {
        $provider = $ai->provider();
        $keyConfig = trim((string) config('services.ai.'.$provider.'.key'));
        $keyEnv = trim((string) match ($provider) {
            'gemini' => env('GEMINI_API_KEY', ''),
            'groq' => env('GROQ_API_KEY', ''),
            'openrouter' => env('OPENROUTER_API_KEY', ''),
            'openai' => env('OPENAI_API_KEY', ''),
            default => '',
        });
        $model = (string) (config('services.ai.'.$provider.'.model') ?: env(strtoupper($provider).'_MODEL', ''));

        $this->info('=== Konfigurasi AI ===');
        $this->line('Folder kerja : '.base_path());
        $this->line('Provider     : '.$provider.' ('.$ai->providerLabel().')');
        $this->line('Model        : '.($model ?: '(kosong)'));
        $this->line('Key (config) : '.($keyConfig === '' ? '(kosong)' : strlen($keyConfig).' karakter'));
        $this->line('Key (.env)   : '.($keyEnv === '' ? '(kosong)' : strlen($keyEnv).' karakter'));
        $this->line('Key dipakai  : '.($ai->isConfigured() ? 'YA' : 'TIDAK'));
        if ($keyConfig === '' && $keyEnv !== '') {
            $this->warn('-> Key ada di .env tapi config belum refresh. Jalankan: php artisan config:clear');
        }
        if ($keyConfig === '' && $keyEnv === '') {
            $this->warn('-> Tambahkan '.$ai->envKeyName().'=... di file .env di folder ini: '.base_path());
        }

        if (!$ai->isConfigured()) {
            $this->error('-> Key belum terbaca. Cek '.$ai->envKeyName().' di .env, lalu php artisan config:clear.');
            return self::FAILURE;
        }

        $this->info('-> Konfigurasi sudah OK. Mencoba ping ke '.$ai->providerLabel().'...');
        $resp = $ai->chat(
            [['role' => 'user', 'content' => 'Jawab singkat dalam Bahasa Indonesia: halo, apa kabar?']],
            ['max_tokens' => 256]
        );
        if ($resp['ok']) {
            $this->info('-> Balasan AI : '.trim($resp['text']));
            return self::SUCCESS;
        }
        $this->error('-> Error : '.$resp['error']);
        return self::FAILURE;
    }
}
