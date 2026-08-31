<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_access_token_id')
                ->constrained('portal_access_tokens')
                ->cascadeOnDelete();
            $table->string('resource_type', 32);
            $table->foreignId('finance_entity_id')
                ->constrained('finance_entities')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['portal_access_token_id', 'resource_type', 'finance_entity_id'],
                'portal_access_grants_unique'
            );
            $table->index(['resource_type', 'finance_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_access_grants');
    }
};
