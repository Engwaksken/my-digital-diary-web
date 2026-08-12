<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('module')->nullable()->comment('plan, income, budget, expense, diet, sleep, health, project, custom');
            $table->text('message')->nullable();
            $table->enum('frequency', ['once', 'daily', 'weekly', 'monthly', 'annually'])->default('once');
            $table->dateTime('next_run_at');
            $table->enum('channel', ['mail', 'database'])->default('database');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
