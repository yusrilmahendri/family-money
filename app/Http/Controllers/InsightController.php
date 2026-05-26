<?php

namespace App\Http\Controllers;

use App\Service\AiService;
use App\Service\FinanceContextService;
use App\Service\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InsightController extends Controller
{
    public function __construct(
        protected AiService $ai,
        protected FinanceContextService $context,
        protected InsightService $insight,
    ) {}

    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $anomaliData = $this->insight->detectAnomalies($year, $month);
        $forecastData = $this->insight->forecastNext3Months();

        return view('insight.index', [
            'title' => 'Insight AI',
            'year' => $year,
            'month' => $month,
            'anomali' => $anomaliData,
            'forecast' => $forecastData,
            'ai_ready' => $this->ai->isConfigured(),
            'ai_provider_label' => $this->ai->providerLabel(),
            'ai_env_key' => $this->ai->envKeyName(),
            'gemini_key_length' => strlen(trim((string) env('GEMINI_API_KEY', ''))),
            'available_years' => $this->availableYears(),
        ]);
    }

    /**
     * Generate ringkasan bulanan via AI (POST agar tidak ter-cache di sisi browser).
     */
    public function generateSummary(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        if (!$this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'error' => sprintf(
                    'AI belum dikonfigurasi. Tambahkan %s di .env (provider: %s).',
                    $this->ai->envKeyName(),
                    $this->ai->providerLabel()
                ),
            ], 200);
        }

        $cacheKey = sprintf('ai_summary_%d_%02d', $year, $month);

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $summary = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($year, $month) {
            $snapshot = $this->context->snapshot();
            $anomali = $this->insight->detectAnomalies($year, $month);
            $forecast = $this->insight->forecastNext3Months();

            $data = [
                'snapshot' => $snapshot,
                'anomali' => $anomali,
                'forecast' => $forecast,
            ];

            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $bulanLabel = $anomali['bulan'];

            $system = "Anda adalah analis keuangan ramah. Tulis ringkasan keuangan keluarga dalam BAHASA INDONESIA. ".
                "Gunakan paragraf pendek (3-5 paragraf), bahasa sederhana, dan format Rupiah (Rp 1.250.000). ".
                "Jangan mengarang angka. Akhiri dengan 2-3 saran konkret.";

            $user = <<<USR
Tolong buatkan ringkasan keuangan untuk periode $bulanLabel berdasarkan data berikut.

Sertakan:
1. Ringkasan posisi keuangan saat ini (saldo, anggaran, dll.).
2. Performa pemasukan & biaya bulan ini (apakah baik?).
3. Anomali yang ditemukan (jika ada).
4. Proyeksi 3 bulan ke depan.
5. 2-3 saran konkret yang bisa ditindaklanjuti.

DATA (JSON):
$payload
USR;

            $resp = $this->ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], ['temperature' => 0.4, 'max_tokens' => 900]);

            return $resp;
        });

        if (!$summary['ok']) {
            Cache::forget($cacheKey);
            return response()->json(['ok' => false, 'error' => $summary['error'] ?? 'AI gagal merespons.']);
        }

        return response()->json([
            'ok' => true,
            'summary' => trim($summary['text'] ?? ''),
        ]);
    }

    /**
     * Penjelasan AI untuk anomali yang terdeteksi.
     */
    public function explainAnomalies(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        if (!$this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'error' => sprintf(
                    'AI belum dikonfigurasi. Tambahkan %s di .env.',
                    $this->ai->envKeyName()
                ),
            ], 200);
        }

        $anomali = $this->insight->detectAnomalies($year, $month);
        if (empty($anomali['anomalies'])) {
            return response()->json([
                'ok' => true,
                'explanation' => 'Tidak ada anomali signifikan pada periode ini. Pola keuangan terlihat normal.',
            ]);
        }

        $payload = json_encode($anomali, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $resp = $this->ai->chat([
            ['role' => 'system', 'content' => 'Anda analis keuangan. Jawab dalam BAHASA INDONESIA, singkat dan praktis. Format Rupiah: Rp 1.250.000.'],
            ['role' => 'user', 'content' => "Berikut hasil deteksi anomali keuangan saya. Tolong jelaskan kemungkinan penyebabnya dan beri saran tindakan untuk setiap anomali.\n\nDATA:\n$payload"],
        ], ['temperature' => 0.3, 'max_tokens' => 700]);

        if (!$resp['ok']) {
            return response()->json(['ok' => false, 'error' => $resp['error']]);
        }

        return response()->json(['ok' => true, 'explanation' => trim($resp['text'] ?? '')]);
    }

    private function availableYears(): array
    {
        $current = (int) now()->year;
        return [$current - 1, $current, $current + 1];
    }
}
