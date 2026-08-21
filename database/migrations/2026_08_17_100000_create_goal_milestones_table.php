<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_goal_id')->constrained('personal_goals')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->enum('status', ['pending','in_progress','completed'])->default('pending');
            $table->unsignedTinyInteger('weight')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','personal_goal_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_milestones');
    }
};
