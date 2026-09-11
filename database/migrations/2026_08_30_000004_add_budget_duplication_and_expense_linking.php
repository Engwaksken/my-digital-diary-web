<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budgets')) {
            Schema::table('budgets', function (Blueprint $table) {
                if (! Schema::hasColumn('budgets', 'source_budget_id')) {
                    $table->unsignedBigInteger('source_budget_id')
                        ->nullable()
                        ->after('user_id')
                        ->index();
                }

                if (! Schema::hasColumn('budgets', 'is_expensed')) {
                    $table->boolean('is_expensed')
                        ->default(false)
                        ->after('notes')
                        ->index();
                }

                if (! Schema::hasColumn('budgets', 'expensed_at')) {
                    $table->date('expensed_at')
                        ->nullable()
                        ->after('is_expensed');
                }

                if (! Schema::hasColumn('budgets', 'import_source')) {
                    $table->string('import_source', 50)->nullable();
                }

                if (! Schema::hasColumn('budgets', 'import_filename')) {
                    $table->string('import_filename')->nullable();
                }

                if (! Schema::hasColumn('budgets', 'import_confidence')) {
                    $table->decimal('import_confidence', 5, 2)->nullable();
                }

                if (! Schema::hasColumn('budgets', 'import_metadata')) {
                    $table->json('import_metadata')->nullable();
                }
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'budget_id')) {
                    $table->unsignedBigInteger('budget_id')
                        ->nullable()
                        ->after('user_id')
                        ->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive because these columns can point at
        // financial records created from Budget checkboxes.
    }
};
