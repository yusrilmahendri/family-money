<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_distributions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->foreignId('source_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->foreignId('family_entity_id')
                ->constrained('finance_entities')
                ->restrictOnDelete();
            $table->foreignId('destination_account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('distribution_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['business_entity_id', 'distribution_date'], 'pd_business_date_idx');
            $table->index(['family_entity_id', 'distribution_date'], 'pd_family_date_idx');
            $table->index(['business_entity_id', 'period_start', 'period_end'], 'pd_business_period_idx');
            $table->index('source_account_id');
            $table->index('destination_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_distributions');
    }
};
