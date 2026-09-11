<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'auto_renew_subscription')) {
                $table->boolean('auto_renew_subscription')
                    ->default(false)
                    ->after('subscription_expires_at');
            }

            if (! Schema::hasColumn('users', 'auto_renew_payment_gateway_id')) {
                $table->unsignedBigInteger('auto_renew_payment_gateway_id')
                    ->nullable()
                    ->after('auto_renew_subscription');

                $table->index(
                    'auto_renew_payment_gateway_id',
                    'users_auto_renew_gateway_idx'
                );
            }

            if (! Schema::hasColumn('users', 'auto_renew_network')) {
                $table->string('auto_renew_network', 20)
                    ->nullable()
                    ->after('auto_renew_payment_gateway_id');
            }

            if (! Schema::hasColumn('users', 'auto_renew_phone')) {
                $table->string('auto_renew_phone', 30)
                    ->nullable()
                    ->after('auto_renew_network');
            }

            if (! Schema::hasColumn('users', 'last_auto_renew_attempt_at')) {
                $table->timestamp('last_auto_renew_attempt_at')
                    ->nullable()
                    ->after('auto_renew_phone');
            }

            if (! Schema::hasColumn('users', 'auto_renew_disabled_at')) {
                $table->timestamp('auto_renew_disabled_at')
                    ->nullable()
                    ->after('last_auto_renew_attempt_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'auto_renew_disabled_at',
                'last_auto_renew_attempt_at',
                'auto_renew_phone',
                'auto_renew_network',
                'auto_renew_payment_gateway_id',
                'auto_renew_subscription',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
