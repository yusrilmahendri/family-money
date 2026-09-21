<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PlantationOperatingBudgetStatus;
use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PlantationOperatingBudgetService
{
    public function __construct(
        private readonly PlantationServiceClient $client,
        private readonly PlantationIntegrationService $integrations,
        private readonly AuditLogService $auditLogs,
        private readonly BudgetAvailabilityService $availability,
    ) {}

    /**
     * @param  array{name: string, period_start: string, period_end: string, allocated_amount: float}  $data
     */
    public function create(FinanceEntity $entity, array $data): PlantationOperatingBudget
    {
        $this->integrations->requireActiveIntegration($entity);

        $budget = DB::transaction(function () use ($entity, $data): PlantationOperatingBudget {
            $entity = $this->availability->lockEntity($entity);
            $this->availability->assertCanAllocate($entity, (float) $data['allocated_amount']);

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

            return $budget;
        });

        $this->push($budget->fresh() ?? $budget, AuditAction::PLANTATION_OPERATING_BUDGET_SYNCED, operation: 'create');

        return $budget->fresh() ?? $budget;
    }

    /**
     * @param  array{name: string, period_start: string, period_end: string, allocated_amount: float}  $data
     */
    public function update(PlantationOperatingBudget $budget, array $data): PlantationOperatingBudget
    {
        $entity = $budget->financeEntity;
        $this->integrations->requireActiveIntegration($entity);

        $old = DB::transaction(function () use ($entity, $budget, $data): array {
            $this->availability->lockEntity($entity);
            $locked = PlantationOperatingBudget::query()->whereKey($budget->id)->lockForUpdate()->firstOrFail();
            $this->availability->assertCanAllocate(
                $entity,
                (float) $data['allocated_amount'],
                $locked,
            );

            return [
                'name' => $locked->name,
                'allocated_amount' => (string) $locked->allocated_amount,
                'period_start' => $locked->period_start?->toDateString(),
                'period_end' => $locked->period_end?->toDateString(),
            ];
        });

        return $this->push(
            $budget,
            AuditAction::PLANTATION_OPERATING_BUDGET_UPDATED,
            $data,
            $old,
            'update',
        );
    }

    public function sync(PlantationOperatingBudget $budget): PlantationOperatingBudget
    {
        $this->integrations->requireActiveIntegration($budget->financeEntity);

        return $this->push($budget, AuditAction::PLANTATION_OPERATING_BUDGET_SYNCED, operation: 'sync');
    }

    /**
     * @param  array{name: string, period_start: ?string, period_end: ?string, allocated_amount: float}|null  $data
     * @param  array<string, mixed>|null  $old
     */
    private function push(
        PlantationOperatingBudget $budget,
        AuditAction $action,
        ?array $data = null,
        ?array $old = null,
        string $operation = 'sync',
    ): PlantationOperatingBudget {
        $payload = $data ?? [
            'name' => $budget->name,
            'period_start' => $budget->period_start?->toDateString(),
            'period_end' => $budget->period_end?->toDateString(),
            'allocated_amount' => (float) $budget->allocated_amount,
        ];

        try {
            $this->pushToPlantation($budget, $payload);
        } catch (PlantationServiceException $exception) {
            Log::warning('plantation.budget_sync_failed', [
                'operation' => $operation,
                'finance_entity_public_id' => $budget->financeEntity?->public_id,
                'budget_public_id' => $budget->public_id,
                'status' => $exception->status,
                'error_type' => $exception->errorType,
            ]);

            try {
                $budget->update([
                    'status' => PlantationOperatingBudgetStatus::SYNC_ERROR,
                    'last_error' => mb_substr($exception->userMessage(), 0, 500),
                ]);
            } catch (Throwable $stateException) {
                Log::warning('plantation.budget_sync_state_update_failed', [
                    'finance_entity_public_id' => $budget->financeEntity?->public_id,
                    'budget_public_id' => $budget->public_id,
                    'exception_class' => $stateException::class,
                ]);
            }

            throw $exception;
        }

        $attributes = [
            'status' => PlantationOperatingBudgetStatus::ACTIVE,
            'last_synced_at' => now(),
            'last_error' => null,
        ];

        if ($data !== null) {
            $attributes['name'] = $data['name'];
            $attributes['period_start'] = $data['period_start'];
            $attributes['period_end'] = $data['period_end'];
            $attributes['allocated_amount'] = $data['allocated_amount'];
        }

        $budget->update($attributes);

        if ($action === AuditAction::PLANTATION_OPERATING_BUDGET_UPDATED && $data !== null) {
            $this->auditLogs->record(
                $budget,
                $action,
                $budget->financeEntity,
                $old,
                [
                    'name' => $data['name'],
                    'allocated_amount' => (string) $data['allocated_amount'],
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                ],
            );
        } else {
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
        }

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
