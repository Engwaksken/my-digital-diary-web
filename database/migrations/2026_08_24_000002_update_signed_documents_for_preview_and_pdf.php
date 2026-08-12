<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits signed_documents.file_path into original_file_path (the
 * untouched upload) and signed_file_path (the stamped result, nullable
 * until confirmed) — previously the original was deleted the moment
 * stamping succeeded, which didn't allow for a preview-before-confirming
 * step. position/signed_at/mime_type are recorded for the activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->renameColumn('file_path', 'original_file_path');
        });

        Schema::table('signed_documents', function (Blueprint $table) {
            $table->string('signed_file_path')->nullable()->after('original_file_path');
            $table->string('position')->nullable()->after('was_stamped');
            $table->timestamp('signed_at')->nullable()->after('position');
            $table->string('mime_type')->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->dropColumn(['signed_file_path', 'position', 'signed_at', 'mime_type']);
        });

        Schema::table('signed_documents', function (Blueprint $table) {
            $table->renameColumn('original_file_path', 'file_path');
        });
    }
};
