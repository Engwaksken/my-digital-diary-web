<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site's BASE currency (what monthly_price is actually denominated
 * in) — previously always implicitly USD with a hardcoded "$" symbol
 * throughout the app. Defaults to UGX per request. `supported_currencies`
 * (already existing) remains how an admin adds ADDITIONAL currencies for
 * users to view converted prices in — this is specifically about what
 * the BASE price itself is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('default_currency_code', 3)->default('UGX')->after('monthly_price');
            $table->string('default_currency_symbol', 10)->default('UGX')->after('default_currency_code');
            $table->unsignedTinyInteger('default_currency_decimals')->default(0)->after('default_currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['default_currency_code', 'default_currency_symbol', 'default_currency_decimals']);
        });
    }
};
