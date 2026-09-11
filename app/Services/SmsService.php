<?php

namespace App\Services;

use App\Models\SmsProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsService
{
    public function activeProvider(): ?SmsProvider
    {
        return SmsProvider::query()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function send(string $phone, string $message): array
    {
        $provider = $this->activeProvider();

        if (! $provider) {
            throw new RuntimeException('No enabled SMS provider is configured by the administrator.');
        }

        $phone = trim($phone);
        if ($phone === '') {
            throw new RuntimeException('The recipient does not have a phone number.');
        }

        $headers = array_filter((array) ($provider->extra_headers ?? []), fn ($v) => $v !== null);
        $client = Http::timeout(20)->acceptJson()->withHeaders($headers);

        $key = trim((string) $provider->api_key);
        if ($key !== '') {
            $client = match ($provider->auth_type) {
                'basic' => $client->withBasicAuth($key, ''),
                'header' => $client->withHeaders(['X-API-Key' => $key]),
                'none' => $client,
                default => $client->withToken($key),
            };
        }

        $payload = array_merge((array) ($provider->extra_payload ?? []), [
            $provider->recipient_field ?: 'to' => $phone,
            $provider->message_field ?: 'message' => $message,
        ]);

        if ($provider->sender_field && $provider->sender_id) {
            $payload[$provider->sender_field] = $provider->sender_id;
        }

        $method = strtoupper($provider->http_method ?: 'POST');
        $response = $method === 'GET'
            ? $client->get($provider->endpoint, $payload)
            : $client->send($method, $provider->endpoint, ['json' => $payload]);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 4000),
            'provider' => $provider->name,
        ];
    }
}
