<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['bug', 'feature_request', 'general', 'complaint', 'compliment'])->default('general');
            $table->string('subject');
            $table->text('message');
            $table->unsignedTinyInteger('rating')->nullable()->comment('1-5, optional');
            $table->enum('status', ['new', 'reviewed', 'resolved'])->default('new');
            $table->text('admin_notes')->nullable()->comment('Admin-only, never shown to the submitting user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
