<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('activity')->comment('e.g. Running, Weights, Yoga, Swimming');
            $table->unsignedInteger('duration_minutes');
            $table->enum('intensity', ['light', 'moderate', 'intense'])->default('moderate');
            $table->unsignedInteger('calories_burned')->nullable();
            $table->dateTime('performed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_logs');
    }
};
