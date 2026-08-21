<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('goal_reflections')) {
            return;
        }

        Schema::create('goal_reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_goal_id')->constrained('personal_goals')->cascadeOnDelete();
            $table->foreignId('goal_milestone_id')->nullable()->constrained('goal_milestones')->nullOnDelete();
            $table->string('reflection_type', 30)->default('goal');
            $table->text('what_worked')->nullable();
            $table->text('challenges')->nullable();
            $table->text('lessons_learned');
            $table->text('repeat_next_time')->nullable();
            $table->text('change_next_time')->nullable();
            $table->unsignedTinyInteger('confidence_after')->nullable();
            $table->date('reflected_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['personal_goal_id', 'reflection_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_reflections');
    }
};
