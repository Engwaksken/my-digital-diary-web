<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reason a document wasn't stamped (missing GD extension, FPDI not
 * installed, unreadable file, etc.) used to only ever be shown once as a
 * flash message right after signing — if the user came back to the
 * document list later, that explanation was gone. This makes it
 * permanent and visible on the document itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->string('stamp_error')->nullable()->after('was_stamped');
        });
    }

    public function down(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->dropColumn('stamp_error');
        });
    }
};
