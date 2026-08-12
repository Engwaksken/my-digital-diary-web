<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('monthly, quarterly, semiannual, annual, lifetime');
            $table->string('name');
            // Null duration = lifetime (never expires). Otherwise the
            // number of months one purchase of this plan covers.
            $table->unsignedInteger('duration_months')->nullable();
            // Percent off the "duration_months x site monthly price"
            // baseline (see SubscriptionPlan::computedPrice()) — ignored
            // for the lifetime plan, which uses flat_price instead, since
            // "months x monthly price" isn't a meaningful comparison for
            // something that never expires.
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('flat_price', 8, 2)->nullable()->comment('Only used when duration_months is null (lifetime)');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the five standard tiers with sensible starting numbers —
        // all fully editable afterward at /admin/subscription-plans.
        DB::table('subscription_plans')->insert([
            ['key' => 'monthly', 'name' => 'Monthly', 'duration_months' => 1, 'discount_percent' => 0, 'flat_price' => null, 'is_enabled' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'quarterly', 'name' => '3 Months', 'duration_months' => 3, 'discount_percent' => 10, 'flat_price' => null, 'is_enabled' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'semiannual', 'name' => '6 Months', 'duration_months' => 6, 'discount_percent' => 15, 'flat_price' => null, 'is_enabled' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'annual', 'name' => 'Annual', 'duration_months' => 12, 'discount_percent' => 20, 'flat_price' => null, 'is_enabled' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'lifetime', 'name' => 'Lifetime', 'duration_months' => null, 'discount_percent' => 0, 'flat_price' => 299.00, 'is_enabled' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
