<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('status');
            $table->string('source_public_id')->nullable()->after('source_type');
            $table->unique(['source_type', 'source_public_id']);
        });

        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('description');
            $table->string('source_public_id')->nullable()->after('source_type');
            $table->unique(['source_type', 'source_public_id']);
        });
    }

    public function down(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_public_id']);
            $table->dropColumn(['source_type', 'source_public_id']);
        });

        Schema::table('receivables', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_public_id']);
            $table->dropColumn(['source_type', 'source_public_id']);
        });
    }
};
