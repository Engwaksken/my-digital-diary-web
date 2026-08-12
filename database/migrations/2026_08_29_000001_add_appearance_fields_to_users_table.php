<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Font family/size are new on BOTH web and mobile — this app never had
 * them before at all. Nullable with sensible defaults applied in the
 * User model's accessor methods (not here), same pattern as
 * theme_color/theme_color_secondary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('font_family', 40)->nullable()->after('theme_color_secondary');
            $table->unsignedTinyInteger('font_size')->nullable()->after('font_family');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['font_family', 'font_size']);
        });
    }
};
