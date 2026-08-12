<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['bank', 'mobile_money', 'card']);
            $table->string('name')->comment('Display label shown to users, e.g. "Zenith Bank Transfer", "M-Pesa"');
            $table->boolean('is_enabled')->default(true);
            // Shown to users for bank/mobile_money (account details, steps to pay).
            $table->text('instructions')->nullable();
            // Type-specific settings — bank account details, mobile money
            // number/provider, or Stripe API keys for card payments.
            // Encrypted at rest (Laravel's built-in 'encrypted:array' cast)
            // since this can contain secret API keys.
            $table->text('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
