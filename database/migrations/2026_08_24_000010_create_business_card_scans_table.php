<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per visit to a business card's public page — whether someone
 * actually scanned the QR code or just opened the shared link directly,
 * both count as a "scan" for this purpose; there's no way to tell them
 * apart from a plain HTTP request either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_card_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_card_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_card_scans');
    }
};
