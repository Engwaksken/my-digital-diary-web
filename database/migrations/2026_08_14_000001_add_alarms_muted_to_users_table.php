<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Global kill-switch for the in-app reminder alarm (popup +
            // sound), independent of each individual reminder's own
            // alarm_enabled checkbox — this is "mute everything right now"
            // without having to edit every reminder one by one.
            $table->boolean('alarms_muted')->default(false)->after('theme_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('alarms_muted');
        });
    }
};
