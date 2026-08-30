<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_integration_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->unsignedSmallInteger('event_version');
            $table->string('plantation_entity_public_id');
            $table->foreignId('finance_entity_id')->constrained('finance_entities')->restrictOnDelete();
            $table->string('source_public_id');
            $table->string('payload_hash', 64);
            $table->timestamp('processed_at');
            $table->string('result_type')->nullable();
            $table->string('result_public_id')->nullable();
            $table->timestamps();

            $table->index(['finance_entity_id', 'event_type']);
            $table->index('source_public_id');
        });

        Schema::create('external_financial_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_entity_id')->constrained('finance_entities')->restrictOnDelete();
            $table->string('source_system')->default('PLANTATION');
            $table->string('event_type');
            $table->string('source_public_id');
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->timestamps();

            $table->unique(['source_system', 'event_type', 'source_public_id'], 'ext_fin_refs_source_unique');
            $table->index(['record_type', 'record_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('keterangan_detail');
            $table->string('reversed_reason')->nullable()->after('reversed_at');
        });

        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE')->after('description');
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->string('reversed_reason')->nullable()->after('reversed_at');
        });

        Schema::table('receivables', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('source_public_id');
            $table->string('cancelled_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_reason']);
        });
        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'reversed_at', 'reversed_reason']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['reversed_at', 'reversed_reason']);
        });
        Schema::dropIfExists('external_financial_references');
        Schema::dropIfExists('processed_integration_events');
    }
};
