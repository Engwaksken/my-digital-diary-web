<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            if (! Schema::hasColumn('budgets', 'application_type')) {
                $table->string('application_type', 30)
                    ->default('expense')
                    ->after('expensed_at')
                    ->index();
            }

            if (! Schema::hasColumn('budgets', 'debt_id')) {
                $table->unsignedBigInteger('debt_id')
                    ->nullable()
                    ->after('application_type')
                    ->index();
            }

            if (! Schema::hasColumn('budgets', 'applied_amount')) {
                $table->decimal('applied_amount', 18, 2)
                    ->default(0)
                    ->after('debt_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            foreach (['applied_amount', 'debt_id', 'application_type'] as $column) {
                if (Schema::hasColumn('budgets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
