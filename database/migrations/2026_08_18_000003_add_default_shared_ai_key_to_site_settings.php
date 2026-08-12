<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A site-wide, admin-supplied AI API key that every user can use for FREE
 * (up to a monthly cap) without ever adding their own — see
 * AiPlannerService::generate(). A user's own key (added at
 * /api-credentials) always takes priority over this shared one, and using
 * their own is unlimited, unlike the shared default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('default_ai_provider')->nullable()->after('trial_days');
            $table->text('default_ai_api_key')->nullable()->after('default_ai_provider')->comment('encrypted');
            $table->unsignedInteger('default_ai_free_limit_per_month')->default(5)->after('default_ai_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['default_ai_provider', 'default_ai_api_key', 'default_ai_free_limit_per_month']);
        });
    }
};
