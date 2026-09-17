<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('category', ['family', 'work', 'friend', 'romantic', 'other'])->default('family');
            $table->string('relation_label')->nullable()->comment('e.g. Spouse, Manager, Sister, Best Friend');
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->date('last_meaningful_interaction')->nullable();
            $table->date('next_planned_interaction')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_relationships');
    }
};
