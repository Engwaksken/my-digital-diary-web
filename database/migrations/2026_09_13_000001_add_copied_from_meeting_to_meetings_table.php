<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('copied_from_meeting_id')
                ->nullable()
                ->after('user_id')
                ->constrained('meetings')
                ->nullOnDelete();
            $table->unique(['user_id', 'copied_from_meeting_id'], 'meetings_user_copied_from_unique');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique('meetings_user_copied_from_unique');
            $table->dropForeign(['copied_from_meeting_id']);
            $table->dropColumn('copied_from_meeting_id');
        });
    }
};
