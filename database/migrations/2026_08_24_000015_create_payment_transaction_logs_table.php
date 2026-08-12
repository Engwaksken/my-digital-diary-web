<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every collection attempt through an aggregator (IoTec, etc.) gets a
 * row here — the request sent, the response received, the reference
 * IoTec assigns, and every status change from initiation through to
 * final success/failure via webhook callback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_reference')->nullable()->comment('the aggregator\'s own transaction id');
            $table->string('status')->default('initiated')->comment('initiated, pending, completed, failed');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('phone_number')->nullable();
            $table->string('network')->nullable()->comment('mtn, airtel');
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction_logs');
    }
};
