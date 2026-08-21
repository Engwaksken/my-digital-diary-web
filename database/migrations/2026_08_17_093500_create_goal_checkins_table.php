<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('goal_checkins')) return;
        Schema::create('goal_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_goal_id')->constrained('personal_goals')->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('planned_action')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['personal_goal_id','week_start']);
            $table->index(['user_id','week_start']);
        });
    }
    public function down(): void { Schema::dropIfExists('goal_checkins'); }
};
