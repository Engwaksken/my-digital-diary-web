<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_provider_configs')) {
            Schema::create('social_media_provider_configs', function (Blueprint $table) {
                $table->id();
                $table->string('platform', 50)->index();
                $table->string('provider_name', 120);
                $table->string('driver', 60)->default('generic');
                $table->string('connection_mode', 30)->default('shared_api_key');
                $table->text('base_url')->nullable();
                $table->string('auth_type', 30)->default('header');
                $table->string('auth_header', 120)->nullable();
                $table->text('api_key')->nullable();
                $table->text('api_secret')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_enabled')->default(false)->index();
                $table->boolean('is_default')->default(false)->index();
                $table->timestamps();

                $table->index(['platform', 'is_enabled', 'is_default'], 'sm_provider_lookup');
            });
        }

        if (Schema::hasTable('social_media_accounts')) {
            Schema::table('social_media_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('social_media_accounts', 'provider_config_id')) {
                    $table->unsignedBigInteger('provider_config_id')->nullable()->index()->after('platform');
                }

                if (! Schema::hasColumn('social_media_accounts', 'provider_account_ref')) {
                    $table->string('provider_account_ref', 255)->nullable()->after('external_account_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_media_accounts')) {
            Schema::table('social_media_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('social_media_accounts', 'provider_account_ref')) {
                    $table->dropColumn('provider_account_ref');
                }

                if (Schema::hasColumn('social_media_accounts', 'provider_config_id')) {
                    $table->dropColumn('provider_config_id');
                }
            });
        }

        Schema::dropIfExists('social_media_provider_configs');
    }
};
