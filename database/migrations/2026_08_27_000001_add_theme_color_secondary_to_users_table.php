<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'theme_color_secondary')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('theme_color_secondary', 7)
                    ->nullable()
                    ->after('theme_color');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'theme_color_secondary')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('theme_color_secondary');
            });
        }
    }
};
