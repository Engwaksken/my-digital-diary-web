<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_food_journals')) {
            return;
        }

        Schema::create('daily_food_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('journal_date');
            $table->text('daily_food_notes');
            $table->timestamps();

            $table->unique(['user_id', 'journal_date']);
            $table->index(['user_id', 'journal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_food_journals');
    }
};
