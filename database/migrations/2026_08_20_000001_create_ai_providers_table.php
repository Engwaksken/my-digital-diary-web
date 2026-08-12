<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anthropic and OpenAI are still hardcoded, built-in providers (they have
 * genuinely different request/response shapes, already implemented in
 * AiPlannerService) — this table is for ADDITIONAL, admin-added ones.
 * Anything an admin adds here is assumed OpenAI-COMPATIBLE, since a large
 * number of providers (Groq, Mistral, Together.ai, Perplexity, a
 * self-hosted Ollama server, etc.) all implement OpenAI's chat-completions
 * request/response shape — so ONE generic caller in AiPlannerService
 * covers any of them, parameterized by base URL + model name, without
 * needing bespoke code per provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('used as api_credentials.provider / ai_plans.provider value');
            $table->string('name')->comment('display name, e.g. "Groq", "Mistral", "My Ollama Server"');
            $table->string('api_base_url')->comment('the OpenAI-compatible chat completions endpoint, e.g. https://api.groq.com/openai/v1/chat/completions');
            $table->string('default_model')->comment('e.g. llama-3.1-70b-versatile, mistral-large-latest');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the two built-ins as ROWS TOO, purely so every provider —
        // built-in or custom — can be looked up the same way for display
        // purposes (name, icon) without special-casing "is this anthropic
        // or openai or something else" in every view. AiPlannerService's
        // actual API-calling logic still branches on these two by key,
        // request-format-wise; this is just for display/dropdown purposes.
        DB::table('ai_providers')->insert([
            ['key' => 'anthropic', 'name' => 'Claude (Anthropic)', 'api_base_url' => 'https://api.anthropic.com/v1/messages', 'default_model' => 'claude-sonnet-4-6', 'is_enabled' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'openai', 'name' => 'ChatGPT (OpenAI)', 'api_base_url' => 'https://api.openai.com/v1/chat/completions', 'default_model' => 'gpt-4o-mini', 'is_enabled' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
