<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends payment_gateways with the fields a real-time collection
 * aggregator (IoTec, and by extension similar ones like Pesapal/
 * Flutterwave) needs — OAuth token/collect/status endpoints, wallet
 * identity, and mobile-network support flags — on top of the existing
 * bank/mobile_money/card manual-entry fields, which are untouched.
 * `type` gains a new allowed value, 'aggregator', for this kind of row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Doctrine's schema builder can't reliably ALTER an existing
        // ENUM's allowed values, so this is a raw statement — adds
        // 'aggregator' alongside the existing bank/mobile_money/card.
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE payment_gateways MODIFY type ENUM('bank', 'mobile_money', 'card', 'aggregator') NOT NULL"
        );

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('gateway_code')->nullable()->unique()->after('id');
            $table->string('display_name')->nullable()->after('name');
            $table->string('provider_type')->nullable()->after('display_name');
            $table->text('description')->nullable()->after('provider_type');
            $table->boolean('is_default')->default(false)->after('is_enabled');
            $table->boolean('sandbox_mode')->default(true)->after('is_default');

            $table->string('token_url')->nullable();
            $table->string('collect_url')->nullable();
            $table->string('base_url')->nullable();
            $table->string('status_url')->nullable();
            $table->text('client_id')->nullable()->comment('encrypted');
            $table->text('client_secret')->nullable()->comment('encrypted');
            $table->text('wallet_guid')->nullable()->comment('encrypted');
            $table->string('callback_url')->nullable();
            $table->string('return_url')->nullable();

            $table->boolean('supports_collection')->default(true);
            $table->boolean('supports_disbursement')->default(false);
            $table->boolean('supports_mtn')->default(true);
            $table->boolean('supports_airtel')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'gateway_code', 'display_name', 'provider_type', 'description', 'is_default', 'sandbox_mode',
                'token_url', 'collect_url', 'base_url', 'status_url', 'client_id', 'client_secret', 'wallet_guid',
                'callback_url', 'return_url', 'supports_collection', 'supports_disbursement', 'supports_mtn', 'supports_airtel',
            ]);
        });

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE payment_gateways MODIFY type ENUM('bank', 'mobile_money', 'card') NOT NULL"
        );
    }
};
