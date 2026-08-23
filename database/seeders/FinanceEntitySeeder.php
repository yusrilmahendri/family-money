<?php

namespace Database\Seeders;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use App\Support\FinanceContext;
use Illuminate\Database\Seeder;

/**
 * Prepared for a later migration/task that creates default entities for
 * existing FinanceContext values.
 *
 * Intentionally NOT called from DatabaseSeeder so production seeding does
 * not invent dummy FAMILY / BUSINESS rows.
 *
 * Mapping to create later (no existing transaction mapping in this task):
 * - FinanceContext::PRIBADI     -> FAMILY,   slug: FinanceEntity::DEFAULT_SLUG_PRIBADI
 * - FinanceContext::USAHA_KEBUN -> BUSINESS, slug: FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN
 */
class FinanceEntitySeeder extends Seeder
{
    public function run(): void
    {
        // No automatic dummy data.
    }

    /**
     * Idempotent helper for the next entity-ownership migration/task.
     */
    public static function seedDefaultsForExistingContexts(): void
    {
        FinanceEntity::query()->firstOrCreate(
            ['slug' => FinanceEntity::DEFAULT_SLUG_PRIBADI],
            [
                'name' => FinanceContext::all()[FinanceContext::PRIBADI],
                'type' => FinanceEntityType::FAMILY,
                'description' => 'Entity default untuk konteks '.FinanceContext::PRIBADI.'.',
                'is_active' => true,
            ]
        );

        FinanceEntity::query()->firstOrCreate(
            ['slug' => FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN],
            [
                'name' => FinanceContext::all()[FinanceContext::USAHA_KEBUN],
                'type' => FinanceEntityType::BUSINESS,
                'description' => 'Entity default untuk konteks '.FinanceContext::USAHA_KEBUN.'.',
                'is_active' => true,
            ]
        );
    }
}
