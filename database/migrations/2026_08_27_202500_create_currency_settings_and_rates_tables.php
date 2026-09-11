<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('currency_settings')) {
            Schema::create('currency_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('base_currency', 3)->default('UGX');
                $table->string('display_currency', 3)->default('UGX');
                $table->unsignedInteger('refresh_minutes')->default(60);
                $table->boolean('allow_user_selection')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('currency_rates')) {
            Schema::create('currency_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('base_currency', 3);
                $table->string('quote_currency', 3);
                $table->decimal('rate', 24, 10);
                $table->timestamp('fetched_at');
                $table->timestamps();
                $table->unique(['base_currency', 'quote_currency']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currency_settings');
    }
};
