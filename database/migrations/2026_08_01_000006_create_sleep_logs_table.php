<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sleep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('sleep_date');
            $table->time('bed_time')->nullable();
            $table->time('wake_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->enum('quality', ['poor', 'fair', 'good', 'excellent'])->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sleep_logs');
    }
};
