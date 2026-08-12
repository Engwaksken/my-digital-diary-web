<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds precise drag-and-drop coordinates alongside the existing 9-grid
 * `position` preset. position_x/position_y are the signature's top-left
 * corner as a PERCENTAGE (0-100) of the document's width/height — when
 * present, they take priority over the grid preset, which remains as a
 * fallback for anyone whose browser can't do the drag interaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->decimal('position_x', 5, 2)->nullable()->after('position');
            $table->decimal('position_y', 5, 2)->nullable()->after('position_x');
        });
    }

    public function down(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
    }
};
