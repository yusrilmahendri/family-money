<?php

namespace App\Http\Controllers;

use App\Service\AiService;
use App\Services\Insight\InsightDataService;
use App\Support\FinanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(
        protected AiService $ai,
        protected InsightDataService $data,
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'history' => ['array'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $ctx = $this->data->getContext();
        $ctxLabel = $this->data->getContextLabel();

        if (!$this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'context' => $ctx,
                'context_label' => $ctxLabel,
                'error' => sprintf(
                    'Fitur AI belum aktif. Tambahkan %s di file .env (provider: %s).',
                    $this->ai->envKeyName(),
                    $this->ai->providerLabel()
                ),
            ], 200);
        }

        // Snapshot HANYA untuk konteks aktif (30 hari terakhir + agregasi)
        $contextText = $this->data->getChatContextText();

        $fokus = $ctx === FinanceContext::USAHA_KEBUN
            ? 'KEUANGAN USAHA KEBUN (pemasukan hasil usaha & biaya operasional)'
            : 'KEUANGAN PRIBADI (pengeluaran & kebutuhan pribadi)';

        $istilah = $ctx === FinanceContext::USAHA_KEBUN
            ? "- \"Pemasukan\" = hasil/penjualan usaha kebun.\n".
              "- \"Biaya Operasional\" = pengeluaran usaha (gaji, upah, pupuk, dll.).\n".
              "- \"Laba/Rugi\" = Pemasukan - Biaya Operasional."
            : "- \"Pengeluaran\" = transaksi/kebutuhan pribadi (mis. BPJS, belanja).";

        $system = <<<SYS
Anda adalah "Asisten Keuangan" untuk aplikasi family-keuangan.
Anda sedang membantu pengguna pada konteks: {$ctxLabel} — yaitu {$fokus}.
PENTING: jawab HANYA berdasarkan data konteks ini. JANGAN mencampur atau menyebut konteks lain
(jika ini PRIBADI jangan bahas usaha kebun; jika ini USAHA jangan bahas pengeluaran pribadi).
Jawab singkat, ramah, dalam BAHASA INDONESIA, dan gunakan format Rupiah (Rp 1.250.000).
Jangan mengarang angka — gunakan HANYA data di bawah. Jika data belum cukup, katakan terus terang & beri saran.

Petunjuk istilah:
{$istilah}

Data keuangan (sudah difilter untuk konteks {$ctxLabel}):
$contextText
SYS;

        $messages = [['role' => 'system', 'content' => $system]];

        foreach (($validated['history'] ?? []) as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $validated['message']];

        $resp = $this->ai->chat($messages, ['temperature' => 0.3, 'max_tokens' => 600]);

        if (!$resp['ok']) {
            return response()->json([
                'ok' => false,
                'context' => $ctx,
                'context_label' => $ctxLabel,
                'error' => $resp['error'] ?? 'Gagal memanggil AI.',
            ], 200);
        }

        return response()->json([
            'ok' => true,
            'context' => $ctx,
            'context_label' => $ctxLabel,
            'answer' => trim($resp['text'] ?? ''),
        ]);
    }
}
