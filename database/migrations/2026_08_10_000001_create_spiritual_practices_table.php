<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spiritual_practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('practice_type', [
                'prayer', 'meditation', 'scripture_reading', 'worship',
                'fasting', 'service', 'journaling', 'other',
            ]);
            $table->string('title')->nullable()->comment('e.g. "Morning devotion", "Evening meditation"');
            $table->date('practiced_at');
            $table->integer('duration_minutes')->nullable();
            $table->text('reflection')->nullable();
            $table->date('next_planned_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spiritual_practices');
    }
};
