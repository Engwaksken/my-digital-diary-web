<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaProviderConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SocialMediaAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $columns = [
            'id',
            'platform',
            'account_name',
            'username',
            'is_active',
        ];

        foreach ([
            'external_account_id',
            'provider_config_id',
            'provider_account_ref',
            'auto_publish_enabled',
            'oauth_expires_at',
        ] as $column) {
            if (Schema::hasColumn('social_media_accounts', $column)) {
                $columns[] = $column;
            }
        }

        $accounts = DB::table('social_media_accounts')
            ->select($columns)
            ->where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->orderBy('account_name')
            ->get()
            ->map(function ($row) {
                $account = (array) $row;

                $provider = $this->enabledProvider(
                    (string) ($row->platform ?? '')
                );

                /*
                 * Mobile receives ONLY safe provider metadata.
                 *
                 * Never expose:
                 * - base_url
                 * - api_key
                 * - api_secret
                 * - auth_header
                 * - settings
                 */
                $account['automatic_api_available'] =
                    (bool) $provider;

                $account['automatic_provider_name'] =
                    $provider?->provider_name;

                $account['automatic_provider_driver'] =
                    $provider?->driver;

                $account['automatic_connection_mode'] =
                    $provider?->connection_mode;

                return $account;
            })
            ->values();

        return response()->json([
            'data' => [
                'whatsapp' => [
                    'number' => $request->user()->whatsapp_number,
                    'channel_name' =>
                        $request->user()->whatsapp_channel_name,
                    'channel_url' =>
                        $request->user()->whatsapp_channel_url,
                ],
                'accounts' => $accounts,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => [
                'required',
                Rule::in([
                    'instagram',
                    'facebook',
                    'x',
                    'tiktok',
                    'linkedin',
                    'whatsapp_status',
                    'whatsapp_channel',
                ]),
            ],
            'account_name' => [
                'required',
                'string',
                'max:120',
            ],
            'username' => [
                'nullable',
                'string',
                'max:180',
            ],
            'external_account_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'provider_account_ref' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $provider = $this->enabledProvider(
            $data['platform']
        );

        $insert = [
            'user_id' => $request->user()->id,
            'platform' => $data['platform'],
            'account_name' => $data['account_name'],
            'username' =>
                trim((string) ($data['username'] ?? ''))
                    ?: null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'external_account_id'
            )
        ) {
            $insert['external_account_id'] =
                trim(
                    (string) (
                        $data['external_account_id'] ?? ''
                    )
                ) ?: null;
        }

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'provider_account_ref'
            )
        ) {
            $insert['provider_account_ref'] =
                trim(
                    (string) (
                        $data['provider_account_ref'] ?? ''
                    )
                ) ?: null;
        }

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'provider_config_id'
            )
        ) {
            $insert['provider_config_id'] =
                $provider?->id;
        }

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'auto_publish_enabled'
            )
        ) {
            $insert['auto_publish_enabled'] = false;
        }

        $id = DB::table(
            'social_media_accounts'
        )->insertGetId($insert);

        return response()->json([
            'message' => $provider
                ? 'Account added. Automatic posting is available.'
                : 'Account added. Automatic posting is not enabled by the administrator for this platform.',
            'data' => [
                'id' => $id,
                'automatic_api_available' =>
                    (bool) $provider,
                'automatic_provider_name' =>
                    $provider?->provider_name,
            ],
        ], 201);
    }

    public function updateWhatsApp(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'whatsapp_number' => [
                'nullable',
                'string',
                'max:30',
            ],
            'whatsapp_channel_name' => [
                'nullable',
                'string',
                'max:180',
            ],
            'whatsapp_channel_url' => [
                'nullable',
                'url',
                'max:500',
            ],
        ]);

        $save = [];

        foreach ($data as $column => $value) {
            if (Schema::hasColumn('users', $column)) {
                $save[$column] = $value;
            }
        }

        if ($save) {
            $request->user()
                ->forceFill($save)
                ->save();
        }

        return response()->json([
            'data' => [
                'number' =>
                    $request->user()->whatsapp_number,
                'channel_name' =>
                    $request->user()->whatsapp_channel_name,
                'channel_url' =>
                    $request->user()->whatsapp_channel_url,
            ],
        ]);
    }

    public function updateAutomaticPublishing(
        Request $request,
        int $account
    ): JsonResponse {
        $row = DB::table('social_media_accounts')
            ->where('id', $account)
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        abort_unless($row, 404);

        $data = $request->validate([
            'enabled' => [
                'required',
                'boolean',
            ],
            'external_account_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'provider_account_ref' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $provider = $this->enabledProvider(
            (string) $row->platform
        );

        if (
            (bool) $data['enabled']
            && ! $provider
        ) {
            return response()->json([
                'message' =>
                    'Automatic posting is not enabled for this platform by the administrator.',
            ], 422);
        }

        $update = [
            'updated_at' => now(),
        ];

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'auto_publish_enabled'
            )
        ) {
            $update['auto_publish_enabled'] =
                (bool) $data['enabled'];
        }

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'external_account_id'
            )
        ) {
            $update['external_account_id'] =
                trim(
                    (string) (
                        $data['external_account_id'] ?? ''
                    )
                ) ?: null;
        }

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'provider_account_ref'
            )
        ) {
            $update['provider_account_ref'] =
                trim(
                    (string) (
                        $data['provider_account_ref'] ?? ''
                    )
                ) ?: null;
        }

        if (
            Schema::hasColumn(
                'social_media_accounts',
                'provider_config_id'
            )
        ) {
            $update['provider_config_id'] =
                $provider?->id;
        }

        DB::table('social_media_accounts')
            ->where('id', $account)
            ->where(
                'user_id',
                $request->user()->id
            )
            ->update($update);

        return response()->json([
            'message' =>
                'Automatic posting settings updated.',
            'data' => [
                'automatic_api_available' =>
                    (bool) $provider,
                'automatic_provider_name' =>
                    $provider?->provider_name,
                'automatic_connection_mode' =>
                    $provider?->connection_mode,
            ],
        ]);
    }

    public function destroy(
        Request $request,
        int $account
    ): JsonResponse {
        $deleted = DB::table(
            'social_media_accounts'
        )
            ->where('id', $account)
            ->where(
                'user_id',
                $request->user()->id
            )
            ->delete();

        abort_if($deleted === 0, 404);

        return response()->json([
            'ok' => true,
        ]);
    }

    private function enabledProvider(
        string $platform
    ): ?SocialMediaProviderConfig {
        if (
            ! Schema::hasTable(
                'social_media_provider_configs'
            )
        ) {
            return null;
        }

        return SocialMediaProviderConfig::query()
            ->where('platform', $platform)
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
