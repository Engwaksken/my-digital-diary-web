<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('payments')
            && ! Schema::hasColumn('payments', 'gateway_transaction_id')
        ) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('gateway_transaction_id')->nullable()->index()->after('reference');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'gateway_transaction_id')
        ) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('gateway_transaction_id');
            });
        }
    }
};
