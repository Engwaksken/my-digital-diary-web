<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('plans', 'plan_year')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedSmallInteger('plan_year')->nullable()->after('period')->index();
            });
        }

        if (!Schema::hasColumn('plans', 'progress_percent')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            });
        }

        DB::table('plans')->whereNull('plan_year')->update([
            'plan_year' => DB::raw('strftime(\'%Y\', COALESCE(target_date, created_at))'),
        ]);
        DB::table('plans')->where('status', 'completed')->update(['progress_percent' => 100]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'progress_percent')) {
                $table->dropColumn('progress_percent');
            }
            if (Schema::hasColumn('plans', 'plan_year')) {
                $table->dropIndex(['plan_year']);
                $table->dropColumn('plan_year');
            }
        });
    }
};
