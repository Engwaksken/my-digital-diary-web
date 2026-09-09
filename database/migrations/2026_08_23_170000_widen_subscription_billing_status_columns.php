<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Pending subscription cancellation stores "cancelled".
         *
         * Older installations may have created payments.status and
         * invoices.status as ENUM columns without "cancelled". MySQL then
         * throws SQLSTATE 01000/1265 ("Data truncated for column status"),
         * which surfaces as the /subscription/payment/{id}/cancel 500.
         *
         * Widening the billing status columns to VARCHAR preserves every
         * existing status and allows current/future billing states safely.
         */
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status')) {
            DB::statement(
                "ALTER TABLE `payments`
                 MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'pending'"
            );
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'status')) {
            DB::statement(
                "ALTER TABLE `invoices`
                 MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'unpaid'"
            );
        }
    }

    public function down(): void
    {
        /*
         * Intentionally left unchanged.
         *
         * Converting back to an older ENUM could destroy valid states such
         * as "cancelled". A rollback should never silently corrupt billing
         * history.
         */
    }
};
