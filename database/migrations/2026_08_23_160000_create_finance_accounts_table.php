<?php

use App\Services\FinanceAccountService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('finance_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['finance_entity_id', 'name']);
            $table->index(['finance_entity_id', 'is_default']);
            $table->index(['finance_entity_id', 'is_active']);
        });

        app(FinanceAccountService::class)->provisionMissingDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};
