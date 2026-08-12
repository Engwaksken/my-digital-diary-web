<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('enterprise_inquiries', 'phone')) {
            Schema::table('enterprise_inquiries', function (Blueprint $table) {
                $table->string('phone', 50)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enterprise_inquiries', 'phone')) {
            Schema::table('enterprise_inquiries', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
