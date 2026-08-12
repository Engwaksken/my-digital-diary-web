<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two things: (1) tones down the existing duration-based discounts to
 * more sustainable levels — 20% off for a full year is generous enough
 * to reward commitment without eroding margin the way a much larger
 * discount would; (2) seeds a Family/Small Team plan and six
 * Organization tiers (5/10/15/20/25/50 seats) with per-seat pricing that
 * decreases as the tier grows, so a 50-seat org pays noticeably less
 * per person than a 5-seat one. Every number here is a starting point —
 * fully editable afterward at Admin -> Subscription Plans, same as the
 * five original tiers always were.
 *
 * Organization/family prices are stored as a flat monthly total (not a
 * discount off the individual base price) — see
 * SubscriptionPlan::computedPrice(), where a flat_price always wins
 * regardless of duration. They're computed here as a multiple of
 * whatever the site's CURRENT base monthly price is, so they scale
 * sensibly with your actual currency/pricing rather than an arbitrary
 * hardcoded number.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. Sustainable discounts on the existing individual plans ----
        DB::table('subscription_plans')->where('key', 'quarterly')->update(['discount_percent' => 5, 'category' => 'individual']);
        DB::table('subscription_plans')->where('key', 'semiannual')->update(['discount_percent' => 10, 'category' => 'individual']);
        DB::table('subscription_plans')->where('key', 'annual')->update(['discount_percent' => 15, 'category' => 'individual', 'is_recommended' => true, 'color' => 'emerald']);
        DB::table('subscription_plans')->where('key', 'monthly')->update(['category' => 'individual']);
        DB::table('subscription_plans')->where('key', 'lifetime')->update(['category' => 'individual', 'color' => 'amber']);

        $monthlyPrice = (float) (DB::table('site_settings')->value('monthly_price') ?? 9);

        // ---- 2. Family / Small Team ----
        DB::table('subscription_plans')->insert([
            'key' => 'family_team',
            'name' => 'Family & Small Team',
            'category' => 'family_team',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => round($monthlyPrice * 5 * 0.8, 2), // 5 seats at a 20% bundled discount vs 5 separate individual subs
            'included_seats' => 5,
            'additional_user_price' => round($monthlyPrice * 0.85, 2),
            'is_enabled' => true,
            'sort_order' => 10,
            'color' => 'indigo',
            'badge' => null,
            'is_recommended' => false,
            'is_best_value' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---- 3. Organization tiers — per-seat rate decreases as the tier grows ----
        $tiers = [
            ['seats' => 5, 'rate' => 0.90, 'sort' => 20],
            ['seats' => 10, 'rate' => 0.80, 'sort' => 21],
            ['seats' => 15, 'rate' => 0.72, 'sort' => 22],
            ['seats' => 20, 'rate' => 0.65, 'sort' => 23, 'best_value' => true],
            ['seats' => 25, 'rate' => 0.60, 'sort' => 24],
            ['seats' => 50, 'rate' => 0.50, 'sort' => 25, 'badge' => 'Best Per-Member Rate'],
        ];

        foreach ($tiers as $tier) {
            $totalPrice = round($monthlyPrice * $tier['seats'] * $tier['rate'], 2);

            DB::table('subscription_plans')->insert([
                'key' => 'organization_' . $tier['seats'],
                'name' => 'Organization — ' . $tier['seats'] . ' Members',
                'category' => 'organization',
                'duration_months' => 1,
                'discount_percent' => 0,
                'flat_price' => $totalPrice,
                'included_seats' => $tier['seats'],
                'additional_user_price' => round($monthlyPrice * $tier['rate'], 2),
                'is_enabled' => true,
                'sort_order' => $tier['sort'],
                'color' => 'blue',
                'badge' => $tier['badge'] ?? null,
                'is_recommended' => false,
                'is_best_value' => $tier['best_value'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('category', '!=', 'individual')->delete();
        DB::table('subscription_plans')->where('key', 'quarterly')->update(['discount_percent' => 10]);
        DB::table('subscription_plans')->where('key', 'semiannual')->update(['discount_percent' => 15]);
        DB::table('subscription_plans')->where('key', 'annual')->update(['discount_percent' => 20, 'is_recommended' => false]);
    }
};
