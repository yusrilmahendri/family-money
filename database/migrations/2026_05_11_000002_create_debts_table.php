<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('principal_total', 15, 2);
            $table->decimal('remaining_balance', 15, 2);
            $table->decimal('monthly_installment', 15, 2)->default(0);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('start_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
