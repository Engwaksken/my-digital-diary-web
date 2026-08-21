<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_media_accounts')) {
            Schema::create('social_media_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('platform', 30);
                $table->string('account_name')->nullable();
                $table->string('username')->nullable();
                $table->string('external_account_id')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'platform', 'account_name']);
                $table->index(['user_id', 'platform']);
            });
        }

        if (!Schema::hasColumn('users', 'whatsapp_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('whatsapp_number', 30)->nullable()->after('email');
            });
        }

        if (!Schema::hasColumn('users', 'whatsapp_channel_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('whatsapp_channel_name')->nullable()->after('whatsapp_number');
            });
        }

        if (!Schema::hasColumn('users', 'whatsapp_channel_url')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('whatsapp_channel_url', 500)->nullable()->after('whatsapp_channel_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'whatsapp_channel_url')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp_channel_url'));
        }
        if (Schema::hasColumn('users', 'whatsapp_channel_name')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp_channel_name'));
        }
        if (Schema::hasColumn('users', 'whatsapp_number')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp_number'));
        }
    }
};
