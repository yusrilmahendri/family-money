<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'context')) {
                $table->string('context', 20)->default('PRIBADI')->after('name');
                $table->index('context');
            }
        });

        // Backfill data lama -> PRIBADI
        DB::table('categories')->whereNull('context')->update(['context' => 'PRIBADI']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'context')) {
                $table->dropIndex(['context']);
                $table->dropColumn('context');
            }
        });
    }
};
