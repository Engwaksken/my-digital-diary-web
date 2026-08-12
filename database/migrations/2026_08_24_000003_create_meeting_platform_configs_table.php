<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Admin-managed OAuth app credentials, one row per meeting platform —
 * the client_id/client_secret a Zoom/Google/Microsoft/Webex OAuth "app"
 * generates when an admin registers one on that platform's own developer
 * console. This table stores THOSE credentials; it's what lets a USER
 * then connect THEIR OWN account (see user_meeting_connections) to fetch
 * their real meetings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_platform_configs', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->unique()->comment('zoom, google, microsoft, webex');
            $table->string('name');
            $table->text('client_id')->nullable()->comment('encrypted');
            $table->text('client_secret')->nullable()->comment('encrypted');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        // Seed the four built-in platforms as disabled rows with no
        // credentials — an admin fills these in and enables each one
        // from Admin -> Settings -> Meeting Platforms.
        DB::table('meeting_platform_configs')->insert([
            ['platform' => 'zoom', 'name' => 'Zoom', 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
            ['platform' => 'google', 'name' => 'Google Meet / Calendar', 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
            ['platform' => 'microsoft', 'name' => 'Microsoft Teams', 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
            ['platform' => 'webex', 'name' => 'Webex', 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_platform_configs');
    }
};
