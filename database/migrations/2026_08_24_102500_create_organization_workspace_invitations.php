<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_workspace_invitations')) {
            Schema::create('organization_workspace_invitations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('invited_by_user_id')->index();
                $table->string('email', 255)->index();
                $table->string('role', 50)->default('member');
                $table->string('token_hash', 64)->unique();
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(
                    ['organization_id', 'status'],
                    'org_ws_invitation_status'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_workspace_invitations');
    }
};
