<?php

namespace App\Support;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\PortalAccessToken;
use Illuminate\Support\Collection;

/**
 * Session capabilities for private entity access.
 *
 * This is NOT Auth::login and is independent of FinanceContext.
 * Multiple entities can be authorized in the same browser session.
 */
class FinanceEntityAccess
{
    public const SESSION_KEY = 'finance_entity_access';

    public const SOURCE_ENTITY = 'entity';

    public const SOURCE_PORTAL = 'portal';

    /**
     * @return array<string, array{token_id: int, granted_at: string, source?: string}>
     */
    public static function all(): array
    {
        $access = session(self::SESSION_KEY, []);

        return is_array($access) ? $access : [];
    }

    public static function grant(FinanceEntity $entity, FinanceEntityAccessToken $token): void
    {
        self::store($entity, (int) $token->id, self::SOURCE_ENTITY);
    }

    public static function grantFromPortal(FinanceEntity $entity, PortalAccessToken $token): void
    {
        self::store($entity, (int) $token->id, self::SOURCE_PORTAL);
    }

    public static function tokenIdFor(FinanceEntity $entity): ?int
    {
        $entry = self::all()[$entity->public_id] ?? null;

        if (! is_array($entry) || ! isset($entry['token_id'])) {
            return null;
        }

        return (int) $entry['token_id'];
    }

    public static function hasCapability(FinanceEntity $entity): bool
    {
        return self::tokenIdFor($entity) !== null
            || PortalAccessSession::hasFinanceGrant($entity);
    }

    public static function sourceFor(FinanceEntity $entity): ?string
    {
        $entry = self::all()[$entity->public_id] ?? null;

        if (! is_array($entry) || ! isset($entry['token_id'])) {
            return PortalAccessSession::hasFinanceGrant($entity) ? self::SOURCE_PORTAL : null;
        }

        return is_string($entry['source'] ?? null) ? $entry['source'] : self::SOURCE_ENTITY;
    }

    /**
     * Re-validate the stored token on every protected request.
     * Revoke / expiry / entity deactivation take effect immediately.
     */
    public static function isAuthorized(FinanceEntity $entity): bool
    {
        if (! $entity->is_active) {
            return false;
        }

        $tokenId = self::tokenIdFor($entity);
        $source = self::sourceFor($entity);

        if ($tokenId !== null && $source === self::SOURCE_PORTAL && self::portalTokenAuthorizes($entity, $tokenId)) {
            return true;
        }

        if ($tokenId !== null && $source !== self::SOURCE_PORTAL && self::entityTokenAuthorizes($entity, $tokenId)) {
            return true;
        }

        return PortalAccessSession::hasFinanceGrant($entity);
    }

    /**
     * Legacy entity-token capability (not a PortalAccess finance grant).
     */
    public static function hasLegacyEntityCapability(FinanceEntity $entity): bool
    {
        $tokenId = self::tokenIdFor($entity);

        if ($tokenId === null || self::sourceFor($entity) === self::SOURCE_PORTAL) {
            return false;
        }

        return self::entityTokenAuthorizes($entity, $tokenId);
    }

    /**
     * @return Collection<int, FinanceEntity>
     */
    public static function authorizedEntities(): Collection
    {
        $publicIds = array_values(array_unique(array_merge(
            array_keys(self::all()),
            PortalAccessSession::financeEntityPublicIds(),
        )));

        if ($publicIds === []) {
            return collect();
        }

        return FinanceEntity::query()
            ->whereIn('public_id', $publicIds)
            ->orderBy('name')
            ->get()
            ->filter(fn (FinanceEntity $entity) => self::isAuthorized($entity))
            ->values();
    }

    private static function store(FinanceEntity $entity, int $tokenId, string $source): void
    {
        $access = self::all();
        $access[$entity->public_id] = [
            'source' => $source,
            'token_id' => $tokenId,
            'granted_at' => now()->toIso8601String(),
        ];

        session([self::SESSION_KEY => $access]);
    }

    private static function entityTokenAuthorizes(FinanceEntity $entity, int $tokenId): bool
    {
        $token = FinanceEntityAccessToken::query()
            ->with('financeEntity')
            ->find($tokenId);

        if (! $token instanceof FinanceEntityAccessToken) {
            return false;
        }

        if ((int) $token->finance_entity_id !== (int) $entity->id) {
            return false;
        }

        return $token->isUsable();
    }

    private static function portalTokenAuthorizes(FinanceEntity $entity, int $tokenId): bool
    {
        $token = PortalAccessToken::query()
            ->with('grants')
            ->find($tokenId);

        if (! $token instanceof PortalAccessToken || ! $token->isUsable()) {
            return false;
        }

        return $token->hasFinanceGrant($entity);
    }

    /**
     * Active BUSINESS entities the private session can send capital to.
     *
     * @return Collection<int, FinanceEntity>
     */
    public static function capitalDestinations(): Collection
    {
        return self::authorizedEntities()
            ->filter(fn (FinanceEntity $entity) => $entity->type === FinanceEntityType::BUSINESS
                && $entity->is_active
                && $entity->activeAccounts()->exists())
            ->values();
    }

    /**
     * Active FAMILY entities the private session can send prive to.
     *
     * @return Collection<int, FinanceEntity>
     */
    public static function withdrawalDestinations(): Collection
    {
        return self::authorizedFamilyDestinations();
    }

    /**
     * Active FAMILY entities the private session can send profit to.
     *
     * @return Collection<int, FinanceEntity>
     */
    public static function distributionDestinations(): Collection
    {
        return self::authorizedFamilyDestinations();
    }

    /**
     * @return Collection<int, FinanceEntity>
     */
    private static function authorizedFamilyDestinations(): Collection
    {
        return self::authorizedEntities()
            ->filter(fn (FinanceEntity $entity) => $entity->type === FinanceEntityType::FAMILY
                && $entity->is_active
                && $entity->activeAccounts()->exists())
            ->values();
    }
}
