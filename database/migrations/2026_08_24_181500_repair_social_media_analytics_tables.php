<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_post_metrics')) {
            Schema::create('social_media_post_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('social_media_post_id')->index();
                $table->string('platform', 50);
                $table->string('external_post_id')->nullable();
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedBigInteger('reach')->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('likes')->default(0);
                $table->unsignedBigInteger('comments')->default(0);
                $table->unsignedBigInteger('shares')->default(0);
                $table->unsignedBigInteger('saves')->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('replies')->default(0);
                $table->unsignedBigInteger('engagements')->default(0);
                $table->decimal('engagement_rate', 8, 2)->default(0);
                $table->timestamp('synced_at')->nullable();
                $table->json('raw_metrics')->nullable();
                $table->timestamps();
                $table->unique(['social_media_post_id', 'platform'], 'smp_metric_post_platform_unique');
            });
        } else {
            $this->repairMetricsTable();
        }

        if (! Schema::hasTable('social_media_metric_snapshots')) {
            Schema::create('social_media_metric_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('social_media_post_id')->index();
                $table->string('platform', 50);
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedBigInteger('reach')->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('likes')->default(0);
                $table->unsignedBigInteger('comments')->default(0);
                $table->unsignedBigInteger('shares')->default(0);
                $table->unsignedBigInteger('saves')->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('replies')->default(0);
                $table->unsignedBigInteger('engagements')->default(0);
                $table->decimal('engagement_rate', 8, 2)->default(0);
                $table->timestamp('captured_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    private function repairMetricsTable(): void
    {
        $definitions = [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->index(),
            'social_media_post_id' => fn (Blueprint $t) => $t->unsignedBigInteger('social_media_post_id')->nullable()->index(),
            'platform' => fn (Blueprint $t) => $t->string('platform', 50)->nullable(),
            'external_post_id' => fn (Blueprint $t) => $t->string('external_post_id')->nullable(),
            'views' => fn (Blueprint $t) => $t->unsignedBigInteger('views')->default(0),
            'reach' => fn (Blueprint $t) => $t->unsignedBigInteger('reach')->default(0),
            'impressions' => fn (Blueprint $t) => $t->unsignedBigInteger('impressions')->default(0),
            'likes' => fn (Blueprint $t) => $t->unsignedBigInteger('likes')->default(0),
            'comments' => fn (Blueprint $t) => $t->unsignedBigInteger('comments')->default(0),
            'shares' => fn (Blueprint $t) => $t->unsignedBigInteger('shares')->default(0),
            'saves' => fn (Blueprint $t) => $t->unsignedBigInteger('saves')->default(0),
            'clicks' => fn (Blueprint $t) => $t->unsignedBigInteger('clicks')->default(0),
            'replies' => fn (Blueprint $t) => $t->unsignedBigInteger('replies')->default(0),
            'engagements' => fn (Blueprint $t) => $t->unsignedBigInteger('engagements')->default(0),
            'engagement_rate' => fn (Blueprint $t) => $t->decimal('engagement_rate', 8, 2)->default(0),
            'synced_at' => fn (Blueprint $t) => $t->timestamp('synced_at')->nullable(),
            'raw_metrics' => fn (Blueprint $t) => $t->json('raw_metrics')->nullable(),
        ];

        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn('social_media_post_metrics', $column)) {
                Schema::table('social_media_post_metrics', $definition);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive repair migration for a live production database.
    }
};
