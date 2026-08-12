<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-composed announcements (app updates, news, etc.) — sent to
 * every user via AnnouncementNotification on creation. This table is
 * the durable record of what was sent/when; the actual per-user
 * delivery tracking lives in Laravel's own notifications table, same
 * as every other notification type in this app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
