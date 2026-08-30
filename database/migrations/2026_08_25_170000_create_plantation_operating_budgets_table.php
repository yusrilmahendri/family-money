<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantation_operating_budgets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('finance_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('allocated_amount', 15, 2);
            $table->string('status');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantation_operating_budgets');
    }
};
