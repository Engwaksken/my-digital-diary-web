<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('health_profiles')) {
            return;
        }

        Schema::create('health_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('weight_kg', 6, 1)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->string('age_range', 20)->nullable();
            $table->string('activity_level', 30)->nullable();
            $table->string('health_goal', 40)->nullable();

            $table->text('food_allergies')->nullable();
            $table->text('dietary_preferences')->nullable();
            $table->text('health_conditions')->nullable();
            $table->text('sleep_challenges')->nullable();

            $table->time('usual_wake_time')->nullable();
            $table->time('usual_bed_time')->nullable();

            $table->json('sleep_advice')->nullable();
            $table->timestamp('sleep_advice_generated_at')->nullable();
            $table->json('diet_advice')->nullable();
            $table->timestamp('diet_advice_generated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_profiles');
    }
};
