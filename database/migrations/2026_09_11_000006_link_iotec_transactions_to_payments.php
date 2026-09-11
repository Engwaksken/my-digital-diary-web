<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('iotec_subscription_transactions')
            && ! Schema::hasColumn('iotec_subscription_transactions', 'payment_id')
        ) {
            Schema::table('iotec_subscription_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_id')->nullable()->index()->after('status_message');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('iotec_subscription_transactions')
            && Schema::hasColumn('iotec_subscription_transactions', 'payment_id')
        ) {
            Schema::table('iotec_subscription_transactions', function (Blueprint $table) {
                $table->dropColumn('payment_id');
            });
        }
    }
};
