<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('tags')->nullable();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_favorite')->default(false)->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();
            $table->index(['user_id','updated_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('notes'); }
};
