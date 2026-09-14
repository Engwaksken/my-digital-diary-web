<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_extra_requests')) {
            Schema::create('user_extra_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('request_type')->default('extra_recording_quota');
                $table->text('description')->nullable();
                $table->string('status')->default('pending'); // pending, approved, applied, rejected
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 10)->default('UGX');
                $table->unsignedBigInteger('iotec_transaction_id')->nullable();
                $table->integer('quota_amount')->default(0); // minutes
                $table->integer('quota_used')->default(0); // minutes used
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_extra_requests');
    }
};
