<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ai_data_permissions')) {
                $table->json('ai_data_permissions')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('users', 'onboarding_focuses')) {
                $table->json('onboarding_focuses')->nullable()->after('ai_data_permissions');
            }
            if (! Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_focuses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['onboarding_completed_at', 'onboarding_focuses', 'ai_data_permissions'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
