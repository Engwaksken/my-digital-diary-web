<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaProviderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class AdminSocialMediaProviderController extends Controller
{
    public function index()
    {
        return view('admin.social-media-providers.index', [
            'providers' => SocialMediaProviderConfig::query()
                ->orderBy('platform')
                ->orderByDesc('is_default')
                ->orderBy('provider_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $provider = new SocialMediaProviderConfig();
        $this->fillProvider($provider, $data, true);
        $provider->save();

        $this->normaliseDefault($provider);

        return back()->with('success', 'Social media API provider added.');
    }

    public function update(Request $request, SocialMediaProviderConfig $provider)
    {
        $data = $this->validated($request, $provider->id);

        $this->fillProvider($provider, $data, false);
        $provider->save();

        $this->normaliseDefault($provider);

        return back()->with('success', 'Social media API provider updated.');
    }

    public function toggle(SocialMediaProviderConfig $provider)
    {
        $provider->update([
            'is_enabled' => ! $provider->is_enabled,
        ]);

        return back()->with(
            'success',
            $provider->is_enabled
                ? 'Provider enabled.'
                : 'Provider disabled.'
        );
    }

    public function makeDefault(SocialMediaProviderConfig $provider)
    {
        SocialMediaProviderConfig::query()
            ->where('platform', $provider->platform)
            ->update(['is_default' => false]);

        $provider->forceFill([
            'is_default' => true,
            'is_enabled' => true,
        ])->save();

        return back()->with('success', 'Default provider updated.');
    }

    public function destroy(SocialMediaProviderConfig $provider)
    {
        $provider->delete();

        return back()->with('success', 'Provider removed.');
    }

    public function test(SocialMediaProviderConfig $provider)
    {
        try {
            $driver = strtolower($provider->driver);
            $baseUrl = rtrim((string) $provider->base_url, '/');
            $key = $provider->decryptedApiKey();

            if ($baseUrl === '') {
                return back()->with('error', 'Provider Base URL is missing.');
            }

            if ($driver === 'whatsscale') {
                $response = Http::acceptJson()
                    ->withHeaders(['X-Api-Key' => (string) $key])
                    ->timeout(20)
                    ->get($baseUrl.'/api/auth/test');
            } elseif ($driver === 'waha') {
                $request = Http::acceptJson()->timeout(20);

                if ($key) {
                    $request = $request->withHeaders([
                        $provider->auth_header ?: 'X-Api-Key' => $key,
                    ]);
                }

                $response = $request->get($baseUrl.'/api/sessions');
            } else {
                $request = Http::acceptJson()->timeout(20);
                if ($key && $provider->auth_type === 'bearer') {
                    $request = $request->withToken($key);
                } elseif ($key && $provider->auth_type === 'header') {
                    $request = $request->withHeaders([
                        $provider->auth_header ?: 'X-Api-Key' => $key,
                    ]);
                }

                $testPath = trim((string) data_get($provider->settings, 'test_endpoint', ''));
                if ($testPath === '') {
                    return back()->with('error', 'Add settings.test_endpoint for this generic provider before testing.');
                }

                $response = $request->get($baseUrl.'/'.ltrim($testPath, '/'));
            }

            if (! $response->successful()) {
                return back()->with(
                    'error',
                    'Provider test failed with HTTP '.$response->status().'.'
                );
            }

            return back()->with('success', 'Provider connection test succeeded.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Provider test failed: '.$e->getMessage());
        }
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'platform' => ['required', Rule::in([
                'facebook',
                'instagram',
                'x',
                'tiktok',
                'linkedin',
                'whatsapp_status',
                'whatsapp_channel',
            ])],
            'provider_name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::in([
                'whatsscale',
                'waha',
                'meta',
                'x',
                'tiktok',
                'linkedin',
                'generic',
            ])],
            'connection_mode' => ['required', Rule::in([
                'shared_api_key',
                'user_oauth',
            ])],
            'base_url' => ['nullable', 'url', 'max:2000'],
            'auth_type' => ['required', Rule::in(['header', 'bearer', 'none'])],
            'auth_header' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:20000'],
            'api_secret' => ['nullable', 'string', 'max:20000'],
            'settings_json' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function fillProvider(
        SocialMediaProviderConfig $provider,
        array $data,
        bool $creating
    ): void {
        $settings = [];

        if (! empty($data['settings_json'])) {
            $settings = json_decode($data['settings_json'], true);

            if (! is_array($settings)) {
                abort(422, 'Settings JSON is invalid.');
            }
        }

        $provider->platform = $data['platform'];
        $provider->provider_name = $data['provider_name'];
        $provider->driver = $data['driver'];
        $provider->connection_mode = $data['connection_mode'];
        $provider->base_url = trim((string) ($data['base_url'] ?? '')) ?: null;
        $provider->auth_type = $data['auth_type'];
        $provider->auth_header = trim((string) ($data['auth_header'] ?? '')) ?: null;
        $provider->settings = $settings;
        $provider->is_enabled = (bool) ($data['is_enabled'] ?? false);
        $provider->is_default = (bool) ($data['is_default'] ?? false);

        if (! empty($data['api_key'])) {
            $provider->api_key = $data['api_key'];
        } elseif ($creating) {
            $provider->api_key = null;
        }

        if (! empty($data['api_secret'])) {
            $provider->api_secret = $data['api_secret'];
        } elseif ($creating) {
            $provider->api_secret = null;
        }
    }

    private function normaliseDefault(SocialMediaProviderConfig $provider): void
    {
        if (! $provider->is_default) {
            return;
        }

        SocialMediaProviderConfig::query()
            ->where('platform', $provider->platform)
            ->whereKeyNot($provider->id)
            ->update(['is_default' => false]);

        if (! $provider->is_enabled) {
            $provider->forceFill(['is_enabled' => true])->save();
        }
    }
}
