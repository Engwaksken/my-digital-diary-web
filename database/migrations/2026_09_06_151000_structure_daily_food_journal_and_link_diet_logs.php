<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_food_journals')) {
            Schema::table('daily_food_journals', function (Blueprint $table): void {
                if (! Schema::hasColumn('daily_food_journals', 'meal_type')) {
                    $table->string('meal_type', 30)
                        ->nullable()
                        ->after('journal_date');
                }

                if (! Schema::hasColumn('daily_food_journals', 'food_items')) {
                    $table->json('food_items')
                        ->nullable()
                        ->after('meal_type');
                }
            });
        }

        if (Schema::hasTable('diet_logs')
            && ! Schema::hasColumn('diet_logs', 'daily_food_journal_id')) {
            Schema::table('diet_logs', function (Blueprint $table): void {
                $table->unsignedBigInteger('daily_food_journal_id')
                    ->nullable()
                    ->after('user_id')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('diet_logs')
            && Schema::hasColumn('diet_logs', 'daily_food_journal_id')) {
            Schema::table('diet_logs', function (Blueprint $table): void {
                $table->dropColumn('daily_food_journal_id');
            });
        }

        if (Schema::hasTable('daily_food_journals')) {
            Schema::table('daily_food_journals', function (Blueprint $table): void {
                foreach (['food_items', 'meal_type'] as $column) {
                    if (Schema::hasColumn('daily_food_journals', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
