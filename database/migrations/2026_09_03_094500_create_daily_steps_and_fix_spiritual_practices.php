<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_steps')) {
            Schema::create('daily_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('tracking_date');
                $table->unsignedBigInteger('steps')->default(0);
                $table->unsignedInteger('daily_goal')->default(10000);
                $table->boolean('is_tracking')->default(false);
                $table->timestamp('tracking_started_at')->nullable();
                $table->timestamp('tracking_stopped_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->unsignedBigInteger('device_baseline_steps')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'tracking_date']);
                $table->index(['user_id', 'is_tracking']);
            });
        }

        if (Schema::hasTable('spiritual_practices')) {
            Schema::table('spiritual_practices', function (Blueprint $table): void {
                if (! Schema::hasColumn('spiritual_practices', 'practice_title')) {
                    $table->string('practice_title')->nullable();
                }
                if (! Schema::hasColumn('spiritual_practices', 'practice_type')) {
                    $table->string('practice_type', 120)->nullable();
                }
                if (! Schema::hasColumn('spiritual_practices', 'faith_path')) {
                    $table->string('faith_path', 120)->nullable();
                }
                if (! Schema::hasColumn('spiritual_practices', 'practiced_at')) {
                    $table->dateTime('practiced_at')->nullable();
                }
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_frequency')) {
                    $table->string('recurrence_frequency', 20)->nullable();
                }
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_days_of_week')) {
                    $table->json('recurrence_days_of_week')->nullable();
                }
                if (! Schema::hasColumn('spiritual_practices', 'recurrence_ends_at')) {
                    $table->date('recurrence_ends_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_steps');
    }
};
