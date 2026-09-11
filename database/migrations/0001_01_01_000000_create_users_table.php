<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            if (Schema::hasColumn('users', 'theme_color_secondary')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('theme_color_secondary');
                });
            }
        } else {
            Schema::dropIfExists('users');
        }
    }
};
