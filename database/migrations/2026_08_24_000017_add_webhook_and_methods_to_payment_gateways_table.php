<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two genuinely new fields — webhook_url as a distinct concept from
 * callback_url (some aggregators register these separately), and
 * supported_payment_methods as a generic JSON list (replacing the
 * IoTec-specific supports_mtn/supports_airtel booleans as the primary
 * mechanism going forward — those two stay for backward compatibility,
 * this is the generic one new gateway types actually use).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->after('callback_url');
            $table->json('supported_payment_methods')->nullable()->after('supports_airtel');
            $table->json('supported_currencies')->nullable()->after('supported_payment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'supported_payment_methods', 'supported_currencies']);
        });
    }
};
