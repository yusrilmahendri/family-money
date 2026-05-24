<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saldos')) {
            return;
        }

        Schema::table('saldos', function (Blueprint $table) {
            if (! Schema::hasColumn('saldos', 'income_id')) {
                $table->unsignedBigInteger('income_id')->nullable()->after('category_id');
                $table->index('income_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('saldos')) {
            return;
        }

        Schema::table('saldos', function (Blueprint $table) {
            if (Schema::hasColumn('saldos', 'income_id')) {
                $table->dropIndex(['income_id']);
                $table->dropColumn('income_id');
            }
        });
    }
};
