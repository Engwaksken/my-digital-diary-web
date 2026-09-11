<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('insight_date')->index();
            $table->string('category', 80)->nullable();
            $table->string('type', 30)->default('personal');
            $table->string('title');
            $table->text('message');
            $table->string('action')->nullable();
            $table->string('destination', 80)->nullable();
            $table->string('tone', 30)->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('model')->nullable();
            $table->string('content_hash', 64)->index();
            $table->string('context_hash', 64)->nullable()->index();
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_insights');
    }
};
