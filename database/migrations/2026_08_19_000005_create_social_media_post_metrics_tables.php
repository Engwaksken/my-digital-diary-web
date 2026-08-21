<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('social_media_post_metrics')) {
            Schema::create('social_media_post_metrics', function (Blueprint $table) {
                $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('social_media_post_id')->constrained('social_media_posts')->cascadeOnDelete();
                $table->string('platform',40); $table->string('external_post_id')->nullable();
                foreach (['views','reach','impressions','likes','comments','shares','saves','clicks','replies','engagements'] as $c) $table->unsignedBigInteger($c)->default(0);
                $table->decimal('engagement_rate',8,2)->default(0); $table->timestamp('synced_at')->nullable(); $table->json('raw_metrics')->nullable(); $table->timestamps();
                $table->unique(['social_media_post_id','platform'],'social_post_platform_metric_unique');
                $table->index(['user_id','platform']);
            });
        }
        if (!Schema::hasTable('social_media_metric_snapshots')) {
            Schema::create('social_media_metric_snapshots', function (Blueprint $table) {
                $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('social_media_post_id')->constrained('social_media_posts')->cascadeOnDelete();
                $table->string('platform',40);
                foreach (['views','reach','impressions','likes','comments','shares','saves','clicks','replies','engagements'] as $c) $table->unsignedBigInteger($c)->default(0);
                $table->decimal('engagement_rate',8,2)->default(0); $table->timestamp('captured_at'); $table->timestamps();
                $table->index(['social_media_post_id','platform','captured_at'],'social_metric_snapshot_lookup');
            });
        }
    }
    public function down(): void { Schema::dropIfExists('social_media_metric_snapshots'); Schema::dropIfExists('social_media_post_metrics'); }
};
