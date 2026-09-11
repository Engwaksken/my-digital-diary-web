<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SocialMediaProviderConfig extends Model
{
    protected $fillable = [
        'platform',
        'provider_name',
        'driver',
        'connection_mode',
        'base_url',
        'auth_type',
        'auth_header',
        'api_key',
        'api_secret',
        'settings',
        'is_enabled',
        'is_default',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function setApiKeyAttribute($value): void
    {
        if ($value === null || trim((string) $value) === '') {
            $this->attributes['api_key'] = null;
            return;
        }

        $this->attributes['api_key'] = Crypt::encryptString((string) $value);
    }

    public function setApiSecretAttribute($value): void
    {
        if ($value === null || trim((string) $value) === '') {
            $this->attributes['api_secret'] = null;
            return;
        }

        $this->attributes['api_secret'] = Crypt::encryptString((string) $value);
    }

    public function decryptedApiKey(): ?string
    {
        return $this->decryptValue($this->attributes['api_key'] ?? null);
    }

    public function decryptedApiSecret(): ?string
    {
        return $this->decryptValue($this->attributes['api_secret'] ?? null);
    }

    private function decryptValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function enabledFor(string $platform): ?self
    {
        return static::query()
            ->where('platform', $platform)
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
