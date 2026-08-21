<?php

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
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('social_media_post_id')->index();
                $table->string('platform', 40)->index();
                $table->string('external_post_id', 255)->nullable()->index();
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
                $table->timestamp('synced_at')->nullable()->index();
                $table->json('raw_metrics')->nullable();
                $table->timestamps();
                $table->unique(['social_media_post_id', 'platform'], 'sm_post_platform_unique');
            });
        } else {
            $columns = [
                'external_post_id' => fn (Blueprint $t) => $t->string('external_post_id', 255)->nullable()->index(),
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
                'synced_at' => fn (Blueprint $t) => $t->timestamp('synced_at')->nullable()->index(),
                'raw_metrics' => fn (Blueprint $t) => $t->json('raw_metrics')->nullable(),
            ];

            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn('social_media_post_metrics', $name)) {
                    Schema::table('social_media_post_metrics', function (Blueprint $table) use ($definition) {
                        $definition($table);
                    });
                }
            }
        }

        if (! Schema::hasTable('social_media_metric_snapshots')) {
            Schema::create('social_media_metric_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('social_media_post_id')->index();
                $table->string('platform', 40)->index();
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

        if (Schema::hasTable('social_media_accounts')) {
            $accountColumns = [
                'external_account_id' => fn (Blueprint $t) => $t->string('external_account_id', 255)->nullable(),
                'oauth_access_token' => fn (Blueprint $t) => $t->text('oauth_access_token')->nullable(),
                'oauth_expires_at' => fn (Blueprint $t) => $t->timestamp('oauth_expires_at')->nullable(),
                'auto_publish_enabled' => fn (Blueprint $t) => $t->boolean('auto_publish_enabled')->default(false),
            ];

            foreach ($accountColumns as $name => $definition) {
                if (! Schema::hasColumn('social_media_accounts', $name)) {
                    Schema::table('social_media_accounts', function (Blueprint $table) use ($definition) {
                        $definition($table);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. These tables may already contain
        // user analytics/history from earlier versions of My Digital Diary.
    }
};
