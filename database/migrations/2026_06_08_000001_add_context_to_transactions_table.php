<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'context')) {
                $table->string('context', 20)->default('PRIBADI')->after('category_id');
                $table->index('context');
            }
        });

        // Backfill data lama -> PRIBADI
        DB::table('transactions')->whereNull('context')->update(['context' => 'PRIBADI']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'context')) {
                $table->dropIndex(['context']);
                $table->dropColumn('context');
            }
        });
    }
};
