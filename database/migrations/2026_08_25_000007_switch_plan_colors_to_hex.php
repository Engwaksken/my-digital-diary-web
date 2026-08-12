<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switches subscription_plans.color from a Tailwind color NAME (e.g.
 * 'emerald', with shades derived automatically) to a literal hex value,
 * rendered via inline styles from here on — see
 * SubscriptionPlan::colorTint()/colorTextStyle(). Two of the admin's
 * suggested colors (Navy and Slate) are both in the same "slate" family
 * but at different shades, which the old "one name, auto-derive every
 * shade" approach couldn't tell apart; storing the exact hex sidesteps
 * that and matches the swatches exactly, pixel for pixel, with no
 * dependency on Tailwind generating the right dynamic utility class.
 */
return new class extends Migration
{
    public function up(): void
    {
        $colorsByKey = [
            'monthly' => '#334155',       // Navy
            'quarterly' => '#1D4ED8',     // Blue
            'semiannual' => '#7C3AED',    // Purple
            'annual' => '#0F766E',        // Teal
            'lifetime' => '#475569',      // Slate (previously amber — now aligned with the admin's own palette)
            'family_team' => '#EA580C',   // Orange
            'organization_5' => '#059669',  // Emerald
            'organization_10' => '#0891B2', // Cyan
            'organization_15' => '#4F46E5', // Indigo
            'organization_20' => '#D97706', // Amber
            'organization_25' => '#E11D48', // Rose
            'organization_50' => '#6D28D9', // Deep Purple
        ];

        foreach ($colorsByKey as $key => $hex) {
            DB::table('subscription_plans')->where('key', $key)->update(['color' => $hex]);
        }

        // Any OTHER plan an admin already added under the old naming
        // scheme (a plain Tailwind color word) gets a sensible hex
        // fallback rather than being left with a value that no longer
        // renders as anything.
        DB::table('subscription_plans')
            ->whereNotIn('key', array_keys($colorsByKey))
            ->where('color', 'NOT LIKE', '#%')
            ->update(['color' => '#475569']);
    }

    public function down(): void
    {
        // Not meaningfully reversible — the original named-color values
        // aren't recoverable once overwritten. Leaving as-is on rollback.
    }
};
