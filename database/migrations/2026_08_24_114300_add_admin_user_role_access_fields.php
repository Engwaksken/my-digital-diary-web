<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'system_role')) {
                $table->string('system_role', 50)
                    ->nullable()
                    ->index();
            }

            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 30)
                    ->default('active')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'suspended_reason')) {
                $table->text('suspended_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive. These account-management columns
        // may be shared by other deployed functionality.
    }
};
