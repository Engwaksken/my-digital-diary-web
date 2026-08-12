<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin optionally restrict WHICH app modules a plan includes
 * (e.g. a cheaper tier without Meetings or Business Card). NULL/empty
 * means "everything" — every existing plan keeps working exactly as
 * before until an admin deliberately narrows one down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('features')->nullable()->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
