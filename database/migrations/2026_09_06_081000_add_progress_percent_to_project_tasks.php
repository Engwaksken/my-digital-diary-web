<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_tasks')) {
            return;
        }

        Schema::table('project_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_tasks', 'progress_percent')) {
                $table->unsignedTinyInteger('progress_percent')
                    ->default(0)
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_tasks')) {
            return;
        }

        Schema::table('project_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('project_tasks', 'progress_percent')) {
                $table->dropColumn('progress_percent');
            }
        });
    }
};
