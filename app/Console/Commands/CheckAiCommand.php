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
        $key = (string) config('services.ai.'.$provider.'.key');
        $model = (string) config('services.ai.'.$provider.'.model');

        $this->info('=== Konfigurasi AI ===');
        $this->line('Provider     : '.$provider.' ('.$ai->providerLabel().')');
        $this->line('Model        : '.($model ?: '(kosong)'));
        $this->line('Key length   : '.strlen($key));
        $this->line('Key preview  : '.($key === '' ? '(kosong)' : substr($key, 0, 8).'...'.substr($key, -4)));
        $this->line('isConfigured : '.($ai->isConfigured() ? 'YA' : 'TIDAK'));

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
