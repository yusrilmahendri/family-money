<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_capital_contributions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('source_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->foreignId('source_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->foreignId('business_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->foreignId('destination_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['source_entity_id', 'transaction_date'], 'bcc_source_entity_date_idx');
            $table->index(['business_entity_id', 'transaction_date'], 'bcc_business_entity_date_idx');
            $table->index('source_account_id');
            $table->index('destination_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_capital_contributions');
    }
};
