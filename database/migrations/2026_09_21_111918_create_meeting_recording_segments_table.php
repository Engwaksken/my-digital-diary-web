<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meeting_recording_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_recording_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('audio_path');
            $table->unsignedInteger('start_seconds');
            $table->unsignedInteger('end_seconds');
            $table->unsignedInteger('duration_seconds');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->text('transcript')->nullable();
            $table->json('transcript_segments')->nullable();
            $table->string('transcription_status')->default('pending');
            $table->text('transcription_error')->nullable();
            $table->json('summary')->nullable();
            $table->string('summary_status')->default('pending');
            $table->text('summary_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_recording_segments');
    }
};