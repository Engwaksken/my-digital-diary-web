<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per signature placement on a document — a single document can
 * now have several (same or different signatures, same or different
 * pages). Position/size are stored as percentages of that page's
 * dimensions, not pixels, so they stay correct regardless of what size
 * the page ends up rendered at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signed_document_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signed_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signature_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('x_percent', 6, 3);
            $table->decimal('y_percent', 6, 3);
            $table->decimal('width_percent', 6, 3);
            $table->decimal('height_percent', 6, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_document_placements');
    }
};
