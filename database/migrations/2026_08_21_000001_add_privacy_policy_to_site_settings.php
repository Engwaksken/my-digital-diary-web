<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->longText('privacy_policy_content')->nullable()->after('default_ai_free_limit_per_month');
            $table->string('privacy_policy_version')->default('1.0')->after('privacy_policy_content');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['privacy_policy_content', 'privacy_policy_version']);
        });
    }
};
