<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32)->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('morning_brief')->default(true);
            $table->boolean('evening_review')->default(true);
            $table->boolean('weekly_review')->default(true);
            $table->boolean('monthly_review')->default(true);
            $table->boolean('milestones')->default(true);
            $table->boolean('product_tips')->default(true);
            $table->boolean('referral_updates')->default(true);
            $table->time('morning_time')->default('08:00:00');
            $table->time('evening_time')->default('20:30:00');
            $table->string('timezone', 64)->default('Africa/Kampala');
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('growth_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('growth_challenge_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('started_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['growth_challenge_id', 'user_id']);
        });

        Schema::create('product_growth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 80);
            $table->string('source', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_growth_events');
        Schema::dropIfExists('growth_challenge_enrolments');
        Schema::dropIfExists('growth_challenges');
        Schema::dropIfExists('communication_preferences');
        Schema::dropIfExists('user_referrals');
    }
};
