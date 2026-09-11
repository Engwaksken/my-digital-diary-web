<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_tasks')) return;

        Schema::table('project_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_tasks', 'due_time')) {
                $table->time('due_time')->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('project_tasks', 'reminder_enabled')) {
                $table->boolean('reminder_enabled')->default(false)->after('due_time');
            }
            if (! Schema::hasColumn('project_tasks', 'reminder_offset_minutes')) {
                $table->integer('reminder_offset_minutes')->nullable()->after('reminder_enabled');
            }
            if (! Schema::hasColumn('project_tasks', 'reminder_custom_at')) {
                $table->dateTime('reminder_custom_at')->nullable()->after('reminder_offset_minutes');
            }
            if (! Schema::hasColumn('project_tasks', 'reminder_channel')) {
                $table->string('reminder_channel', 30)->nullable()->after('reminder_custom_at');
            }
            if (! Schema::hasColumn('project_tasks', 'reminder_id')) {
                $table->unsignedBigInteger('reminder_id')->nullable()->after('reminder_channel')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_tasks')) return;
        Schema::table('project_tasks', function (Blueprint $table): void {
            foreach ([
                'reminder_id','reminder_channel','reminder_custom_at',
                'reminder_offset_minutes','reminder_enabled','due_time',
            ] as $column) {
                if (Schema::hasColumn('project_tasks', $column)) $table->dropColumn($column);
            }
        });
    }
};
