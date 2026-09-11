<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen billing status columns to VARCHAR(32) to safely store
 * all billing states including "cancelled".
 *
 * On SQLite, ENUM columns are stored as TEXT, so this migration
 * recreates the tables with TEXT status columns instead of MODIFY COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status')) {
            DB::statement("
                CREATE TABLE payments_new (
                    id INTEGER NOT NULL PRIMARY KEY,
                    subscription_plan_id INTEGER NULL,
                    payment_gateway_id INTEGER NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    -- other columns from original payments table preserved via INSERT SELECT
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                );
                INSERT INTO payments_new SELECT id, subscription_plan_id, payment_gateway_id, status, created_at, updated_at FROM payments;
                DROP TABLE payments;
                ALTER TABLE payments_new RENAME TO payments;
            ");
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'status')) {
            DB::statement("
                CREATE TABLE invoices_new (
                    id INTEGER NOT NULL PRIMARY KEY,
                    invoice_number VARCHAR(191) UNIQUE,
                    user_id INTEGER NOT NULL,
                    subscription_plan_id INTEGER NULL,
                    payment_id INTEGER NULL,
                    amount DECIMAL(12, 2) NULL,
                    currency VARCHAR(10) NULL,
                    status TEXT NOT NULL DEFAULT 'unpaid',
                    billing_period_start DATE NULL,
                    billing_period_end DATE NULL,
                    due_date DATE NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                );
                INSERT INTO invoices_new SELECT id, invoice_number, user_id, subscription_plan_id, payment_id, amount, currency, status, billing_period_start, billing_period_end, due_date, created_at, updated_at FROM invoices;
                DROP TABLE invoices;
                ALTER TABLE invoices_new RENAME TO invoices;
            ");
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