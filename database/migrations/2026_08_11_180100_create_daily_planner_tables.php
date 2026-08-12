<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('daily_plans', function(Blueprint $table){ $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->date('plan_date'); $table->string('title')->default('My Daily Plan'); $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['user_id','plan_date']); });
  Schema::create('daily_plan_items', function(Blueprint $table){ $table->id(); $table->foreignId('daily_plan_id')->constrained()->cascadeOnDelete(); $table->string('title'); $table->text('description')->nullable(); $table->enum('priority',['low','medium','high'])->default('medium'); $table->time('start_time')->nullable(); $table->time('end_time')->nullable(); $table->boolean('is_completed')->default(false); $table->timestamp('completed_at')->nullable(); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); });
 }
 public function down(): void { Schema::dropIfExists('daily_plan_items'); Schema::dropIfExists('daily_plans'); }
};
