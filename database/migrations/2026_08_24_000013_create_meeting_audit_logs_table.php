<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A simple, append-only trail of who did what with a meeting's
 * recording/transcript/summary — started/paused/stopped recording,
 * downloaded a file, emailed a summary, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_audit_logs');
    }
};
