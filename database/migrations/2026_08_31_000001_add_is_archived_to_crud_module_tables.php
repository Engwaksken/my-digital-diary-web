<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds is_archived to every table backing the 19 modules that share
 * ApiCrudController — archiving support is added once at that shared
 * base class rather than per-controller (see ApiCrudController's
 * index()/archive()/unarchive() methods), so every one of these
 * tables needs the same column. Default false — archiving is
 * something a user does deliberately, existing rows should never
 * silently disappear from their normal list after this migration runs.
 */
return new class extends Migration
{
    private array $tables = [
        'feedback', 'network_contacts', 'meetings', 'plans', 'projects',
        'savings_contributions', 'savings_goals', 'reminders', 'expenses',
        'project_tasks', 'personal_relationships', 'spiritual_practices',
        'education_plans', 'health_checkups', 'budgets', 'incomes',
        'diet_logs', 'debts', 'sleep_logs', 'exercise_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_archived')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_archived');
            });
        }
    }
};
