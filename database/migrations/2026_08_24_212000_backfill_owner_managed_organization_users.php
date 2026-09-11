<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')
                    ->nullable()
                    ->index();
            }

            if (! Schema::hasColumn('users', 'organization_role')) {
                $table->string(
                    'organization_role',
                    40
                )->nullable();
            }
        });

        if (
            ! Schema::hasTable('organizations')
            || ! Schema::hasTable('organization_members')
        ) {
            return;
        }

        /*
         * Owner = Super Admin of their own workspace.
         */
        DB::table('organizations')
            ->whereNotNull('owner_user_id')
            ->orderBy('id')
            ->each(function ($organization): void {
                DB::table('users')
                    ->where('id', $organization->owner_user_id)
                    ->update([
                        'organization_id' => $organization->id,
                        'organization_role' => 'owner',
                    ]);
            });

        /*
         * Existing invited/created members are children of the organisation
         * owner. Repair the users convenience columns from the authoritative
         * membership row.
         */
        DB::table('organization_members')
            ->whereNotNull('user_id')
            ->whereIn('status', [
                'active',
                'invited',
                'inactive',
            ])
            ->orderBy('id')
            ->each(function ($membership): void {
                DB::table('users')
                    ->where('id', $membership->user_id)
                    ->update([
                        'organization_id' =>
                            $membership->organization_id,
                        'organization_role' =>
                            $membership->role ?: 'member',
                    ]);
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive. These columns may predate this repair.
    }
};
