<?php

namespace App\Support;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;

/**
 * Compatibility map between FinanceEntity.type and historical context values.
 *
 * FAMILY  → PRIBADI
 * BUSINESS → USAHA_KEBUN
 *
 * Runtime ownership is finance_entity_id. This map is for backfill,
 * factories, and the retained context column.
 */
class FinanceOwnership
{
    /**
     * @return list<string>
     */
    public static function knownContexts(): array
    {
        return [FinanceContext::PRIBADI, FinanceContext::USAHA_KEBUN];
    }

    public static function contextFor(FinanceEntity $entity): string
    {
        return $entity->type === FinanceEntityType::BUSINESS
            ? FinanceContext::USAHA_KEBUN
            : FinanceContext::PRIBADI;
    }

    public static function typeForContext(?string $context): ?FinanceEntityType
    {
        return match ($context) {
            FinanceContext::PRIBADI => FinanceEntityType::FAMILY,
            FinanceContext::USAHA_KEBUN => FinanceEntityType::BUSINESS,
            default => null,
        };
    }

    public static function defaultSlugForContext(string $context): ?string
    {
        return match ($context) {
            FinanceContext::PRIBADI => FinanceEntity::DEFAULT_SLUG_PRIBADI,
            FinanceContext::USAHA_KEBUN => FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN,
            default => null,
        };
    }

    public static function defaultEntityForContext(string $context): ?FinanceEntity
    {
        $slug = self::defaultSlugForContext($context);

        if ($slug === null) {
            return null;
        }

        return FinanceEntity::query()->where('slug', $slug)->first();
    }

    public static function defaultEntityIdForContext(string $context): ?int
    {
        return self::defaultEntityForContext($context)?->id;
    }
}
