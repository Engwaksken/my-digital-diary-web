<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration, no schema change — `users.email_verified_at`
 * already existed (Breeze scaffolds it by default), it just was never
 * enforced. This backfills every EXISTING account as verified the moment
 * this feature ships, so nobody who already has an account gets locked
 * out of their own dashboard by the new `verified` middleware in
 * routes/web.php. Only NEW registrations from this point forward actually
 * have to click the confirmation link.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally a no-op — there's no reliable way to know which of
        // these rows were "really" verified vs. backfilled, and reversing
        // this would re-lock out every existing user.
    }
};
