<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_transfers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('finance_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->foreignId('source_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->foreignId('destination_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['finance_entity_id', 'transaction_date']);
            $table->index('source_account_id');
            $table->index('destination_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transfers');
    }
};
