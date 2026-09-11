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

        // Several parts of My Digital Diary already use soft-delete semantics
        // for spiritual practices. Guarantee the column exists so Eloquent's
        // SoftDeletes global scope and monthly subscription summaries can query
        // the table without "Unknown column spiritual_practices.deleted_at".
        if (! Schema::hasColumn('spiritual_practices', 'deleted_at')) {
            Schema::table('spiritual_practices', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        // The legacy database used a restricted ENUM for practice_type.
        // Newer UI values such as "reflection", "mindfulness", "service",
        // "personal_ritual", etc. are valid application values but MySQL
        // rejects them when the ENUM has not been expanded.
        //
        // Use VARCHAR instead of another ENUM so future practice types can be
        // added without requiring a database migration each time.
        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('spiritual_practices', 'practice_type')) {
            DB::statement("
                ALTER TABLE `spiritual_practices`
                MODIFY `practice_type` VARCHAR(120) NULL
            ");
        }

        // The modern Spiritual Practices UI uses practiced_at. Keep it
        // available even on older databases.
        if (! Schema::hasColumn('spiritual_practices', 'practiced_at')) {
            Schema::table('spiritual_practices', function (Blueprint $table): void {
                $table->dateTime('practiced_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('spiritual_practices', 'practice_title')) {
            Schema::table('spiritual_practices', function (Blueprint $table): void {
                $table->string('practice_title')->nullable();
            });
        }

        // Backfill modern fields from older columns where possible.
        if (
            Schema::hasColumn('spiritual_practices', 'title') &&
            Schema::hasColumn('spiritual_practices', 'practice_title')
        ) {
            DB::table('spiritual_practices')
                ->where(function ($query): void {
                    $query->whereNull('practice_title')->orWhere('practice_title', '');
                })
                ->whereNotNull('title')
                ->update(['practice_title' => DB::raw('title')]);
        }

        if (
            Schema::hasColumn('spiritual_practices', 'practice_date') &&
            Schema::hasColumn('spiritual_practices', 'practiced_at')
        ) {
            DB::table('spiritual_practices')
                ->whereNull('practiced_at')
                ->whereNotNull('practice_date')
                ->orderBy('id')
                ->eachById(function ($practice): void {
                    DB::table('spiritual_practices')
                        ->where('id', $practice->id)
                        ->update(['practiced_at' => $practice->practice_date . ' 00:00:00']);
                });
        }
    }

    public function down(): void
    {
        // Intentionally do not restore the legacy practice_type ENUM because
        // doing so could truncate valid modern practice types.
        //
        // Keep deleted_at as well: existing application code uses soft-delete
        // semantics and removing it could reintroduce production failures.
    }
};
