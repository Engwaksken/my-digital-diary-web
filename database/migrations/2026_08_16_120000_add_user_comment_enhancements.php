<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_plans', 'custom_prompt')) $table->text('custom_prompt')->nullable()->after('content');
        });
        Schema::table('daily_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_plans', 'achievements')) $table->text('achievements')->nullable()->after('notes');
            if (!Schema::hasColumn('daily_plans', 'challenges')) $table->text('challenges')->nullable()->after('achievements');
        });
        Schema::table('daily_plan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_plan_items', 'achievements')) $table->text('achievements')->nullable()->after('description');
            if (!Schema::hasColumn('daily_plan_items', 'challenges')) $table->text('challenges')->nullable()->after('achievements');
        });
        Schema::table('spiritual_practices', function (Blueprint $table) {
            if (!Schema::hasColumn('spiritual_practices', 'practice_time')) $table->time('practice_time')->nullable()->after('practiced_at');
            if (!Schema::hasColumn('spiritual_practices', 'preacher')) $table->string('preacher')->nullable()->after('title');
            if (!Schema::hasColumn('spiritual_practices', 'theme_topic')) $table->string('theme_topic')->nullable()->after('preacher');
            if (!Schema::hasColumn('spiritual_practices', 'scriptures')) $table->text('scriptures')->nullable()->after('theme_topic');
            if (!Schema::hasColumn('spiritual_practices', 'lessons_learnt')) $table->text('lessons_learnt')->nullable()->after('scriptures');
        });
    }

    public function down(): void
    {
        Schema::table('ai_plans', fn (Blueprint $t) => $t->dropColumn(['custom_prompt']));
        Schema::table('daily_plans', fn (Blueprint $t) => $t->dropColumn(['achievements','challenges']));
        Schema::table('daily_plan_items', fn (Blueprint $t) => $t->dropColumn(['achievements','challenges']));
        Schema::table('spiritual_practices', fn (Blueprint $t) => $t->dropColumn(['practice_time','preacher','theme_topic','scriptures','lessons_learnt']));
    }
};
