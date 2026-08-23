<?php

use App\Services\FinanceEntityOwnershipMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staged ownership migration:
     * 1) add nullable finance_entity_id
     * 2) create/find default entities
     * 3) backfill
     * 4) add FK restrictOnDelete
     * 5) tighten NOT NULL on MySQL only when unmapped = 0
     */
    public function up(): void
    {
        foreach (FinanceEntityOwnershipMigrator::ownedTables() as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'finance_entity_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('finance_entity_id')->nullable();
                $blueprint->index('finance_entity_id');
            });
        }

        $migrator = new FinanceEntityOwnershipMigrator;
        $migrator->backfill();

        foreach (FinanceEntityOwnershipMigrator::ownedTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('finance_entity_id')
                    ->references('id')
                    ->on('finance_entities')
                    ->restrictOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql' && $migrator->unmappedTotal() === 0) {
            foreach (FinanceEntityOwnershipMigrator::ownedTables() as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'finance_entity_id')) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `finance_entity_id` BIGINT UNSIGNED NOT NULL");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (FinanceEntityOwnershipMigrator::ownedTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_entity_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['finance_entity_id']);
                $blueprint->dropIndex(['finance_entity_id']);
                $blueprint->dropColumn('finance_entity_id');
            });
        }
    }
};
