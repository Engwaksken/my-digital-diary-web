<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->unsignedTinyInteger('day_of_week')->default(0); // Sunday = 0
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->time('run_time')->default('02:00:00');
            $table->string('timezone', 64)->default('Africa/Kampala');
            $table->enum('backup_type', ['database', 'files', 'both'])->default('both');
            $table->enum('provider', ['local', 's3'])->default('local');
            $table->string('s3_key')->nullable();
            $table->text('s3_secret')->nullable();
            $table->string('s3_region')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_endpoint')->nullable();
            $table->boolean('s3_path_style')->default(false);
            $table->unsignedInteger('retention_days')->nullable()->default(90);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('trigger', ['scheduled', 'manual'])->default('scheduled');
            $table->enum('backup_type', ['database', 'files', 'both']);
            $table->string('provider');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_histories');
        Schema::dropIfExists('backup_settings');
    }
};
