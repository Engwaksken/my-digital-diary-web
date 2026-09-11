<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('owner_user_id')->index();
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
                $table->timestamps();
                $table->unique('owner_user_id');
            });
        } else {
            Schema::table('organizations', function (Blueprint $table): void {
                if (! Schema::hasColumn('organizations', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('organizations', 'owner_user_id')) {
                    $table->unsignedBigInteger('owner_user_id')->nullable()->index();
                }
                if (! Schema::hasColumn('organizations', 'subscription_plan_id')) {
                    $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('organization_members')) {
            Schema::create('organization_members', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('invited_email')->nullable()->index();
                $table->string('role', 40)->default('member');
                $table->string('status', 40)->default('invited');
                $table->string('invite_token', 120)->nullable()->unique();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('organization_members', function (Blueprint $table): void {
                $columns = [
                    'user_id' => fn () => $table->unsignedBigInteger('user_id')->nullable()->index(),
                    'invited_email' => fn () => $table->string('invited_email')->nullable()->index(),
                    'role' => fn () => $table->string('role', 40)->default('member'),
                    'status' => fn () => $table->string('status', 40)->default('invited'),
                    'invite_token' => fn () => $table->string('invite_token', 120)->nullable(),
                    'invited_at' => fn () => $table->timestamp('invited_at')->nullable(),
                    'activated_at' => fn () => $table->timestamp('activated_at')->nullable(),
                    'deactivated_at' => fn () => $table->timestamp('deactivated_at')->nullable(),
                ];

                foreach ($columns as $column => $definition) {
                    if (! Schema::hasColumn('organization_members', $column)) {
                        $definition();
                    }
                }
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->index();
            }

            if (! Schema::hasColumn('users', 'organization_role')) {
                $table->string('organization_role', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive repair migration for production.
    }
};
