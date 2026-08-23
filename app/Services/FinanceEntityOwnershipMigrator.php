<?php

namespace App\Services;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use App\Support\FinanceContext;
use App\Support\FinanceOwnership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceEntityOwnershipMigrator
{
    /**
     * @return list<string>
     */
    public static function ownedTables(): array
    {
        return [
            'categories',
            'transactions',
            'incomes',
            'budgets',
            'debts',
            'savings_goals',
            'recurring_transactions',
        ];
    }

    public function ensureDefaultEntities(): array
    {
        $family = FinanceEntity::query()->firstOrCreate(
            ['slug' => FinanceEntity::DEFAULT_SLUG_PRIBADI],
            [
                'name' => 'Keuangan Keluarga',
                'type' => FinanceEntityType::FAMILY,
                'description' => 'Entity default untuk konteks '.FinanceContext::PRIBADI.'.',
                'is_active' => true,
            ]
        );

        $business = FinanceEntity::query()->firstOrCreate(
            ['slug' => FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN],
            [
                'name' => 'Usaha Kebun',
                'type' => FinanceEntityType::BUSINESS,
                'description' => 'Entity default untuk konteks '.FinanceContext::USAHA_KEBUN.'.',
                'is_active' => true,
            ]
        );

        return [
            FinanceContext::PRIBADI => (int) $family->id,
            FinanceContext::USAHA_KEBUN => (int) $business->id,
        ];
    }

    /**
     * Idempotent backfill. Unknown contexts are left unmapped.
     *
     * @return array<string, array{total: int, mapped: int, unmapped: int}>
     */
    public function backfill(): array
    {
        $ids = $this->ensureDefaultEntities();
        $familyId = $ids[FinanceContext::PRIBADI];
        $businessId = $ids[FinanceContext::USAHA_KEBUN];

        $this->backfillByContext('categories', $familyId, $businessId);
        $this->backfillByContext('transactions', $familyId, $businessId);
        $this->backfillByContext('incomes', $familyId, $businessId);

        // Budgets are farm-only (BudgetController::guardFarm). Recurring is personal-only.
        $this->backfillViaCategory('budgets', $businessId);
        $this->backfillViaCategory('recurring_transactions', $familyId);
        $this->assignAll('debts', $familyId);
        $this->assignAll('savings_goals', $familyId);

        return $this->counts();
    }

    /**
     * @return array<string, array{total: int, mapped: int, unmapped: int}>
     */
    public function counts(): array
    {
        $result = [];

        foreach (self::ownedTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
                continue;
            }

            $total = (int) DB::table($table)->count();
            $mapped = (int) DB::table($table)->whereNotNull('finance_entity_id')->count();
            $result[$table] = [
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => $total - $mapped,
            ];
        }

        return $result;
    }

    public function unmappedTotal(): int
    {
        return (int) collect($this->counts())->sum('unmapped');
    }

    public function unknownContextCount(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'context')) {
            return 0;
        }

        return (int) DB::table($table)
            ->whereNotNull('context')
            ->whereNotIn('context', FinanceOwnership::knownContexts())
            ->count();
    }

    public function invalidReferenceCount(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
            return 0;
        }

        return (int) DB::table($table)
            ->whereNotNull("{$table}.finance_entity_id")
            ->whereNotExists(function ($query) use ($table) {
                $query->select(DB::raw(1))
                    ->from('finance_entities')
                    ->whereColumn('finance_entities.id', "{$table}.finance_entity_id");
            })
            ->count();
    }

    public function contextMismatchCount(string $table): int
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'finance_entity_id')
            || ! Schema::hasColumn($table, 'context')) {
            return 0;
        }

        return (int) DB::table($table)
            ->join('finance_entities', 'finance_entities.id', '=', "{$table}.finance_entity_id")
            ->where(function ($query) use ($table) {
                $query->where(function ($inner) use ($table) {
                    $inner->where("{$table}.context", FinanceContext::PRIBADI)
                        ->where('finance_entities.type', FinanceEntityType::BUSINESS->value);
                })->orWhere(function ($inner) use ($table) {
                    $inner->where("{$table}.context", FinanceContext::USAHA_KEBUN)
                        ->where('finance_entities.type', FinanceEntityType::FAMILY->value);
                });
            })
            ->count();
    }

    /**
     * @return array<string, array{total: int, mapped: int, unmapped: int, invalid: int, mismatch: int, unknown_context: int}>
     */
    public function audit(): array
    {
        $result = [];
        $counts = $this->counts();

        foreach (self::ownedTables() as $table) {
            $row = $counts[$table] ?? ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
            $result[$table] = [
                ...$row,
                'invalid' => $this->invalidReferenceCount($table),
                'mismatch' => $this->contextMismatchCount($table),
                'unknown_context' => $this->unknownContextCount($table),
            ];
        }

        return $result;
    }

    public function hasCriticalInconsistencies(): bool
    {
        foreach ($this->audit() as $row) {
            if ($row['unmapped'] > 0 || $row['invalid'] > 0 || $row['mismatch'] > 0 || $row['unknown_context'] > 0) {
                return true;
            }
        }

        return false;
    }

    private function backfillByContext(string $table, int $familyId, int $businessId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
            return;
        }

        if (Schema::hasColumn($table, 'context')) {
            DB::table($table)
                ->whereNull('finance_entity_id')
                ->where('context', FinanceContext::PRIBADI)
                ->update(['finance_entity_id' => $familyId]);

            DB::table($table)
                ->whereNull('finance_entity_id')
                ->where('context', FinanceContext::USAHA_KEBUN)
                ->update(['finance_entity_id' => $businessId]);
        }
    }

    private function backfillViaCategory(string $table, int $fallbackFamilyId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
            return;
        }

        $rows = DB::table($table)->whereNull('finance_entity_id')->get();

        foreach ($rows as $row) {
            if (isset($row->category_id) && $row->category_id) {
                $entityId = DB::table('categories')->where('id', $row->category_id)->value('finance_entity_id');

                if ($entityId) {
                    DB::table($table)->where('id', $row->id)->update([
                        'finance_entity_id' => $entityId,
                    ]);
                }

                continue;
            }

            DB::table($table)->where('id', $row->id)->update([
                'finance_entity_id' => $fallbackFamilyId,
            ]);
        }
    }

    private function assignAll(string $table, int $entityId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
            return;
        }

        DB::table($table)->whereNull('finance_entity_id')->update([
            'finance_entity_id' => $entityId,
        ]);
    }
}
