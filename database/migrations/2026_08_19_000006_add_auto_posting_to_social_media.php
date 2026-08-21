<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_media_posts')) {
            Schema::table('social_media_posts', function (Blueprint $table) {
                if (! Schema::hasColumn('social_media_posts', 'posting_mode')) {
                    $table->string('posting_mode', 30)
                        ->default('manual')
                        ->after('approval_status');
                }
            });
        }

        if (Schema::hasTable('social_media_accounts')) {
            Schema::table('social_media_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('social_media_accounts', 'external_account_id')) {
                    $table->string('external_account_id')
                        ->nullable()
                        ->after('username');
                }

                if (! Schema::hasColumn('social_media_accounts', 'oauth_access_token')) {
                    $table->longText('oauth_access_token')
                        ->nullable()
                        ->after('external_account_id');
                }

                if (! Schema::hasColumn('social_media_accounts', 'oauth_refresh_token')) {
                    $table->longText('oauth_refresh_token')
                        ->nullable()
                        ->after('oauth_access_token');
                }

                if (! Schema::hasColumn('social_media_accounts', 'oauth_expires_at')) {
                    $table->timestamp('oauth_expires_at')
                        ->nullable()
                        ->after('oauth_refresh_token');
                }

                if (! Schema::hasColumn('social_media_accounts', 'oauth_scopes')) {
                    $table->json('oauth_scopes')
                        ->nullable()
                        ->after('oauth_expires_at');
                }

                if (! Schema::hasColumn('social_media_accounts', 'auto_publish_enabled')) {
                    $table->boolean('auto_publish_enabled')
                        ->default(false)
                        ->after('oauth_scopes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_media_posts')) {
            Schema::table('social_media_posts', function (Blueprint $table) {
                if (Schema::hasColumn('social_media_posts', 'posting_mode')) {
                    $table->dropColumn('posting_mode');
                }
            });
        }

        if (Schema::hasTable('social_media_accounts')) {
            Schema::table('social_media_accounts', function (Blueprint $table) {
                $columns = [
                    'external_account_id',
                    'oauth_access_token',
                    'oauth_refresh_token',
                    'oauth_expires_at',
                    'oauth_scopes',
                    'auto_publish_enabled',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('social_media_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
