<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'engagement_notification_preferences')) {
                $table->json('engagement_notification_preferences')->nullable()->after('onboarding_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'engagement_notification_preferences')) {
                $table->dropColumn('engagement_notification_preferences');
            }
        });
    }
};
