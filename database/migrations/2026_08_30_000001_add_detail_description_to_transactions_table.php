<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('detail_description')->nullable()->after('keterangan_detail');
        });

        if (Schema::hasColumn('transactions', 'keterangan_detail')) {
            DB::table('transactions')
                ->whereNotNull('keterangan_detail')
                ->where('keterangan_detail', '!=', '')
                ->whereNull('detail_description')
                ->update(['detail_description' => DB::raw('keterangan_detail')]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('detail_description');
        });
    }
};
