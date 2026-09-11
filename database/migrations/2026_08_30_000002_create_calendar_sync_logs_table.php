<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->date('sync_from_date');
            $table->date('sync_to_date')->nullable();
            $table->boolean('include_recurring')->default(true);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'provider', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_logs');
    }
};
