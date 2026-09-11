<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            if (!Schema::hasColumn('budgets', 'import_source')) {
                $table->string('import_source', 30)->nullable();
            }
            if (!Schema::hasColumn('budgets', 'import_filename')) {
                $table->string('import_filename')->nullable();
            }
            if (!Schema::hasColumn('budgets', 'import_confidence')) {
                $table->decimal('import_confidence', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('budgets', 'import_metadata')) {
                $table->json('import_metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            foreach (['import_source','import_filename','import_confidence','import_metadata'] as $column) {
                if (Schema::hasColumn('budgets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
