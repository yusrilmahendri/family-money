<?php

namespace App\Support;

use App\Enums\PortalAccessResourceType;
use App\Models\FinanceEntity;
use App\Models\PortalAccessGrant;
use App\Models\PortalAccessToken;
use Illuminate\Support\Collection;

/**
 * Session capabilities granted by a PortalAccess credential.
 *
 * Card visibility is derived from these grants after live revalidation.
 * It is not an authorization source of its own.
 */
class PortalAccessSession
{
    public const SESSION_KEY = 'portal_access';

    public static function grant(PortalAccessToken $access): void
    {
        $ids = self::tokenIds();
        $id = (int) $access->id;

        if (! in_array($id, $ids, true)) {
            $ids[] = $id;
        }

        session([self::SESSION_KEY => [
            'token_ids' => $ids,
            'granted_at' => now()->toIso8601String(),
        ]]);

        $access->loadMissing(['grants.financeEntity']);

        foreach ($access->grants as $grant) {
            if (! $grant->isFinance()) {
                continue;
            }

            $entity = $grant->financeEntity;

            if ($entity instanceof FinanceEntity) {
                FinanceEntityAccess::grantFromPortal($entity, $access);
            }
        }
    }

    /**
     * @return list<int>
     */
    public static function tokenIds(): array
    {
        $raw = session(self::SESSION_KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $ids = $raw['token_ids'] ?? [];

        if (! is_array($ids) && isset($raw['token_id'])) {
            $ids = [$raw['token_id']];
        }

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @return Collection<int, PortalAccessToken>
     */
    public static function usableTokens(): Collection
    {
        $ids = self::tokenIds();

        if ($ids === []) {
            return collect();
        }

        return PortalAccessToken::query()
            ->with(['grants.financeEntity.plantationIntegration'])
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (PortalAccessToken $token) => $token->isUsable())
            ->values();
    }

    public static function isValid(): bool
    {
        return self::usableTokens()->isNotEmpty();
    }

    public static function hasFinanceGrant(FinanceEntity $entity): bool
    {
        return self::hasGrant(PortalAccessResourceType::FINANCE, $entity);
    }

    public static function hasPlantationGrant(FinanceEntity $entity): bool
    {
        return self::hasGrant(PortalAccessResourceType::PLANTATION, $entity);
    }

    public static function hasGrant(PortalAccessResourceType $type, FinanceEntity $entity): bool
    {
        foreach (self::usableTokens() as $token) {
            if ($token->hasGrant($type, $entity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, FinanceEntity>
     */
    public static function authorizedFinanceEntities(): Collection
    {
        return self::entitiesWithGrant(PortalAccessResourceType::FINANCE)
            ->filter(fn (FinanceEntity $entity) => $entity->is_active)
            ->values();
    }

    /**
     * @return Collection<int, FinanceEntity>
     */
    public static function authorizedPlantationEntities(): Collection
    {
        return self::entitiesWithGrant(PortalAccessResourceType::PLANTATION)
            ->filter(fn (FinanceEntity $entity) => $entity->is_active && $entity->hasActivePlantationIntegration())
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function financeEntityPublicIds(): array
    {
        return self::authorizedFinanceEntities()
            ->map(fn (FinanceEntity $entity) => $entity->public_id)
            ->all();
    }

    /**
     * @return Collection<int, FinanceEntity>
     */
    private static function entitiesWithGrant(PortalAccessResourceType $type): Collection
    {
        $seen = [];
        $entities = collect();

        foreach (self::usableTokens() as $token) {
            foreach ($token->grants as $grant) {
                if (! $grant instanceof PortalAccessGrant || $grant->resource_type !== $type) {
                    continue;
                }

                $entity = $grant->financeEntity;

                if (! $entity instanceof FinanceEntity || isset($seen[$entity->id])) {
                    continue;
                }

                $seen[$entity->id] = true;
                $entities->push($entity);
            }
        }

        return $entities->sortBy('name')->values();
    }
}
