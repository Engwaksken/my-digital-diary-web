<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_posts')) {
            return;
        }

        Schema::table('social_media_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_media_posts', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable();
            }

            if (! Schema::hasColumn('social_media_posts', 'posting_started_at')) {
                $table->timestamp('posting_started_at')->nullable();
            }

            if (! Schema::hasColumn('social_media_posts', 'posting_notification_sent_at')) {
                $table->timestamp('posting_notification_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_media_posts')) {
            return;
        }

        Schema::table('social_media_posts', function (Blueprint $table) {
            foreach ([
                'reminder_sent_at',
                'posting_started_at',
                'posting_notification_sent_at',
            ] as $column) {
                if (Schema::hasColumn('social_media_posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
