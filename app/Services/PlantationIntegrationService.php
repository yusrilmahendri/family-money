<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PlantationIntegrationStatus;
use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PlantationIntegrationService
{
    public function __construct(
        private readonly PlantationServiceClient $client,
        private readonly AuditLogService $auditLogs,
        private readonly HarvestReceivableSyncService $harvestReceivables,
    ) {}

    public function activate(FinanceEntity $entity): PlantationIntegration
    {
        $this->assertBusiness($entity);

        if (! $entity->is_active) {
            throw new InvalidArgumentException('Finance entity harus aktif sebelum Management Kebun diaktifkan.');
        }

        $lock = Cache::lock('plantation-integration-activate:'.$entity->id, 30);

        if (! $lock->block(8)) {
            throw new InvalidArgumentException('Permintaan sedang diproses. Coba beberapa saat lagi.');
        }

        try {
            if ($entity->fresh()?->plantationIntegration) {
                throw new InvalidArgumentException('Entity ini sudah terhubung ke Management Kebun.');
            }

            $remote = $this->client->createEntity([
                'name' => $entity->name,
                'finance_entity_public_id' => $entity->public_id,
                'description' => $entity->description,
            ]);

            $publicId = $remote['public_id'] ?? null;

            if (! is_string($publicId) || $publicId === '') {
                throw new PlantationServiceException('Plantation Service mengembalikan data tidak valid.');
            }

            $integration = PlantationIntegration::query()->create([
                'finance_entity_id' => $entity->id,
                'plantation_entity_public_id' => $publicId,
                'status' => PlantationIntegrationStatus::ACTIVE,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->auditLogs()->record(
                $integration,
                AuditAction::PLANTATION_INTEGRATION_ACTIVATED,
                $entity,
                null,
                [
                    'plantation_entity_public_id' => $publicId,
                    'status' => PlantationIntegrationStatus::ACTIVE->value,
                ],
            );

            return $integration;
        } catch (PlantationServiceException $exception) {
            $this->logSafe($entity, 'activate', $exception);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function sync(FinanceEntity $entity): PlantationIntegration
    {
        $integration = $this->requireIntegration($entity);

        try {
            $this->client->updateEntity($integration->plantation_entity_public_id, [
                'name' => $entity->name,
                'description' => $entity->description,
            ]);

            $integration->update([
                'status' => $integration->status === PlantationIntegrationStatus::INACTIVE
                    ? PlantationIntegrationStatus::INACTIVE
                    : PlantationIntegrationStatus::ACTIVE,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->auditLogs()->record(
                $integration->fresh(),
                AuditAction::PLANTATION_INTEGRATION_SYNCED,
                $entity,
                null,
                [
                    'plantation_entity_public_id' => $integration->plantation_entity_public_id,
                    'name' => $entity->name,
                ],
            );

            return $integration->fresh();
        } catch (PlantationServiceException $exception) {
            $this->markError($integration, $exception);
            $this->logSafe($entity, 'sync', $exception);

            throw $exception;
        }
    }

    /**
     * @return array{posted: int, payments: int, reversed: int, cancelled: int}
     */
    public function syncHarvestReceivables(FinanceEntity $entity): array
    {
        $integration = $this->requireActiveIntegration($entity);

        try {
            $sales = $this->client->listHarvestSales($integration->plantation_entity_public_id);
            $counts = $this->harvestReceivables->ingestPulledSales($entity->fresh() ?? $entity, $sales);

            $integration->update([
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->auditLogs()->record(
                $integration->fresh(),
                AuditAction::PLANTATION_INTEGRATION_SYNCED,
                $entity,
                null,
                [
                    'plantation_entity_public_id' => $integration->plantation_entity_public_id,
                    'harvest_receivables' => $counts,
                ],
            );

            return $counts;
        } catch (PlantationServiceException $exception) {
            $this->markError($integration, $exception);
            $this->logSafe($entity, 'sync-harvest-receivables', $exception);

            throw $exception;
        }
    }

    public function deactivate(FinanceEntity $entity): PlantationIntegration
    {
        $integration = $this->requireIntegration($entity);

        try {
            $this->client->deactivateEntity($integration->plantation_entity_public_id);

            $old = $this->auditLogs()->snapshot($integration);
            $integration->update([
                'status' => PlantationIntegrationStatus::INACTIVE,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->auditLogs()->record(
                $integration->fresh(),
                AuditAction::PLANTATION_INTEGRATION_DEACTIVATED,
                $entity,
                $old,
                $this->auditLogs()->snapshot($integration->fresh()),
            );

            return $integration->fresh();
        } catch (PlantationServiceException $exception) {
            $this->markError($integration, $exception);
            $this->logSafe($entity, 'deactivate', $exception);

            throw $exception;
        }
    }

    public function reactivate(FinanceEntity $entity): PlantationIntegration
    {
        $integration = $this->requireIntegration($entity);

        try {
            $this->client->activateEntity($integration->plantation_entity_public_id);

            $integration->update([
                'status' => PlantationIntegrationStatus::ACTIVE,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->auditLogs()->record(
                $integration->fresh(),
                AuditAction::PLANTATION_INTEGRATION_ACTIVATED,
                $entity,
                null,
                [
                    'plantation_entity_public_id' => $integration->plantation_entity_public_id,
                    'status' => PlantationIntegrationStatus::ACTIVE->value,
                ],
            );

            return $integration->fresh();
        } catch (PlantationServiceException $exception) {
            $this->markError($integration, $exception);
            $this->logSafe($entity, 'reactivate', $exception);

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAccessLinks(FinanceEntity $entity): array
    {
        $integration = $this->requireIntegration($entity);

        return $this->client->listAccessLinks($integration->plantation_entity_public_id);
    }

    /**
     * @param  array{label?: string|null, expires_at?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function issueAccessLink(FinanceEntity $entity, array $payload): array
    {
        $integration = $this->requireIntegration($entity);
        $remote = $this->client->issueAccessLink($integration->plantation_entity_public_id, $payload);

        $this->auditLogs()->record(
            $integration,
            AuditAction::PLANTATION_ACCESS_LINK_CREATED,
            $entity,
            null,
            [
                'plantation_entity_public_id' => $integration->plantation_entity_public_id,
                'access_link_id' => $remote['id'] ?? null,
                'label' => $remote['label'] ?? ($payload['label'] ?? null),
            ],
        );

        return $remote;
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeAccessLink(FinanceEntity $entity, int $tokenId): array
    {
        return $this->mutateLink($entity, $tokenId, AuditAction::PLANTATION_ACCESS_LINK_REVOKED, fn (string $publicId) => $this->client->revokeAccessLink($publicId, $tokenId));
    }

    /**
     * @return array<string, mixed>
     */
    public function activateAccessLink(FinanceEntity $entity, int $tokenId): array
    {
        return $this->mutateLink($entity, $tokenId, AuditAction::PLANTATION_ACCESS_LINK_ACTIVATED, fn (string $publicId) => $this->client->activateAccessLink($publicId, $tokenId));
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateAccessLink(FinanceEntity $entity, int $tokenId): array
    {
        return $this->mutateLink($entity, $tokenId, AuditAction::PLANTATION_ACCESS_LINK_REGENERATED, fn (string $publicId) => $this->client->regenerateAccessLink($publicId, $tokenId));
    }

    public function deleteAccessLink(FinanceEntity $entity, int $tokenId): void
    {
        $integration = $this->requireIntegration($entity);
        $this->client->deleteAccessLink($integration->plantation_entity_public_id, $tokenId);

        $this->auditLogs()->record(
            $integration,
            AuditAction::PLANTATION_ACCESS_LINK_DELETED,
            $entity,
            ['access_link_id' => $tokenId],
            null,
        );
    }

    private function assertBusiness(FinanceEntity $entity): void
    {
        if (! $entity->isBusiness()) {
            throw new InvalidArgumentException('Hanya Finance Entity bertipe BUSINESS yang dapat dihubungkan ke Management Kebun.');
        }
    }

    public function requireActiveIntegration(FinanceEntity $entity): PlantationIntegration
    {
        $integration = $this->requireIntegration($entity);

        if (! $integration->isActive()) {
            throw new InvalidArgumentException('Management Kebun harus aktif sebelum anggaran dikirim ke Plantation.');
        }

        return $integration;
    }

    private function requireIntegration(FinanceEntity $entity): PlantationIntegration
    {
        $this->assertBusiness($entity);

        $integration = $entity->plantationIntegration;

        if (! $integration instanceof PlantationIntegration) {
            throw new InvalidArgumentException('Entity ini belum terhubung ke Management Kebun.');
        }

        return $integration;
    }

    /**
     * @param  callable(string): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function mutateLink(FinanceEntity $entity, int $tokenId, AuditAction $action, callable $callback): array
    {
        $integration = $this->requireIntegration($entity);
        $remote = $callback($integration->plantation_entity_public_id);

        $this->auditLogs()->record(
            $integration,
            $action,
            $entity,
            null,
            [
                'plantation_entity_public_id' => $integration->plantation_entity_public_id,
                'access_link_id' => $tokenId,
            ],
        );

        return $remote;
    }

    private function markError(PlantationIntegration $integration, PlantationServiceException $exception): void
    {
        $integration->update([
            'status' => PlantationIntegrationStatus::ERROR,
            'last_error' => $this->safeError($exception),
        ]);
    }

    private function safeError(PlantationServiceException $exception): string
    {
        return mb_substr($exception->getMessage(), 0, 500);
    }

    private function logSafe(FinanceEntity $entity, string $operation, PlantationServiceException $exception): void
    {
        Log::warning('plantation.integration_failed', [
            'operation' => $operation,
            'finance_entity_public_id' => $entity->public_id,
            'status' => $exception->status,
        ]);
    }

    private function auditLogs(): AuditLogService
    {
        return $this->auditLogs;
    }
}
