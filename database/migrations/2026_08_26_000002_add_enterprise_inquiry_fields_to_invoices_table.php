<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an invoice exist WITHOUT a user/payment yet — needed so an admin
 * can send a quotation to an Enterprise lead before any account or
 * payment exists. A "quotation" is just an Invoice with status='quote';
 * once a deal closes, that same row flips to 'unpaid' (a real invoice)
 * rather than needing a second document type. user_id/subscription_plan_id
 * become nullable to support this — every EXISTING invoice already has
 * both, so this doesn't change anything for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('enterprise_inquiry_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('description')->nullable()->after('subscription_plan_id');
        });

        // Raw SQL rather than Schema::table(...)->change() — that helper
        // needs doctrine/dbal installed for some column modifications,
        // which isn't guaranteed present in every environment this runs
        // in. A plain ALTER TABLE has no such dependency.
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE invoices MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enterprise_inquiry_id');
            $table->dropColumn('description');
        });
    }
};
