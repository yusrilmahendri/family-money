<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PlantationOperatingBudgetStatus;
use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PlantationOperatingBudgetService
{
    public function __construct(
        private readonly PlantationServiceClient $client,
        private readonly PlantationIntegrationService $integrations,
        private readonly AuditLogService $auditLogs,
    ) {}

    /**
     * @param  array{name: string, period_start: string, period_end: string, allocated_amount: float}  $data
     */
    public function create(FinanceEntity $entity, array $data): PlantationOperatingBudget
    {
        $this->integrations->requireActiveIntegration($entity);

        $budget = PlantationOperatingBudget::query()->create([
            'finance_entity_id' => $entity->id,
            'name' => $data['name'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'allocated_amount' => $data['allocated_amount'],
            'status' => PlantationOperatingBudgetStatus::DRAFT,
        ]);

        $this->auditLogs->record(
            $budget,
            AuditAction::PLANTATION_OPERATING_BUDGET_CREATED,
            $entity,
            null,
            [
                'public_id' => $budget->public_id,
                'name' => $budget->name,
                'allocated_amount' => (string) $budget->allocated_amount,
                'period_start' => $budget->period_start?->toDateString(),
                'period_end' => $budget->period_end?->toDateString(),
            ],
        );

        $this->push($budget->fresh() ?? $budget, AuditAction::PLANTATION_OPERATING_BUDGET_SYNCED);

        return $budget->fresh() ?? $budget;
    }

    /**
     * @param  array{name: string, period_start: string, period_end: string, allocated_amount: float}  $data
     */
    public function update(PlantationOperatingBudget $budget, array $data): PlantationOperatingBudget
    {
        $entity = $budget->financeEntity;
        $this->integrations->requireActiveIntegration($entity);

        $old = [
            'name' => $budget->name,
            'allocated_amount' => (string) $budget->allocated_amount,
            'period_start' => $budget->period_start?->toDateString(),
            'period_end' => $budget->period_end?->toDateString(),
        ];

        $this->pushToPlantation($budget, $data);

        $budget->update([
            'name' => $data['name'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'allocated_amount' => $data['allocated_amount'],
            'status' => PlantationOperatingBudgetStatus::ACTIVE,
            'last_synced_at' => now(),
            'last_error' => null,
        ]);

        $this->auditLogs->record(
            $budget,
            AuditAction::PLANTATION_OPERATING_BUDGET_UPDATED,
            $entity,
            $old,
            [
                'name' => $data['name'],
                'allocated_amount' => (string) $data['allocated_amount'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
            ],
        );

        return $budget->fresh() ?? $budget;
    }

    public function sync(PlantationOperatingBudget $budget): PlantationOperatingBudget
    {
        $this->integrations->requireActiveIntegration($budget->financeEntity);

        return $this->push($budget, AuditAction::PLANTATION_OPERATING_BUDGET_SYNCED);
    }

    private function push(PlantationOperatingBudget $budget, AuditAction $action): PlantationOperatingBudget
    {
        try {
            $this->pushToPlantation($budget, [
                'name' => $budget->name,
                'period_start' => $budget->period_start?->toDateString(),
                'period_end' => $budget->period_end?->toDateString(),
                'allocated_amount' => (float) $budget->allocated_amount,
            ]);
        } catch (PlantationServiceException $exception) {
            $budget->update([
                'status' => PlantationOperatingBudgetStatus::SYNC_ERROR,
                'last_error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            Log::warning('plantation.budget_sync_failed', [
                'finance_entity_public_id' => $budget->financeEntity?->public_id,
                'budget_public_id' => $budget->public_id,
                'status' => $exception->status,
            ]);

            throw $exception;
        }

        $budget->update([
            'status' => PlantationOperatingBudgetStatus::ACTIVE,
            'last_synced_at' => now(),
            'last_error' => null,
        ]);

        $this->auditLogs->record(
            $budget,
            $action,
            $budget->financeEntity,
            null,
            [
                'public_id' => $budget->public_id,
                'status' => PlantationOperatingBudgetStatus::ACTIVE->value,
            ],
        );

        return $budget->fresh() ?? $budget;
    }

    /**
     * @param  array{name: string, period_start: ?string, period_end: ?string, allocated_amount: float}  $data
     */
    private function pushToPlantation(PlantationOperatingBudget $budget, array $data): void
    {
        $entity = $budget->financeEntity;

        if (! $entity instanceof FinanceEntity || blank($entity->public_id)) {
            throw new InvalidArgumentException('Finance Entity tidak valid.');
        }

        $this->client->upsertBudgetAllocation($budget->public_id, [
            'budget_public_id' => $budget->public_id,
            'finance_entity_public_id' => $entity->public_id,
            'name' => $data['name'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'allocated_amount' => $data['allocated_amount'],
        ]);
    }
}
