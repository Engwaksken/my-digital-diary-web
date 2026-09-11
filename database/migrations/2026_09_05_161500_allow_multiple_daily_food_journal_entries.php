<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_food_journals')) {
            return;
        }

        $indexName = 'daily_food_journals_user_id_journal_date_unique';

        $exists = $this->hasIndex('daily_food_journals', $indexName);

        if ($exists) {
            Schema::table('daily_food_journals', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }

        $historyIndex = 'daily_food_journals_user_date_history_idx';

        $historyExists = $this->hasIndex('daily_food_journals', $historyIndex);

        if (! $historyExists) {
            Schema::table('daily_food_journals', function (Blueprint $table) use ($historyIndex) {
                $table->index(['user_id', 'journal_date', 'created_at'], $historyIndex);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_food_journals')) {
            return;
        }

        $historyIndex = 'daily_food_journals_user_date_history_idx';

        $historyExists = $this->hasIndex('daily_food_journals', $historyIndex);

        if ($historyExists) {
            Schema::table('daily_food_journals', function (Blueprint $table) use ($historyIndex) {
                $table->dropIndex($historyIndex);
            });
        }

        // Do not restore the previous unique index here because installations
        // may now contain several legitimate entries for one day.
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }
};
