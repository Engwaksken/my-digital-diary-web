<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_accounts')) {
            return;
        }

        Schema::table('social_media_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_media_accounts', 'external_account_id')) {
                $table->string('external_account_id')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'oauth_access_token')) {
                $table->text('oauth_access_token')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'oauth_refresh_token')) {
                $table->text('oauth_refresh_token')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'oauth_expires_at')) {
                $table->timestamp('oauth_expires_at')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'auto_publish_enabled')) {
                $table->boolean('auto_publish_enabled')->default(false);
            }
            if (! Schema::hasColumn('social_media_accounts', 'automation_provider')) {
                $table->string('automation_provider', 100)->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'automation_endpoint')) {
                $table->text('automation_endpoint')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'automation_secret')) {
                $table->text('automation_secret')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive compatibility migration.
    }
};
