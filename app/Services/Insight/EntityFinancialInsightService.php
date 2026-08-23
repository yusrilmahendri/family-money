<?php

namespace App\Services\Insight;

use App\Models\FinanceEntity;

class EntityFinancialInsightService
{
    public function __construct(
        private readonly EntityFinancialSummaryService $summaries,
        private readonly FinancialAnomalyDetectionService $anomalies,
    ) {}

    /**
     * @param  array{key?: string, from?: ?string, to?: ?string}  $filter
     * @return array{
     *     filter: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     anomalies: array<string, mixed>,
     *     ai_context: array<string, mixed>
     * }
     */
    public function make(FinanceEntity $entity, array $filter = []): array
    {
        $resolved = $this->summaries->resolve(
            (string) ($filter['key'] ?? 'month'),
            $filter['from'] ?? null,
            $filter['to'] ?? null,
        );
        $summary = $this->summaries->forPeriod($entity, $resolved);
        $detected = $this->anomalies->detect($entity, $summary);

        return [
            'filter' => $resolved,
            'summary' => [
                'entity' => $summary['entity'],
                'period' => $summary['period'],
                'metrics' => $summary['metrics'],
                'highlights' => $summary['highlights'],
                'narrative' => $summary['narrative'],
            ],
            'anomalies' => $detected,
            'ai_context' => [
                'ringkasan' => $this->summaries->compact($summary),
                'anomali' => $this->anomalies->compact($detected),
            ],
        ];
    }

    /**
     * @return array{cash_flow: float, anomaly_count: int, critical_count: int, period_label: string}
     */
    public function dashboardPreview(FinanceEntity $entity): array
    {
        return $this->anomalies->dashboardPreview($entity);
    }
}
