<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SocialMediaAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = collect();
        $tableReady = Schema::hasTable('social_media_accounts');

        if ($tableReady && Schema::hasColumn('social_media_accounts', 'user_id')) {
            $query = DB::table('social_media_accounts')
                ->where('user_id', $request->user()->id);

            if (Schema::hasColumn('social_media_accounts', 'platform')) {
                $query->orderBy('platform');
            } else {
                $query->orderBy('id');
            }

            $accounts = $query->get();
        }

        return view('profile.social-media', [
            'accounts' => $accounts,
            'accountsTableReady' => $tableReady,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAccountsTable();

        $data = $request->validate([
            'platform' => ['required', Rule::in([
                'instagram', 'facebook', 'x', 'tiktok', 'linkedin',
            ])],
            'account_name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:180'],
        ]);

        $row = [
            'user_id' => $request->user()->id,
            'platform' => $data['platform'],
            'account_name' => trim($data['account_name']),
            'username' => isset($data['username']) && trim((string) $data['username']) !== ''
                ? trim((string) $data['username'])
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('social_media_accounts', 'is_active')) {
            $row['is_active'] = true;
        }

        if (Schema::hasColumn('social_media_accounts', 'auto_publish_enabled')) {
            $row['auto_publish_enabled'] = false;
        }

        DB::table('social_media_accounts')->insert($row);

        return back()->with('success', 'Social media account added.');
    }

    public function update(
        Request $request,
        int $account
    ): RedirectResponse {
        $this->ensureAccountsTable();

        $data = $request->validate([
            'platform' => ['required', Rule::in([
                'instagram', 'facebook', 'x', 'tiktok', 'linkedin',
                'whatsapp_status', 'whatsapp_channel',
            ])],
            'account_name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
            'auto_publish_enabled' => ['nullable', 'boolean'],
        ]);

        $query = DB::table('social_media_accounts')
            ->where('id', $account)
            ->where('user_id', $request->user()->id);

        abort_unless($query->exists(), 404);

        $update = [
            'platform' => $data['platform'],
            'account_name' => trim($data['account_name']),
            'username' => isset($data['username']) &&
                trim((string) $data['username']) !== ''
                    ? trim((string) $data['username'])
                    : null,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('social_media_accounts', 'is_active')) {
            $update['is_active'] = $request->boolean('is_active');
        }

        if (Schema::hasColumn(
            'social_media_accounts',
            'auto_publish_enabled'
        )) {
            $update['auto_publish_enabled'] =
                $request->boolean('auto_publish_enabled');
        }

        $query->update($update);

        return back()->with('success', 'Social media account updated.');
    }

    public function updateWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_channel_name' => ['nullable', 'string', 'max:180'],
            'whatsapp_channel_url' => ['nullable', 'url', 'max:500'],
        ]);

        $user = $request->user();
        $update = [];

        foreach ($data as $column => $value) {
            if (Schema::hasColumn($user->getTable(), $column)) {
                $update[$column] = $value;
            }
        }

        if ($update !== []) {
            $user->forceFill($update)->save();
        }

        return back()->with('success', 'WhatsApp settings updated.');
    }

    public function destroy(Request $request, int $account): RedirectResponse
    {
        if (Schema::hasTable('social_media_accounts') &&
            Schema::hasColumn('social_media_accounts', 'user_id')) {
            DB::table('social_media_accounts')
                ->where('id', $account)
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        return back()->with('success', 'Social media account removed.');
    }

    private function ensureAccountsTable(): void
    {
        abort_unless(
            Schema::hasTable('social_media_accounts') &&
            Schema::hasColumn('social_media_accounts', 'user_id') &&
            Schema::hasColumn('social_media_accounts', 'platform') &&
            Schema::hasColumn('social_media_accounts', 'account_name'),
            503,
            'Social media accounts are not ready yet. Run the latest migrations.'
        );
    }
}
