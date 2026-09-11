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
        if (! Schema::hasTable('debts')
            || ! Schema::hasColumn('debts', 'reminder_enabled')) {
            return;
        }

        DB::table('debts')
            ->whereNull('reminder_enabled')
            ->update(['reminder_enabled' => false]);

        Schema::table('debts', function (Blueprint $table): void {
            $table->boolean('reminder_enabled')
                ->default(false)
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        // Safe false default intentionally retained.
    }
};
