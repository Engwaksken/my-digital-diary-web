<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds three things to subscription_plans: a category (individual /
 * family_team / organization) so plans can be grouped and displayed
 * separately; seat-based fields for organization plans (a fixed number
 * of included seats per tier, plus an optional per-seat overage price
 * for going beyond that tier); and admin-configurable display fields
 * (color, badge, recommended/best-value flags) so the pricing page can
 * be visually differentiated without hardcoding any of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('category')->default('individual')->after('key')
                ->comment('individual, family_team, organization');
            $table->unsignedInteger('included_seats')->default(1)->after('duration_months');
            $table->decimal('additional_user_price', 10, 2)->nullable()->after('included_seats')
                ->comment('per-seat price for exceeding included_seats — null means no overage allowed, must upgrade tier instead');
            $table->string('color')->default('slate')->after('sort_order')
                ->comment('a Tailwind color name, e.g. emerald, indigo — used for the plan card border/accent');
            $table->string('badge')->nullable()->after('color')
                ->comment('short label shown on the card, e.g. "Most Popular" — independent of is_recommended/is_best_value below');
            $table->boolean('is_recommended')->default(false)->after('badge');
            $table->boolean('is_best_value')->default(false)->after('is_recommended');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'included_seats', 'additional_user_price',
                'color', 'badge', 'is_recommended', 'is_best_value',
            ]);
        });
    }
};
