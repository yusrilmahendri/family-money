<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes admin accounts from future non-login family/business users.
     * Existing rows stay is_admin = false. No password or data changes.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
