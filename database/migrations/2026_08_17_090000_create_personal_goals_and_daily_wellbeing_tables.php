<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->decimal('target_value', 15, 2)->nullable();
            $table->decimal('current_value', 15, 2)->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->enum('status', ['not_started','in_progress','completed','paused'])->default('in_progress');
            $table->enum('priority', ['low','medium','high'])->default('medium');
            $table->text('notes')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->index(['user_id','module','status']);
        });

        Schema::create('daily_wellbeing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('log_date')->index();
            $table->unsignedSmallInteger('water_ml')->default(0);
            $table->unsignedSmallInteger('water_target_ml')->default(2000);
            $table->unsignedSmallInteger('exercise_minutes')->default(0);
            $table->unsignedSmallInteger('steps')->nullable();
            $table->enum('mood', ['low','okay','good','great'])->nullable();
            $table->boolean('self_care_done')->default(false);
            $table->boolean('screen_break_done')->default(false);
            $table->boolean('reflection_done')->default(false);
            $table->text('self_care_activity')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->unique(['user_id','log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_wellbeing_logs');
        Schema::dropIfExists('personal_goals');
    }
};
