<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company/team account — the owner is whoever actually subscribed
 * (and pays); org admins (see organization_members) can be the owner or
 * additional staff promoted to manage seats without necessarily holding
 * the billing relationship themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The preceding repair migration creates this table for legacy
        // installations, including fresh SQLite test databases.
        if (Schema::hasTable('organizations')) {
            return;
        }

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
