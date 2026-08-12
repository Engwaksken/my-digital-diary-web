<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A JSON array of additional currencies a user can VIEW subscription
 * prices in — [{"code":"KES","symbol":"KSh","rate":130.5}, ...], where
 * `rate` is units of that currency per 1 USD (the site's base currency,
 * set by monthly_price). This is DISPLAY-ONLY: switching currency on the
 * subscription page recalculates what's shown on screen, but actual
 * charges (Stripe, mobile money) still process in the base USD amount —
 * genuinely charging in a second currency needs that gateway's own
 * multi-currency setup, which isn't something this migration or the
 * currency selector attempts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('supported_currencies')->nullable()->after('privacy_policy_version');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('supported_currencies');
        });
    }
};
