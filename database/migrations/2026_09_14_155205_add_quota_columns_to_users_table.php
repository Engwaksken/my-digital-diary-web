<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'extra_recording_quota_minutes')) {
                $table->integer('extra_recording_quota_minutes')->default(0);
            }
            if (! Schema::hasColumn('users', 'extra_quota_expires_at')) {
                $table->timestamp('extra_quota_expires_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'extra_recording_quota_minutes')) {
                $table->dropColumn('extra_recording_quota_minutes');
            }
            if (Schema::hasColumn('users', 'extra_quota_expires_at')) {
                $table->dropColumn('extra_quota_expires_at');
            }
        });
    }
};
