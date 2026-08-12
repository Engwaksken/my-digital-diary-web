<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * organization_id is the user's CURRENT org, if any — cleared the moment
 * they're offboarded (see OrganizationController::removeMember()), which
 * is what actually frees the seat and revokes company access.
 *
 * personal_email/personal_email_verified_at exist specifically for
 * offboarding: someone who joined using a work email needs a personal
 * one to keep using their account afterward. Kept separate from the
 * main `email` column rather than overwriting it immediately, so the
 * verification step (a code sent to the new address) has to succeed
 * BEFORE it becomes their new login email — never swapped blindly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('organization_role')->nullable()->after('organization_id')->comment('admin, staff — only meaningful when organization_id is set');
            $table->string('personal_email')->nullable()->after('email');
            $table->timestamp('personal_email_verified_at')->nullable()->after('personal_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['organization_role', 'personal_email', 'personal_email_verified_at']);
        });
    }
};
