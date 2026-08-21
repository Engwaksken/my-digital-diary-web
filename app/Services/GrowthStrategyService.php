<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GrowthStrategyService
{
    public function dashboard(User $user): array
    {
        $this->ensureDefaults($user);

        return [
            'activation' => $this->activation($user),
            'challenge' => $this->challenge($user),
            'referral' => $this->referralSummary($user),
            'trust' => [
                'title' => 'Private by design',
                'message' => 'Your private diary, reflections and financial records are never placed in shared progress cards.',
            ],
            'campaign' => [
                'headline' => 'Take back your attention',
                'message' => 'Spend a few intentional minutes planning your own life instead of only watching everyone else’s.',
            ],
        ];
    }

    public function ensureDefaults(User $user): void
    {
        DB::table('communication_preferences')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'timezone' => $user->timezone ?: 'Africa/Kampala',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('growth_challenges')->updateOrInsert(
            ['slug' => '30-days-with-my-digital-diary'],
            [
                'title' => '30 Days With My Digital Diary',
                'description' => 'Plan, act, record, reflect and review your progress for 30 days.',
                'duration_days' => 30,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function joinChallenge(User $user): array
    {
        $this->ensureDefaults($user);
        $challenge = DB::table('growth_challenges')->where('slug', '30-days-with-my-digital-diary')->firstOrFail();
        $today = Carbon::now($user->timezone ?: 'Africa/Kampala')->startOfDay();

        DB::table('growth_challenge_enrolments')->updateOrInsert(
            ['growth_challenge_id' => $challenge->id, 'user_id' => $user->id],
            [
                'started_on' => $today->toDateString(),
                'ends_on' => $today->copy()->addDays(29)->toDateString(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->event($user, 'challenge_joined', 'growth');
        return $this->challenge($user);
    }

    public function referralLink(User $user, string $channel = 'app'): array
    {
        $row = DB::table('user_referrals')
            ->where('referrer_user_id', $user->id)
            ->whereNull('referred_user_id')
            ->first();

        if (!$row) {
            $id = DB::table('user_referrals')->insertGetId([
                'referrer_user_id' => $user->id,
                'code' => strtoupper(Str::random(10)),
                'channel' => $channel,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('user_referrals')->find($id);
        }

        $this->event($user, 'referral_link_created', $channel);

        return [
            'code' => $row->code,
            'url' => route('growth.invite', $row->code),
            'share_text' => 'I’m using My Digital Diary to plan my day, manage my money and track my goals. Join me and build your own progress.',
        ];
    }

    public function markInviteClick(string $code): ?object
    {
        $row = DB::table('user_referrals')->where('code', $code)->first();
        if ($row && !$row->clicked_at) {
            DB::table('user_referrals')->where('id', $row->id)->update(['clicked_at' => now(), 'updated_at' => now()]);
        }
        return $row;
    }

    public function preferences(User $user): array
    {
        $this->ensureDefaults($user);
        return (array) DB::table('communication_preferences')->where('user_id', $user->id)->first();
    }

    public function updatePreferences(User $user, array $data): array
    {
        DB::table('communication_preferences')->updateOrInsert(
            ['user_id' => $user->id],
            array_merge($data, ['updated_at' => now(), 'created_at' => now()])
        );
        return $this->preferences($user);
    }

    public function event(?User $user, string $name, string $source = 'app', array $meta = []): void
    {
        DB::table('product_growth_events')->insert([
            'user_id' => $user?->id,
            'event_name' => $name,
            'source' => $source,
            'meta' => $meta ? json_encode($meta) : null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function activation(User $user): array
    {
        $steps = [
            ['key' => 'plan', 'label' => 'Plan your day', 'complete' => Schema::hasTable('daily_plan_items') && DB::table('daily_plan_items')->where('user_id', $user->id)->exists()],
            ['key' => 'goal', 'label' => 'Create a goal', 'complete' => Schema::hasTable('personal_goals') && DB::table('personal_goals')->where('user_id', $user->id)->exists()],
            ['key' => 'money', 'label' => 'Record money', 'complete' => (Schema::hasTable('expenses') && DB::table('expenses')->where('user_id', $user->id)->exists()) || (Schema::hasTable('incomes') && DB::table('incomes')->where('user_id', $user->id)->exists())],
        ];
        $done = collect($steps)->where('complete', true)->count();
        return ['steps' => $steps, 'completed' => $done, 'total' => 3, 'percent' => (int) round(($done / 3) * 100)];
    }

    private function challenge(User $user): array
    {
        $challenge = DB::table('growth_challenges')->where('slug', '30-days-with-my-digital-diary')->first();
        if (!$challenge) return ['joined' => false, 'progress_percent' => 0];
        $enrolment = DB::table('growth_challenge_enrolments')->where('growth_challenge_id', $challenge->id)->where('user_id', $user->id)->first();
        if (!$enrolment) return ['joined' => false, 'title' => $challenge->title, 'duration_days' => 30, 'progress_percent' => 0];

        $meaningfulDays = Schema::hasTable('engagement_events')
            ? DB::table('engagement_events')->where('user_id', $user->id)->whereBetween('event_date', [$enrolment->started_on, $enrolment->ends_on])->distinct('event_date')->count('event_date')
            : 0;

        return [
            'joined' => true,
            'title' => $challenge->title,
            'meaningful_days' => $meaningfulDays,
            'duration_days' => 30,
            'progress_percent' => min(100, (int) round(($meaningfulDays / 30) * 100)),
            'ends_on' => $enrolment->ends_on,
        ];
    }

    private function referralSummary(User $user): array
    {
        return [
            'invites' => DB::table('user_referrals')->where('referrer_user_id', $user->id)->count(),
            'conversions' => DB::table('user_referrals')->where('referrer_user_id', $user->id)->whereNotNull('converted_at')->count(),
        ];
    }
}
