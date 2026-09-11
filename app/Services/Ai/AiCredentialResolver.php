<?php

namespace App\Services\Ai;

use App\Models\SiteSetting;
use App\Models\User;

class AiCredentialResolver
{
    /**
     * Personal OpenAI credentials take priority. The administrator's encrypted
     * database-backed key is the fallback for users without a usable key.
     */
    public function resolveForUser(?User $user, string $provider = 'openai'): ?array
    {
        $provider = strtolower(trim($provider));
        $credential = $user?->activeApiCredential();

        if (
            $credential
            && strtolower(trim((string) $credential->provider)) === $provider
            && trim((string) $credential->api_key) !== ''
        ) {
            return [
                'provider' => $provider,
                'api_key' => trim((string) $credential->api_key),
                'source' => 'user',
            ];
        }

        $settings = SiteSetting::current();

        if (
            $settings->hasDefaultAiKey()
            && strtolower(trim((string) $settings->default_ai_provider)) === $provider
        ) {
            return [
                'provider' => $provider,
                'api_key' => trim((string) $settings->default_ai_api_key),
                'source' => 'shared',
            ];
        }

        return null;
    }
}
