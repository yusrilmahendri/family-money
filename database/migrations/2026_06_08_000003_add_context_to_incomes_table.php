<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incomes')) {
            return;
        }

        Schema::table('incomes', function (Blueprint $table) {
            if (! Schema::hasColumn('incomes', 'context')) {
                $table->string('context', 20)->default('USAHA_KEBUN')->after('category_id');
                $table->index('context');
            }
        });

        // Pemasukan adalah hasil usaha -> default USAHA_KEBUN
        DB::table('incomes')->whereNull('context')->update(['context' => 'USAHA_KEBUN']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('incomes')) {
            return;
        }

        Schema::table('incomes', function (Blueprint $table) {
            if (Schema::hasColumn('incomes', 'context')) {
                $table->dropIndex(['context']);
                $table->dropColumn('context');
            }
        });
    }
};
