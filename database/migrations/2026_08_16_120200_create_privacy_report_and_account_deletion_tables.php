<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('privacy_report_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->json('modules');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'timezone')) $table->string('timezone')->default('Africa/Kampala');
            if (!Schema::hasColumn('users', 'last_expiry_reminder_at')) $table->timestamp('last_expiry_reminder_at')->nullable();
            if (!Schema::hasColumn('users', 'deletion_reason')) $table->text('deletion_reason')->nullable();
            if (!Schema::hasColumn('users', 'deletion_requested_at')) $table->timestamp('deletion_requested_at')->nullable()->index();
            if (!Schema::hasColumn('users', 'scheduled_deletion_at')) $table->timestamp('scheduled_deletion_at')->nullable()->index();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('privacy_report_requests');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['timezone','last_expiry_reminder_at','deletion_reason','deletion_requested_at','scheduled_deletion_at']));
    }
};
