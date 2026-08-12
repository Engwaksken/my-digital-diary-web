<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An explicit flag rather than inferring "was this person offboarded"
 * from other fields (which could false-positive on someone who simply
 * never joined an org). Set the moment OrganizationController removes
 * or replaces a seat; cleared once they verify a personal email or
 * subscribe to a new individual plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('offboarded_at')->nullable()->after('personal_email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('offboarded_at');
        });
    }
};
