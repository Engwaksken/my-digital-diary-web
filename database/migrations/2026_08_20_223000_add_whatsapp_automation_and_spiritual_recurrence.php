<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_media_accounts')) {
            Schema::table('social_media_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('social_media_accounts', 'automation_provider')) {
                    $table->string('automation_provider', 100)->nullable()->after('external_account_id');
                }
                if (! Schema::hasColumn('social_media_accounts', 'automation_endpoint')) {
                    $table->text('automation_endpoint')->nullable()->after('automation_provider');
                }
                if (! Schema::hasColumn('social_media_accounts', 'automation_secret')) {
                    $table->text('automation_secret')->nullable()->after('automation_endpoint');
                }
            });
        }

        if (Schema::hasTable('spiritual_practices')) {
            Schema::table('spiritual_practices', function (Blueprint $table) {
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_frequency')) {
                    $table->string('recurrence_frequency', 20)->nullable()->after('next_planned_date');
                }
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_days_of_week')) {
                    $table->json('recurrence_days_of_week')->nullable()->after('recurrence_frequency');
                }
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_ends_at')) {
                    $table->date('recurrence_ends_at')->nullable()->after('recurrence_days_of_week');
                }
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_parent_id')) {
                    $table->unsignedBigInteger('recurrence_parent_id')->nullable()->index()->after('recurrence_ends_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_media_accounts')) {
            Schema::table('social_media_accounts', function (Blueprint $table) {
                foreach (['automation_secret','automation_endpoint','automation_provider'] as $column) {
                    if (Schema::hasColumn('social_media_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('spiritual_practices')) {
            Schema::table('spiritual_practices', function (Blueprint $table) {
                foreach (['recurrence_parent_id','recurrence_ends_at','recurrence_days_of_week','recurrence_frequency'] as $column) {
                    if (Schema::hasColumn('spiritual_practices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
