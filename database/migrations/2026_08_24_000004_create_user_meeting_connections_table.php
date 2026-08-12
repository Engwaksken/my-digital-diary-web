<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's OWN OAuth connection to one platform — created once they
 * click "Connect" and complete that platform's login/consent screen.
 * access_token/refresh_token are what let the app fetch THEIR meetings
 * on their behalf without ever seeing their password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_meeting_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->text('access_token')->comment('encrypted');
            $table->text('refresh_token')->nullable()->comment('encrypted');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('connected_email')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_meeting_connections');
    }
};
