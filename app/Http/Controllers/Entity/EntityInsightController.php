<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Models\FinanceEntity;
use App\Services\AiService;
use App\Services\Insight\EntityAiChatService;
use App\Services\Insight\EntityFinancialInsightService;
use App\Services\Insight\EntityFinancialSummaryService;
use App\Services\Insight\EntityInsightDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntityInsightController extends Controller
{
    public function __construct(
        private readonly EntityInsightDataService $data,
        private readonly EntityAiChatService $chat,
        private readonly EntityFinancialInsightService $insight,
        private readonly AiService $ai,
    ) {}

    public function index(Request $request, FinanceEntity $financeEntity): View
    {
        $filter = $this->periodFilter($request);
        $structured = $this->insight->make($financeEntity, $filter);
        $payload = $this->data->payload($financeEntity, $structured['filter']['from'], $structured['filter']['to']);

        return view('entity.insight.index', [
            'entity' => $financeEntity,
            'payload' => $payload,
            'hasData' => $this->data->hasData($financeEntity),
            'aiReady' => $this->ai->isConfigured(),
            'title' => 'Insight AI',
            'assistantTitle' => $this->data->assistantTitle($financeEntity),
            'suggestions' => $this->data->suggestedQuestions($financeEntity),
            'welcomeChips' => $this->data->welcomeChips($financeEntity),
            'chatUrl' => route('entity.ai.chat', $financeEntity),
            'insight' => $structured['summary'],
            'anomalies' => $structured['anomalies'],
            'periodKey' => $structured['filter']['key'],
            'periodFrom' => $structured['filter']['from'],
            'periodTo' => $structured['filter']['to'],
            'explainPrompt' => EntityFinancialSummaryService::EXPLAIN_PROMPT,
        ]);
    }

    public function summary(FinanceEntity $financeEntity): JsonResponse
    {
        $payload = $this->data->payload($financeEntity);

        if (! $this->ai->isConfigured()) {
            return response()->json([
                'ok' => false,
                'payload' => $payload,
                'error' => 'AI belum dikonfigurasi.',
            ]);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $resp = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'Anda analis keuangan. Analisis HANYA entity '.$financeEntity->name.' ('.$financeEntity->type->value.'). Jangan memakai data entity lain. Bahasa Indonesia, format Rupiah.',
            ],
            [
                'role' => 'user',
                'content' => "Ringkas posisi keuangan entity ini berdasarkan JSON berikut:\n".$json,
            ],
        ], ['temperature' => 0.3, 'max_tokens' => 700]);

        return response()->json([
            'ok' => (bool) ($resp['ok'] ?? false),
            'payload' => $payload,
            'summary' => trim((string) ($resp['text'] ?? '')),
            'error' => $resp['error'] ?? null,
        ]);
    }

    public function chat(Request $request, FinanceEntity $financeEntity): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'history' => ['sometimes', 'array', 'max:16'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
            'period' => ['sometimes', 'nullable', 'in:month,last_month,year,custom'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ]);

        $filter = null;
        if (! empty($validated['period'])) {
            $filter = [
                'key' => $validated['period'],
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ];
        }

        $result = $this->chat->ask(
            $financeEntity,
            trim($validated['message']),
            $validated['history'] ?? [],
            $filter,
        );

        return response()->json($result);
    }

    /**
     * @return array{key: string, from: ?string, to: ?string}
     */
    private function periodFilter(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:month,last_month,year,custom'],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:from'],
        ]);

        return [
            'key' => $validated['period'] ?? 'month',
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }
}
