<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per billing-related event — invoice generated/emailed, receipt
 * generated/emailed, expiry reminder sent, payment status changed — so
 * an admin can see exactly what was sent to whom and whether it actually
 * succeeded, without digging through server logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->comment('invoice_generated, invoice_emailed, receipt_generated, receipt_emailed, reminder_sent, payment_status_changed');
            $table->string('recipient_email')->nullable();
            $table->string('status')->default('success')->comment('success, failed');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_event_logs');
    }
};
