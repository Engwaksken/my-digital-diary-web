<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('level', ['course', 'certificate', 'associate', 'bachelor', 'master', 'phd', 'bootcamp', 'other'])
                ->default('course');
            $table->string('institution')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'on_hold'])->default('planned');
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_plans');
    }
};
