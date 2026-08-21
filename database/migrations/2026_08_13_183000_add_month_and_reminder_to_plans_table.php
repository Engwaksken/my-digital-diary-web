<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'plan_month')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedTinyInteger('plan_month')->nullable()->after('plan_year')->index();
            });
        }

        if (! Schema::hasColumn('plans', 'reminder_at')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dateTime('reminder_at')->nullable()->after('target_date')->index();
            });
        }

        // Existing annual-plan rows keep annual semantics. Monthly rows are opt-in.
        DB::table('plans')
            ->where('period', 'annually')
            ->whereNotNull('plan_month')
            ->update(['period' => 'monthly']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'reminder_at')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropIndex(['reminder_at']);
                $table->dropColumn('reminder_at');
            });
        }

        if (Schema::hasColumn('plans', 'plan_month')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropIndex(['plan_month']);
                $table->dropColumn('plan_month');
            });
        }
    }
};
