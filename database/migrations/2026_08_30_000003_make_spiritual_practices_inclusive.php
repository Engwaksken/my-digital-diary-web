<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spiritual_practices')) {
            return;
        }

        $definitions = [
            'faith_path' => fn (Blueprint $table) => $table->string('faith_path', 100)->nullable(),
            'custom_faith_path' => fn (Blueprint $table) => $table->string('custom_faith_path', 150)->nullable(),
            'practice_title' => fn (Blueprint $table) => $table->string('practice_title')->nullable(),
            'inspirational_text' => fn (Blueprint $table) => $table->text('inspirational_text')->nullable(),
            'source_tradition' => fn (Blueprint $table) => $table->string('source_tradition')->nullable(),
            'gratitude' => fn (Blueprint $table) => $table->text('gratitude')->nullable(),
            'intention' => fn (Blueprint $table) => $table->text('intention')->nullable(),
            'community_place' => fn (Blueprint $table) => $table->string('community_place')->nullable(),
            'mood_before' => fn (Blueprint $table) => $table->string('mood_before', 60)->nullable(),
            'mood_after' => fn (Blueprint $table) => $table->string('mood_after', 60)->nullable(),
        ];

        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn('spiritual_practices', $column)) {
                Schema::table('spiritual_practices', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: older deployments may already use
        // some of these columns. Do not drop user spiritual-growth data.
    }
};
