<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve any custom administrator-defined site name. Only replace
        // the legacy product name when it is still present.
        if (Schema::hasTable('site_settings') && Schema::hasColumn('site_settings', 'site_name')) {
            DB::table('site_settings')
                ->whereRaw('LOWER(TRIM(site_name)) = ?', ['personal monitor'])
                ->update(['site_name' => 'My Digital Diary']);
        }

        // Laravel database notifications are stored as JSON/text. Replace the
        // old user-facing product name in historical notification payloads too.
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'data')) {
            DB::table('notifications')
                ->where('data', 'like', '%Personal Monitor%')
                ->update([
                    'data' => DB::raw("REPLACE(data, 'Personal Monitor', 'My Digital Diary')"),
                ]);
        }

        // Some deployments include a persistent activity_logs table. Clean
        // only text-like columns that actually exist; deployments without this
        // table/column set are left untouched.
        if (Schema::hasTable('activity_logs')) {
            foreach (['description', 'message', 'action', 'details', 'text'] as $column) {
                if (! Schema::hasColumn('activity_logs', $column)) {
                    continue;
                }

                DB::table('activity_logs')
                    ->where($column, 'like', '%Personal Monitor%')
                    ->update([
                        $column => DB::raw("REPLACE(`{$column}`, 'Personal Monitor', 'My Digital Diary')"),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Branding cleanup is intentionally not reversed. Restoring a legacy
        // product name could overwrite administrator branding and historical
        // notification text unexpectedly.
    }
};
