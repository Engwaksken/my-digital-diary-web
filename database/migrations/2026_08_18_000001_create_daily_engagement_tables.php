<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('daily_checkins', function (Blueprint $table) {
   $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->date('checkin_date'); $table->string('type',20);
   $table->unsignedTinyInteger('mood')->nullable(); $table->text('reflection')->nullable();
   $table->text('gratitude')->nullable(); $table->text('tomorrow_focus')->nullable();
   $table->json('meta')->nullable(); $table->timestamps();
   $table->unique(['user_id','checkin_date','type']);
  });
  Schema::create('engagement_streaks', function (Blueprint $table) {
   $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->unsignedInteger('current_streak')->default(0); $table->unsignedInteger('best_streak')->default(0);
   $table->date('last_meaningful_day')->nullable(); $table->timestamps(); $table->unique('user_id');
  });
  Schema::create('engagement_events', function (Blueprint $table) {
   $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->date('event_date'); $table->string('event_type',60); $table->string('source_type',80)->nullable();
   $table->unsignedBigInteger('source_id')->nullable(); $table->json('meta')->nullable(); $table->timestamps();
   $table->index(['user_id','event_date']);
  });
  Schema::create('engagement_preferences', function (Blueprint $table) {
   $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->boolean('morning_brief_enabled')->default(true); $table->boolean('evening_review_enabled')->default(true);
   $table->boolean('weekly_review_enabled')->default(true); $table->boolean('monthly_review_enabled')->default(true);
   $table->boolean('celebrations_enabled')->default(true); $table->boolean('share_cards_enabled')->default(true);
   $table->time('morning_time')->default('08:00:00'); $table->time('evening_time')->default('20:30:00');
   $table->string('timezone',64)->default('Africa/Kampala'); $table->timestamps(); $table->unique('user_id');
  });
 }
 public function down(): void {
  Schema::dropIfExists('engagement_preferences'); Schema::dropIfExists('engagement_events');
  Schema::dropIfExists('engagement_streaks'); Schema::dropIfExists('daily_checkins');
 }
};
