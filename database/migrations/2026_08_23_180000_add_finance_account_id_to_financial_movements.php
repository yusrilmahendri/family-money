<?php

use App\Services\FinanceAccountMovementMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staged account ownership:
     * 1) add nullable finance_account_id
     * 2) map existing rows to the owning entity's default account
     * 3) add FK restrictOnDelete
     * 4) tighten NOT NULL on MySQL only when unmapped = 0
     */
    public function up(): void
    {
        $tables = [
            ...FinanceAccountMovementMigrator::ownedMovementTables(),
            ...array_keys(FinanceAccountMovementMigrator::childMovementTables()),
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'finance_account_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('finance_account_id')->nullable();
                $blueprint->index('finance_account_id');
            });
        }

        $migrator = new FinanceAccountMovementMigrator;
        $migrator->backfill();

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_account_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('finance_account_id')
                    ->references('id')
                    ->on('finance_accounts')
                    ->restrictOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql' && $migrator->unmappedTotal() === 0) {
            foreach ($tables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'finance_account_id')) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `finance_account_id` BIGINT UNSIGNED NOT NULL");
                }
            }
        }
    }

    public function down(): void
    {
        $tables = [
            ...FinanceAccountMovementMigrator::ownedMovementTables(),
            ...array_keys(FinanceAccountMovementMigrator::childMovementTables()),
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'finance_account_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['finance_account_id']);
                $blueprint->dropIndex(['finance_account_id']);
                $blueprint->dropColumn('finance_account_id');
            });
        }
    }
};
