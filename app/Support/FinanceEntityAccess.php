<?php

namespace App\Support;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
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

    /**
     * @return array<string, array{token_id: int, granted_at: string}>
     */
    public static function all(): array
    {
        $access = session(self::SESSION_KEY, []);

        return is_array($access) ? $access : [];
    }

    public static function grant(FinanceEntity $entity, FinanceEntityAccessToken $token): void
    {
        $access = self::all();
        $access[$entity->public_id] = [
            'token_id' => (int) $token->id,
            'granted_at' => now()->toIso8601String(),
        ];

        session([self::SESSION_KEY => $access]);
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
        return self::tokenIdFor($entity) !== null;
    }

    /**
     * Re-validate the stored token on every protected request.
     * Revoke / expiry / entity deactivation take effect immediately.
     */
    public static function isAuthorized(FinanceEntity $entity): bool
    {
        $tokenId = self::tokenIdFor($entity);

        if ($tokenId === null) {
            return false;
        }

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

    /**
     * @return Collection<int, FinanceEntity>
     */
    public static function authorizedEntities(): Collection
    {
        $publicIds = array_keys(self::all());

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
