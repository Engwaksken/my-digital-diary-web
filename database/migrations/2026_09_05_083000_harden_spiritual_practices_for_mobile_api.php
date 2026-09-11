<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spiritual_practices')) {
            return;
        }

        $additions = [
            'practice_title' => fn (Blueprint $table) => $table->string('practice_title')->nullable(),
            'faith_path' => fn (Blueprint $table) => $table->string('faith_path', 120)->nullable(),
            'custom_faith_path' => fn (Blueprint $table) => $table->string('custom_faith_path', 150)->nullable(),
            'inspirational_text' => fn (Blueprint $table) => $table->text('inspirational_text')->nullable(),
            'source_tradition' => fn (Blueprint $table) => $table->string('source_tradition')->nullable(),
            'gratitude' => fn (Blueprint $table) => $table->text('gratitude')->nullable(),
            'intention' => fn (Blueprint $table) => $table->text('intention')->nullable(),
            'community_place' => fn (Blueprint $table) => $table->string('community_place')->nullable(),
            'mood_before' => fn (Blueprint $table) => $table->string('mood_before', 60)->nullable(),
            'mood_after' => fn (Blueprint $table) => $table->string('mood_after', 60)->nullable(),
            'notes' => fn (Blueprint $table) => $table->text('notes')->nullable(),
            'is_archived' => fn (Blueprint $table) => $table->boolean('is_archived')->default(false),
            'archived_at' => fn (Blueprint $table) => $table->timestamp('archived_at')->nullable(),
            'deleted_at' => fn (Blueprint $table) => $table->softDeletes(),
        ];

        foreach ($additions as $column => $callback) {
            if (! Schema::hasColumn('spiritual_practices', $column)) {
                Schema::table('spiritual_practices', $callback);
            }
        }

        if (! Schema::hasColumn('spiritual_practices', 'practiced_at')) {
            Schema::table('spiritual_practices', function (Blueprint $table): void {
                $table->dateTime('practiced_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('spiritual_practices', 'practice_type')) {
            Schema::table('spiritual_practices', function (Blueprint $table): void {
                $table->string('practice_type', 120)->nullable();
            });
        } else {
            // Remove the legacy restricted ENUM which caused "Data truncated".
            // On SQLite, practice_type is already stored as TEXT, so no ALTER is needed.
            // This block is kept for compatibility with existing MySQL databases.
        }

        if (Schema::hasColumn('spiritual_practices', 'title')
            && Schema::hasColumn('spiritual_practices', 'practice_title')) {
            DB::statement(
                "UPDATE `spiritual_practices`
                 SET `practice_title` = `title`
                 WHERE (`practice_title` IS NULL OR `practice_title` = '')
                   AND `title` IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        // Do not restore the legacy ENUM or remove compatibility columns.
    }
};
