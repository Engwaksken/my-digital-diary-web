<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSocialMediaController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            in_array(
                (string) $request->user()->role,
                ['admin', 'super_admin'],
                true
            ),
            403
        );
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'whatsapp_number',
                'whatsapp_channel_name',
                'whatsapp_channel_url',
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $user->accounts_count = DB::table('social_media_accounts')
                    ->where('user_id', $user->id)
                    ->count();

                $user->scheduled_posts_count =
                    DB::table('social_media_posts')
                        ->where('user_id', $user->id)
                        ->where('status', 'scheduled')
                        ->count();

                return $user;
            });

        return response()->json([
            'data' => [
                'summary' => [
                    'users' => User::count(),
                    'accounts' => DB::table('social_media_accounts')->count(),
                    'whatsapp_configured' => User::query()
                        ->whereNotNull('whatsapp_number')
                        ->where('whatsapp_number', '<>', '')
                        ->count(),
                    'scheduled_posts' => DB::table('social_media_posts')
                        ->where('status', 'scheduled')
                        ->count(),
                ],
                'users' => $users,
            ],
        ]);
    }

    public function updateWhatsApp(
        Request $request,
        User $user
    ) {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'whatsapp_number' => ['nullable','string','max:30'],
            'whatsapp_channel_name' => ['nullable','string','max:180'],
            'whatsapp_channel_url' => ['nullable','url','max:500'],
        ]);

        $user->forceFill($data)->save();

        return response()->json(['ok' => true]);
    }

    public function destroyAccount(
        Request $request,
        User $user,
        int $account
    ) {
        $this->ensureAdmin($request);

        DB::table('social_media_accounts')
            ->where('id', $account)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
