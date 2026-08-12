<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A meeting can have more than one recording over time (re-recorded,
 * multiple sessions, etc.) — each row here is one full
 * record/pause/resume/stop session, with its own audio file, transcript,
 * and AI-generated summary once those steps have run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('recording')->comment('recording, paused, completed, failed');
            $table->timestamp('consent_given_at')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->longText('transcript')->nullable();
            $table->json('transcript_segments')->nullable()->comment('array of {start_seconds, end_seconds, speaker, text} once transcribed');
            $table->string('transcription_status')->default('pending')->comment('pending, processing, completed, failed');
            $table->text('transcription_error')->nullable();

            $table->json('summary')->nullable()->comment('{main_points, decisions, action_items, questions_for_followup}');
            $table->string('summary_status')->default('pending')->comment('pending, processing, completed, failed');
            $table->text('summary_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_recordings');
    }
};
