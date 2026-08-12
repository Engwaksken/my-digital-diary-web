<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a reminder to one or more specific records from its selected
 * "Related Module" (e.g. this reminder is about THESE two specific
 * Plans, not just "Plans" generically). label/item_datetime are
 * deliberately denormalized snapshots taken at link time — cheaper than
 * joining out to 9 different possible model tables every time a
 * reminder is displayed, and stable even if the source record is later
 * renamed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->string('item_label');
            $table->dateTime('item_datetime')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_items');
    }
};
