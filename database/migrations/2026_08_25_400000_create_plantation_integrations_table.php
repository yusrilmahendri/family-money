<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantation_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_entity_id')
                ->unique()
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->string('plantation_entity_public_id')->unique();
            $table->string('status');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantation_integrations');
    }
};
