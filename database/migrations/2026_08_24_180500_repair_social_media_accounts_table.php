<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_accounts')) {
            Schema::create('social_media_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('platform', 50);
                $table->string('account_name', 120);
                $table->string('username', 180)->nullable();
                $table->string('external_account_id')->nullable();
                $table->text('oauth_access_token')->nullable();
                $table->timestamp('oauth_expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('auto_publish_enabled')->default(false);
                $table->timestamps();
                $table->index(['user_id', 'platform']);
            });

            return;
        }

        Schema::table('social_media_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_media_accounts', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('social_media_accounts', 'platform')) {
                $table->string('platform', 50)->nullable()->index();
            }
            if (! Schema::hasColumn('social_media_accounts', 'account_name')) {
                $table->string('account_name', 120)->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'username')) {
                $table->string('username', 180)->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'external_account_id')) {
                $table->string('external_account_id')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'oauth_access_token')) {
                $table->text('oauth_access_token')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'oauth_expires_at')) {
                $table->timestamp('oauth_expires_at')->nullable();
            }
            if (! Schema::hasColumn('social_media_accounts', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('social_media_accounts', 'auto_publish_enabled')) {
                $table->boolean('auto_publish_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        // Deliberately non-destructive: this migration repairs a live table.
    }
};
