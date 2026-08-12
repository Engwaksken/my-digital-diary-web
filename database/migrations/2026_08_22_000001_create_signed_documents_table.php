<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document a user uploaded to have their saved signature applied to.
 * For IMAGE uploads, the signature is genuinely composited onto the image
 * itself using PHP's built-in GD library (no new dependency) — see
 * SignatureController::stampSignatureOntoImage(). For non-image uploads
 * (PDF, Word docs, etc.), true in-place signing would need a PDF-editing
 * library this app doesn't currently have (e.g. FPDI); those are stored
 * and shareable as-is, honestly not actually stamped — see the
 * `was_stamped` column, shown to the user rather than silently implying
 * every uploaded file got signed when only images actually did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->boolean('was_stamped')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_documents');
    }
};
