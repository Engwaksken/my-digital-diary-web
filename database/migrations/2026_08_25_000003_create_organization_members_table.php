<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per seat — an invite starts here before the person even has
 * an account (user_id null, invited_email set), then gets linked to a
 * real user once they accept. status tracks the seat's lifecycle:
 * invited -> active -> (deactivated, by the org admin, OR removed when
 * the person leaves — see OrganizationController::removeMember()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('invited_email')->nullable();
            $table->string('role')->default('staff')->comment('admin, staff');
            $table->string('status')->default('invited')->comment('invited, active, inactive');
            $table->string('invite_token')->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
