<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'personal_goal_id')) $table->foreignId('personal_goal_id')->nullable()->after('user_id')->constrained('personal_goals')->nullOnDelete();
        });
        Schema::table('daily_plan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_plan_items', 'personal_goal_id')) $table->foreignId('personal_goal_id')->nullable()->after('daily_plan_id')->constrained('personal_goals')->nullOnDelete();
        });
        Schema::table('project_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('project_tasks', 'personal_goal_id')) $table->foreignId('personal_goal_id')->nullable()->after('project_id')->constrained('personal_goals')->nullOnDelete();
        });
    }
    public function down(): void
    {
        foreach (['plans','daily_plan_items','project_tasks'] as $tableName) {
            if (Schema::hasColumn($tableName, 'personal_goal_id')) {
                Schema::table($tableName, function (Blueprint $table) { $table->dropConstrainedForeignId('personal_goal_id'); });
            }
        }
    }
};
