<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_plans') && ! Schema::hasColumn('subscription_plans', 'included_extra_recording_minutes')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->unsignedInteger('included_extra_recording_minutes')
                    ->nullable()
                    ->default(0)
                    ->after('included_seats');
            });
        }

        if (! Schema::hasTable('subscription_recording_extra_grants')) {
            Schema::create('subscription_recording_extra_grants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
                $table->string('activation_event_key')->unique();
                $table->string('activation_event_type')->nullable()->index();
                $table->unsignedInteger('included_minutes')->default(0);
                $table->timestamp('granted_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->index(['user_id', 'subscription_plan_id'], 'extra_grants_user_plan_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_recording_extra_grants');

        if (Schema::hasTable('subscription_plans') && Schema::hasColumn('subscription_plans', 'included_extra_recording_minutes')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('included_extra_recording_minutes');
            });
        }
    }
};
