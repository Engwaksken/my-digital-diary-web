<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Contact Sales" submissions from the Enterprise section of the pricing
 * page — a sales-assisted lead, not a self-serve purchase. An admin
 * follows up manually and, if the deal closes, assigns the customer to
 * one of the existing 'organization'-category plans themselves (those
 * plans still exist for that purpose; they're just no longer shown as
 * purchasable cards on the public pricing page).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('about');
            $table->string('email');
            $table->string('country');
            $table->string('employee_count');
            $table->string('status')->default('new')->comment('new, contacted, closed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_inquiries');
    }
};
