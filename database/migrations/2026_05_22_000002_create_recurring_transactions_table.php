<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');                                  // mis. "Bayar BPJS"
            $table->decimal('amount', 15, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-31, untuk monthly
            $table->unsignedTinyInteger('day_of_week')->nullable();  // 0=Mgg .. 6=Sab, untuk weekly
            $table->date('start_date');
            $table->date('end_date')->nullable();                    // null = tidak berakhir
            $table->date('next_due');
            $table->date('last_posted_at')->nullable();
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
