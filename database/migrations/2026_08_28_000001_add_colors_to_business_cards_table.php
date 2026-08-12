<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a user pick both gradient colors for their PUBLIC business card
 * background — same two-color-gradient pattern as the personal profile
 * theme (see 2026_08_27_000001_add_theme_color_secondary_to_users_table),
 * with the same "second color is optional, auto-derived if left blank"
 * behavior for backward compatibility with every existing card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->string('card_color', 7)->nullable()->after('is_published');
            $table->string('card_color_secondary', 7)->nullable()->after('card_color');
        });
    }

    public function down(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->dropColumn(['card_color', 'card_color_secondary']);
        });
    }
};
