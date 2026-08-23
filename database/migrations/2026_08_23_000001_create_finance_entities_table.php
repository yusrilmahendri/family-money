<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foundation table for FAMILY / BUSINESS ownership.
     *
     * Default rows for existing FinanceContext values (PRIBADI, USAHA_KEBUN)
     * are intentionally not inserted here. A later migration/task can create
     * them using FinanceEntity::DEFAULT_SLUG_PRIBADI and
     * FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN without mapping existing
     * transactions in this task.
     */
    public function up(): void
    {
        Schema::create('finance_entities', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entities');
    }
};
