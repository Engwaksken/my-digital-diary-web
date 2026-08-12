<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per installed app (a user might have the app on both a phone
 * and a tablet — both need to receive the push). `fcm_token` is what
 * Firebase Cloud Messaging actually sends to; it's rotated by the OS/app
 * periodically, which is why registration is "upsert on token, keyed by
 * device_id" rather than a token a user sets once — see
 * DeviceTokenController::store().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id')->comment('a stable per-install identifier the app generates once and keeps, e.g. via device_info_plus');
            $table->text('fcm_token');
            $table->enum('platform', ['ios', 'android'])->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
