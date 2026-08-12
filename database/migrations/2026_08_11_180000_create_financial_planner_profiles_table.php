<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('financial_planner_profiles', function(Blueprint $table){
  $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
  $table->unsignedSmallInteger('current_age')->default(30); $table->unsignedSmallInteger('retirement_age')->default(60);
  $table->decimal('current_retirement_savings',14,2)->default(0); $table->decimal('monthly_retirement_contribution',14,2)->nullable();
  $table->decimal('expected_annual_return',5,2)->default(5); $table->decimal('inflation_rate',5,2)->default(3);
  $table->decimal('desired_monthly_retirement_income',14,2)->nullable(); $table->unsignedSmallInteger('retirement_years')->default(20); $table->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('financial_planner_profiles'); }
};
