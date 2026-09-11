<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * api_credentials.provider and ai_plans.provider were both hardcoded
 * ENUM('anthropic','openai') columns — now that admins can add custom AI
 * providers (see ai_providers table), a user's own key or a generated
 * plan needs to be able to reference ANY provider's key, not just the two
 * built-ins. Raw ALTER TABLE (not Schema::table()) since MySQL needs
 * MODIFY COLUMN to change a column's underlying type, not just add/drop one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores the original columns as VARCHAR already and cannot
        // execute MySQL's MODIFY COLUMN syntax used below.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE api_credentials MODIFY COLUMN provider VARCHAR(255) NOT NULL DEFAULT 'anthropic'");
        DB::statement("ALTER TABLE ai_plans MODIFY COLUMN provider VARCHAR(255) NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE api_credentials MODIFY COLUMN provider ENUM('anthropic','openai') NOT NULL DEFAULT 'anthropic'");
        DB::statement("ALTER TABLE ai_plans MODIFY COLUMN provider ENUM('anthropic','openai') NULL");
    }
};
