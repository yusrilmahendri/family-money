<?php

namespace App\Http\Controllers;

use App\Service\AiService;
use App\Services\Insight\InsightDataService;
use App\Support\FinanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InsightController extends Controller
{
    public function __construct(
        protected AiService $ai,
        protected InsightDataService $data,
    ) {}

    public function index(Request $request)
    {
        // Guard ringan: wajib pilih konteks dulu (tanpa middleware)
        if (! FinanceContext::isSelected()) {
            return redirect()->route('apps.index');
        }

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $anomali = $this->data->getAnomalyPayload($year, $month);
        $forecast = $this->data->getForecastPayload(3);

        return view('insight.index', [
            'title' => 'Insight AI — '.$this->data->getContextLabel(),
            'year' => $year,
            'month' => $month,
            'context' => $this->data->getContext(),
            'context_label' => $this->data->getContextLabel(),
            'mode' => $this->data->mode(),
            'has_data' => $this->data->hasData(),
            'anomali' => $anomali,
            'forecast' => $forecast,
            'ai_ready' => $this->ai->isConfigured(),
            'ai_provider_label' => $this->ai->providerLabel(),
            'ai_env_key' => $this->ai->envKeyName(),
            'gemini_key_length' => strlen(trim((string) env('GEMINI_API_KEY', ''))),
            'available_years' => $this->availableYears(),
        ]);
    }

    /**
     * Generate ringkasan bulanan via AI — terikat konteks aktif.
     */
    public function generateSummary(Request $request): JsonResponse
    {
        if (! FinanceContext::isSelected()) {
            return response()->json(['ok' => false, 'error' => 'Pilih aplikasi terlebih dahulu di /apps.'], 200);
        }

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $ctx = $this->data->getContext();
        $ctxLabel = $this->data->getContextLabel();

        if (! $this->data->hasData()) {
            return response()->json([
                'ok' => false,
                'error' => 'Belum ada data untuk konteks '.$ctxLabel.'. Tambahkan data dulu agar AI bisa menganalisis.',
            ], 200);
        }

        if (! $this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'error' => sprintf(
                    'AI belum dikonfigurasi. Tambahkan %s di .env (provider: %s).',
                    $this->ai->envKeyName(),
                    $this->ai->providerLabel()
                ),
            ], 200);
        }

        $cacheKey = sprintf('ai_summary_%s_%d_%02d', strtolower($ctx), $year, $month);

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $summary = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($year, $month, $ctx, $ctxLabel) {
            $data = [
                'summary' => $this->data->getSummaryPayload($year, $month),
                'anomali' => $this->data->getAnomalyPayload($year, $month),
                'forecast' => $this->data->getForecastPayload(3),
            ];

            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $bulanLabel = $data['anomali']['bulan'];

            $fokus = $ctx === FinanceContext::USAHA_KEBUN
                ? 'KEUANGAN USAHA KEBUN (pemasukan hasil usaha & biaya operasional)'
                : 'KEUANGAN PRIBADI (pengeluaran & kebutuhan pribadi)';

            $system = "Anda adalah analis keuangan ramah. Anda sedang menganalisis {$fokus}. ".
                "PENTING: analisis HANYA berdasarkan data konteks ini; JANGAN menyebut atau mencampur konteks lain ".
                "(jika ini PRIBADI jangan bahas usaha kebun, dan sebaliknya). ".
                "Tulis dalam BAHASA INDONESIA, paragraf pendek (3-5 paragraf), bahasa sederhana, format Rupiah (Rp 1.250.000). ".
                "Jangan mengarang angka. Akhiri dengan 2-3 saran konkret yang relevan dengan konteks {$ctxLabel}.";

            $user = <<<USR
Buatkan ringkasan {$fokus} untuk periode {$bulanLabel} berdasarkan data berikut.

Sertakan:
1. Ringkasan posisi/performa periode ini.
2. Tren beberapa bulan terakhir (membaik / memburuk?).
3. Anomali yang ditemukan (jika ada).
4. Proyeksi 3 bulan ke depan.
5. 2-3 saran konkret yang relevan dengan {$ctxLabel}.

DATA (JSON, sudah difilter untuk konteks {$ctxLabel}):
$payload
USR;

            return $this->ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], ['temperature' => 0.4, 'max_tokens' => 900]);
        });

        if (! $summary['ok']) {
            Cache::forget($cacheKey);
            return response()->json(['ok' => false, 'error' => $summary['error'] ?? 'AI gagal merespons.']);
        }

        return response()->json([
            'ok' => true,
            'context' => $ctx,
            'context_label' => $ctxLabel,
            'summary' => trim($summary['text'] ?? ''),
        ]);
    }

    /**
     * Penjelasan AI untuk anomali yang terdeteksi — terikat konteks aktif.
     */
    public function explainAnomalies(Request $request): JsonResponse
    {
        if (! FinanceContext::isSelected()) {
            return response()->json(['ok' => false, 'error' => 'Pilih aplikasi terlebih dahulu di /apps.'], 200);
        }

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $ctx = $this->data->getContext();
        $ctxLabel = $this->data->getContextLabel();

        if (! $this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'error' => sprintf('AI belum dikonfigurasi. Tambahkan %s di .env.', $this->ai->envKeyName()),
            ], 200);
        }

        $anomali = $this->data->getAnomalyPayload($year, $month);
        if (empty($anomali['anomalies'])) {
            return response()->json([
                'ok' => true,
                'context_label' => $ctxLabel,
                'explanation' => 'Tidak ada anomali signifikan pada periode ini untuk '.$ctxLabel.'. Pola keuangan terlihat normal.',
            ]);
        }

        $payload = json_encode($anomali, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $fokus = $ctx === FinanceContext::USAHA_KEBUN ? 'KEUANGAN USAHA KEBUN' : 'KEUANGAN PRIBADI';

        $resp = $this->ai->chat([
            ['role' => 'system', 'content' => "Anda analis keuangan. Anda menganalisis {$fokus}. Jawab dalam BAHASA INDONESIA, singkat & praktis, format Rupiah Rp 1.250.000. Jangan mencampur konteks lain."],
            ['role' => 'user', 'content' => "Berikut hasil deteksi anomali {$fokus} saya. Jelaskan kemungkinan penyebab tiap anomali dan beri saran tindakan.\n\nDATA:\n$payload"],
        ], ['temperature' => 0.3, 'max_tokens' => 700]);

        if (! $resp['ok']) {
            return response()->json(['ok' => false, 'error' => $resp['error']]);
        }

        return response()->json(['ok' => true, 'context_label' => $ctxLabel, 'explanation' => trim($resp['text'] ?? '')]);
    }

    private function availableYears(): array
    {
        $current = (int) now()->year;
        return [$current - 1, $current, $current + 1];
    }
}
