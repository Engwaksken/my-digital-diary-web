<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_platform_configs')) {
            Schema::create('meeting_platform_configs', function (Blueprint $table) {
                $table->id();
                $table->string('platform')->unique();
                $table->string('name');
                $table->text('client_id')->nullable();
                $table->text('client_secret')->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->timestamps();
            });
        }

        foreach ([
            ['platform' => 'zoom', 'name' => 'Zoom'],
            ['platform' => 'google', 'name' => 'Google Meet / Calendar'],
            ['platform' => 'microsoft', 'name' => 'Microsoft Teams'],
            ['platform' => 'webex', 'name' => 'Webex'],
        ] as $platform) {
            DB::table('meeting_platform_configs')->updateOrInsert(
                ['platform' => $platform['platform']],
                ['name' => $platform['name'], 'is_enabled' => false, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (! Schema::hasTable('user_meeting_connections')) {
            Schema::create('user_meeting_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('platform');
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->string('connected_email')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'platform']);
            });
        }
    }

    public function down(): void
    {
        // Repair migration only; do not drop support tables on rollback.
    }
};
