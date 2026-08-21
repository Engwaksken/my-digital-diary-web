<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('objective')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()
                ->constrained('social_media_campaigns')->nullOnDelete();

            $table->string('title');
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->string('media_type', 20)->default('text');
            $table->string('media_path')->nullable();

            $table->json('platforms');
            $table->json('platform_content')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->string('status', 24)->default('draft');
            $table->string('approval_status', 24)->default('approved');
            $table->text('last_error')->nullable();
            $table->json('publishing_results')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
        Schema::dropIfExists('social_media_campaigns');
    }
};
