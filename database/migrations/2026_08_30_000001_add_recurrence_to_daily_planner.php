<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plan_items', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_plan_items', 'repeat_type')) {
                $table->string('repeat_type', 30)
                    ->default('once')
                    ->after('sort_order');
            }

            if (! Schema::hasColumn('daily_plan_items', 'repeat_days')) {
                $table->json('repeat_days')
                    ->nullable()
                    ->after('repeat_type');
            }

            if (! Schema::hasColumn('daily_plan_items', 'repeat_interval')) {
                $table->unsignedSmallInteger('repeat_interval')
                    ->default(1)
                    ->after('repeat_days');
            }

            if (! Schema::hasColumn('daily_plan_items', 'repeat_starts_on')) {
                $table->date('repeat_starts_on')
                    ->nullable()
                    ->after('repeat_interval');
            }

            if (! Schema::hasColumn('daily_plan_items', 'repeat_ends_on')) {
                $table->date('repeat_ends_on')
                    ->nullable()
                    ->after('repeat_starts_on');
            }

            if (! Schema::hasColumn('daily_plan_items', 'recurrence_group_id')) {
                $table->uuid('recurrence_group_id')
                    ->nullable()
                    ->after('repeat_ends_on')
                    ->index();
            }

            if (! Schema::hasColumn('daily_plan_items', 'series_parent_id')) {
                $table->foreignId('series_parent_id')
                    ->nullable()
                    ->after('recurrence_group_id')
                    ->constrained('daily_plan_items')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('daily_plan_item_occurrences')) {
            Schema::create('daily_plan_item_occurrences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_plan_item_id')
                    ->constrained('daily_plan_items')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->date('occurrence_date');

                $table->boolean('is_completed')->default(false);
                $table->timestamp('completed_at')->nullable();

                // A deleted/skipped single occurrence does not delete the series.
                $table->boolean('is_skipped')->default(false);

                // Optional "this occurrence only" edits.
                $table->string('title_override')->nullable();
                $table->text('description_override')->nullable();
                $table->string('priority_override', 20)->nullable();
                $table->time('start_time_override')->nullable();
                $table->time('end_time_override')->nullable();
                $table->foreignId('personal_goal_id_override')
                    ->nullable()
                    ->constrained('personal_goals')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    ['daily_plan_item_id', 'occurrence_date'],
                    'dp_item_occurrence_unique'
                );

                $table->index(
                    ['user_id', 'occurrence_date'],
                    'dp_occurrence_user_date_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_item_occurrences');

        Schema::table('daily_plan_items', function (Blueprint $table) {
            if (Schema::hasColumn('daily_plan_items', 'series_parent_id')) {
                $table->dropConstrainedForeignId('series_parent_id');
            }

            foreach ([
                'repeat_type',
                'repeat_days',
                'repeat_interval',
                'repeat_starts_on',
                'repeat_ends_on',
                'recurrence_group_id',
            ] as $column) {
                if (Schema::hasColumn('daily_plan_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
