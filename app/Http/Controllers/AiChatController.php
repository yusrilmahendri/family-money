<?php

namespace App\Http\Controllers;

use App\Service\AiService;
use App\Service\FinanceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(
        protected AiService $ai,
        protected FinanceContextService $context,
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'history' => ['array'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        if (!$this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'error' => 'Fitur AI belum aktif. Hubungi admin untuk mengatur OPENAI_API_KEY di file .env.',
            ], 200);
        }

        $contextText = $this->context->snapshotAsText();

        $system = <<<SYS
Anda adalah "Asisten Keuangan Keluarga" untuk aplikasi family-keuangan.
Jawab dengan singkat, ramah, dan dalam BAHASA INDONESIA.
Selalu gunakan format Rupiah (contoh: Rp 1.250.000) untuk uang.
Jangan mengarang angka — gunakan HANYA data yang diberikan di bawah.
Jika data tidak ada / belum cukup, jawab terus-terang & beri saran cara mengisi data tsb.

Konteks data keuangan pengguna (JSON):
$contextText

Petunjuk istilah:
- "Saldo" = total dana (sudah termasuk pemasukan usaha yang auto-sync).
- "Anggaran" = plafon belanja per Jenis Usaha.
- "Biaya Operasional" = aktivitas anggaran (gaji, upah, pupuk, dll.) yang mengurangi anggaran.
- "Transaksi Pribadi" = pengeluaran non-usaha (mis. BPJS) yang mengurangi saldo.
- "Saldo Bebas" = Saldo - Anggaran - Transaksi Pribadi.
- "Laba/Rugi" = Pemasukan Usaha - Biaya Operasional pada periode tertentu.
SYS;

        $messages = [['role' => 'system', 'content' => $system]];

        foreach (($validated['history'] ?? []) as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $validated['message']];

        $resp = $this->ai->chat($messages, ['temperature' => 0.3, 'max_tokens' => 600]);

        if (!$resp['ok']) {
            return response()->json(['ok' => false, 'error' => $resp['error'] ?? 'Gagal memanggil AI.'], 200);
        }

        return response()->json([
            'ok' => true,
            'answer' => trim($resp['text'] ?? ''),
        ]);
    }
}
