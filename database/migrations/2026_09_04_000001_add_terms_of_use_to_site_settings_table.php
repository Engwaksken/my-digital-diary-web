<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('site_settings', 'terms_of_use_content')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->longText('terms_of_use_content')->nullable()->after('privacy_policy_version');
            });
        }

        if (! Schema::hasColumn('site_settings', 'terms_of_use_version')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->string('terms_of_use_version', 20)->nullable()->after('terms_of_use_content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('site_settings', 'terms_of_use_version')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->dropColumn('terms_of_use_version');
            });
        }

        if (Schema::hasColumn('site_settings', 'terms_of_use_content')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->dropColumn('terms_of_use_content');
            });
        }
    }
};
