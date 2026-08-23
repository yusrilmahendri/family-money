<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entity_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_entity_id')
                ->constrained('finance_entities')
                ->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['finance_entity_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entity_access_tokens');
    }
};
