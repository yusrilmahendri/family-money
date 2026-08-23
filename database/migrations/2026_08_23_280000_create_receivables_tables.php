<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('finance_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->string('party_name');
            $table->string('description')->nullable();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('remaining_balance', 15, 2);
            $table->date('receivable_date');
            $table->date('due_date')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['finance_entity_id', 'status']);
            $table->index(['finance_entity_id', 'due_date']);
        });

        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('receivable_id')
                ->constrained('receivables')
                ->restrictOnDelete();
            $table->foreignId('finance_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['receivable_id', 'payment_date']);
            $table->index('finance_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivables');
    }
};
