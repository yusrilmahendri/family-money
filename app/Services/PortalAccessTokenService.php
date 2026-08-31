<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PortalAccessResourceType;
use App\Models\FinanceEntity;
use App\Models\PlantationIntegration;
use App\Models\PortalAccessGrant;
use App\Models\PortalAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PortalAccessTokenService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  list<array{resource_type: PortalAccessResourceType|string, finance_entity_id: int}|string>  $grants
     * @return array{0: PortalAccessToken, 1: string}
     */
    public function issue(string $name, array $grants, ?Carbon $expiresAt = null, bool $audit = true): array
    {
        $resolved = $this->resolveGrants($grants);
        $plain = PortalAccessToken::generatePlainToken();

        $token = DB::transaction(function () use ($name, $resolved, $expiresAt, $plain): PortalAccessToken {
            $token = new PortalAccessToken([
                'name' => $name,
                'is_active' => true,
                'expires_at' => $expiresAt,
            ]);
            $token->token_hash = PortalAccessToken::hashToken($plain);
            $token->save();

            $this->syncGrants($token, $resolved);

            return $token->load('grants.financeEntity');
        });

        if ($audit) {
            $this->audit->recordCreated($token, null, extra: [
                'grants' => $this->grantSnapshots($token),
            ]);
        }

        return [$token, $plain];
    }

    /**
     * @return array{0: PortalAccessToken, 1: string}
     */
    public function regenerate(PortalAccessToken $token): array
    {
        return DB::transaction(function () use ($token) {
            $old = array_merge($this->audit->snapshot($token), [
                'grants' => $this->grantSnapshots($token),
            ]);
            $token->update(['is_active' => false]);

            $grantPayload = $token->grants()
                ->get(['resource_type', 'finance_entity_id'])
                ->map(fn (PortalAccessGrant $grant) => [
                    'resource_type' => $grant->resource_type,
                    'finance_entity_id' => (int) $grant->finance_entity_id,
                ])
                ->all();

            [$replacement, $plain] = $this->issue(
                $token->name,
                $grantPayload,
                $token->expires_at,
                false
            );

            $this->audit->record(
                $replacement,
                AuditAction::REGENERATE,
                null,
                $old + ['revoked_portal_access_id' => $token->id],
                $this->audit->snapshot($replacement) + [
                    'replacement_portal_access_id' => $replacement->id,
                    'grants' => $this->grantSnapshots($replacement),
                ],
            );

            return [$replacement, $plain];
        });
    }

    public function revoke(PortalAccessToken $token): PortalAccessToken
    {
        $old = $this->audit->snapshot($token);
        $token->update(['is_active' => false]);
        $fresh = $token->fresh();
        $this->audit->record($fresh, AuditAction::REVOKE, null, $old, $this->audit->snapshot($fresh));

        return $fresh;
    }

    public function activate(PortalAccessToken $token): PortalAccessToken
    {
        $old = $this->audit->snapshot($token);
        $token->update(['is_active' => true]);
        $fresh = $token->fresh();
        $this->audit->record($fresh, AuditAction::ACTIVATE, null, $old, $this->audit->snapshot($fresh));

        return $fresh;
    }

    /**
     * @param  array{name?: string, expires_at?: mixed, grants?: list<mixed>}  $data
     */
    public function update(PortalAccessToken $token, array $data): PortalAccessToken
    {
        return DB::transaction(function () use ($token, $data) {
            $old = array_merge($this->audit->snapshot($token), [
                'grants' => $this->grantSnapshots($token),
            ]);

            $meta = [];

            if (array_key_exists('name', $data)) {
                $meta['name'] = $data['name'];
            }

            if (array_key_exists('expires_at', $data)) {
                $meta['expires_at'] = $data['expires_at'];
            }

            if ($meta !== []) {
                $token->update($meta);
            }

            if (array_key_exists('grants', $data)) {
                $this->syncGrants($token, $this->resolveGrants($data['grants'] ?? []));
            }

            $fresh = $token->fresh()->load('grants.financeEntity');
            $this->audit->recordUpdated($fresh, $old, extra: [
                'grants' => $this->grantSnapshots($fresh),
            ]);

            return $fresh;
        });
    }

    public function findUsableByPlainToken(string $plainToken): ?PortalAccessToken
    {
        $token = PortalAccessToken::query()
            ->with(['grants.financeEntity.plantationIntegration'])
            ->where('token_hash', PortalAccessToken::hashToken($plainToken))
            ->first();

        if (! $token instanceof PortalAccessToken || ! $token->isUsable()) {
            return null;
        }

        return $token;
    }

    public function markUsed(PortalAccessToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function delete(PortalAccessToken $token): void
    {
        $old = array_merge($this->audit->snapshot($token), [
            'portal_access_id' => (int) $token->id,
            'portal_access_public_id' => $token->public_id,
            'grants' => $this->grantSnapshots($token),
        ]);

        $this->audit->record(
            $token,
            AuditAction::ACCESS_LINK_DELETED,
            null,
            $old,
        );

        $token->delete();
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     resource_type: string,
     *     finance_entity_id: int,
     *     label: string,
     *     hint: string|null
     * }>
     */
    public function availableResources(): Collection
    {
        $entities = FinanceEntity::query()
            ->with('plantationIntegration')
            ->orderBy('name')
            ->get();

        $resources = collect();

        foreach ($entities as $entity) {
            $financeHint = $entity->is_active ? null : 'Entity nonaktif';

            $resources->push([
                'key' => 'finance:'.$entity->public_id,
                'resource_type' => PortalAccessResourceType::FINANCE->value,
                'finance_entity_id' => (int) $entity->id,
                'label' => $entity->isFamily()
                    ? 'Keuangan Keluarga — '.$entity->name
                    : 'Keuangan Usaha — '.$entity->name,
                'hint' => $financeHint,
            ]);

            if (! $entity->isBusiness() || ! $entity->plantationIntegration instanceof PlantationIntegration) {
                continue;
            }

            $plantationHint = null;

            if (! $entity->is_active) {
                $plantationHint = 'Entity nonaktif';
            } elseif (! $entity->plantationIntegration->isActive()) {
                $plantationHint = 'Integrasi kebun nonaktif';
            }

            $resources->push([
                'key' => 'plantation:'.$entity->public_id,
                'resource_type' => PortalAccessResourceType::PLANTATION->value,
                'finance_entity_id' => (int) $entity->id,
                'label' => 'Management Kebun — '.$entity->name,
                'hint' => $plantationHint,
            ]);
        }

        return $resources;
    }

    /**
     * @param  list<mixed>  $grants
     * @return list<array{resource_type: PortalAccessResourceType, finance_entity_id: int}>
     */
    public function resolveGrants(array $grants): array
    {
        $resolved = [];
        $seen = [];

        foreach ($grants as $grant) {
            $spec = $this->normalizeGrant($grant);
            $key = $spec['resource_type']->value.':'.$spec['finance_entity_id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $resolved[] = $spec;
        }

        if ($resolved === []) {
            throw new InvalidArgumentException('Pilih minimal satu layanan.');
        }

        return $resolved;
    }

    /**
     * @param  list<array{resource_type: PortalAccessResourceType, finance_entity_id: int}>  $grants
     */
    private function syncGrants(PortalAccessToken $token, array $grants): void
    {
        $token->grants()->delete();

        foreach ($grants as $grant) {
            $token->grants()->create([
                'resource_type' => $grant['resource_type'],
                'finance_entity_id' => $grant['finance_entity_id'],
            ]);
        }
    }

    /**
     * @return list<array{resource_type: string, finance_entity_id: int}>
     */
    private function grantSnapshots(PortalAccessToken $token): array
    {
        return $token->grants()
            ->orderBy('id')
            ->get(['resource_type', 'finance_entity_id'])
            ->map(fn (PortalAccessGrant $grant) => [
                'resource_type' => $grant->resource_type instanceof PortalAccessResourceType
                    ? $grant->resource_type->value
                    : (string) $grant->resource_type,
                'finance_entity_id' => (int) $grant->finance_entity_id,
            ])
            ->all();
    }

    /**
     * @return array{resource_type: PortalAccessResourceType, finance_entity_id: int}
     */
    private function normalizeGrant(mixed $grant): array
    {
        if (is_string($grant)) {
            return $this->grantFromKey($grant);
        }

        if (! is_array($grant)) {
            throw new InvalidArgumentException('Grant layanan tidak valid.');
        }

        if (isset($grant['key']) && is_string($grant['key'])) {
            return $this->grantFromKey($grant['key']);
        }

        $type = $grant['resource_type'] ?? null;

        if ($type instanceof PortalAccessResourceType) {
            $resourceType = $type;
        } else {
            $resourceType = PortalAccessResourceType::tryFrom((string) $type);
        }

        $entityId = (int) ($grant['finance_entity_id'] ?? 0);

        if ($resourceType === null || $entityId < 1) {
            throw new InvalidArgumentException('Grant layanan tidak valid.');
        }

        $entity = FinanceEntity::query()->with('plantationIntegration')->find($entityId);

        return $this->assertGrantAllowed($resourceType, $entity);
    }

    /**
     * @return array{resource_type: PortalAccessResourceType, finance_entity_id: int}
     */
    private function grantFromKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        $typeValue = $parts[0] ?? '';
        $publicId = $parts[1] ?? '';
        $resourceType = PortalAccessResourceType::tryFrom($typeValue);

        if ($resourceType === null || $publicId === '') {
            throw new InvalidArgumentException('Grant layanan tidak valid.');
        }

        $entity = FinanceEntity::query()
            ->with('plantationIntegration')
            ->where('public_id', $publicId)
            ->first();

        return $this->assertGrantAllowed($resourceType, $entity);
    }

    /**
     * @return array{resource_type: PortalAccessResourceType, finance_entity_id: int}
     */
    private function assertGrantAllowed(PortalAccessResourceType $type, ?FinanceEntity $entity): array
    {
        if (! $entity instanceof FinanceEntity) {
            throw new InvalidArgumentException('Layanan yang dipilih tidak ditemukan.');
        }

        if ($type === PortalAccessResourceType::PLANTATION) {
            if (! $entity->isBusiness()) {
                throw new InvalidArgumentException('Management Kebun hanya dapat diberikan ke FinanceEntity BUSINESS.');
            }

            if (! $entity->plantationIntegration instanceof PlantationIntegration) {
                throw new InvalidArgumentException('Management Kebun memerlukan integrasi kebun pada entity tersebut.');
            }
        }

        return [
            'resource_type' => $type,
            'finance_entity_id' => (int) $entity->id,
        ];
    }
}
