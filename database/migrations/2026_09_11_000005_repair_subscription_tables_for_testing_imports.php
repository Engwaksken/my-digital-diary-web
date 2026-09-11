<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->siteSettings();
        $this->subscriptionPlans();
        $this->paymentGateways();
        $this->payments();
        $this->invoices();
        $this->paymentTransactionLogs();
        $this->billingEventLogs();
    }

    private function siteSettings(): void
    {
        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('site_name')->default('My Digital Diary');
                $table->decimal('monthly_price', 8, 2)->default(9.00);
                $table->unsignedInteger('trial_days')->default(30);
                $table->string('default_currency_code', 3)->default('UGX');
                $table->string('default_currency_symbol', 10)->default('UGX');
                $table->unsignedTinyInteger('default_currency_decimals')->default(0);
                $table->json('supported_currencies')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('site_settings', function (Blueprint $table) {
                foreach ([
                    'site_name' => fn () => $table->string('site_name')->default('My Digital Diary'),
                    'monthly_price' => fn () => $table->decimal('monthly_price', 8, 2)->default(9.00),
                    'trial_days' => fn () => $table->unsignedInteger('trial_days')->default(30),
                    'default_currency_code' => fn () => $table->string('default_currency_code', 3)->default('UGX'),
                    'default_currency_symbol' => fn () => $table->string('default_currency_symbol', 10)->default('UGX'),
                    'default_currency_decimals' => fn () => $table->unsignedTinyInteger('default_currency_decimals')->default(0),
                    'supported_currencies' => fn () => $table->json('supported_currencies')->nullable(),
                    'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                    'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
                ] as $column => $add) {
                    if (! Schema::hasColumn('site_settings', $column)) {
                        $add();
                    }
                }
            });
        }

        DB::table('site_settings')->updateOrInsert(
            ['id' => 1],
            ['site_name' => 'My Digital Diary', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function subscriptionPlans(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('key')->nullable()->unique();
                $table->string('name');
                $table->string('category', 40)->default('individual');
                $table->unsignedInteger('duration_months')->nullable();
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('flat_price', 12, 2)->nullable();
                $table->unsignedInteger('included_seats')->default(1);
                $table->decimal('additional_user_price', 12, 2)->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('features')->nullable();
                $table->string('color', 20)->nullable();
                $table->string('badge')->nullable();
                $table->boolean('is_recommended')->default(false);
                $table->boolean('is_best_value')->default(false);
                $table->timestamps();
            });
        } else {
            Schema::table('subscription_plans', function (Blueprint $table) {
                foreach ([
                    'key' => fn () => $table->string('key')->nullable(),
                    'name' => fn () => $table->string('name')->nullable(),
                    'category' => fn () => $table->string('category', 40)->default('individual'),
                    'duration_months' => fn () => $table->unsignedInteger('duration_months')->nullable(),
                    'discount_percent' => fn () => $table->decimal('discount_percent', 5, 2)->default(0),
                    'flat_price' => fn () => $table->decimal('flat_price', 12, 2)->nullable(),
                    'included_seats' => fn () => $table->unsignedInteger('included_seats')->default(1),
                    'additional_user_price' => fn () => $table->decimal('additional_user_price', 12, 2)->default(0),
                    'is_enabled' => fn () => $table->boolean('is_enabled')->default(true),
                    'sort_order' => fn () => $table->unsignedInteger('sort_order')->default(0),
                    'features' => fn () => $table->json('features')->nullable(),
                    'color' => fn () => $table->string('color', 20)->nullable(),
                    'badge' => fn () => $table->string('badge')->nullable(),
                    'is_recommended' => fn () => $table->boolean('is_recommended')->default(false),
                    'is_best_value' => fn () => $table->boolean('is_best_value')->default(false),
                    'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                    'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
                ] as $column => $add) {
                    if (! Schema::hasColumn('subscription_plans', $column)) {
                        $add();
                    }
                }
            });
        }

        foreach ([
            ['key' => 'monthly', 'name' => 'Monthly', 'duration_months' => 1, 'sort_order' => 1],
            ['key' => 'quarterly', 'name' => '3 Months', 'duration_months' => 3, 'discount_percent' => 10, 'sort_order' => 2],
            ['key' => 'semiannual', 'name' => '6 Months', 'duration_months' => 6, 'discount_percent' => 15, 'sort_order' => 3],
            ['key' => 'annual', 'name' => 'Annual', 'duration_months' => 12, 'discount_percent' => 20, 'sort_order' => 4],
            ['key' => 'lifetime', 'name' => 'Lifetime', 'duration_months' => null, 'flat_price' => 299, 'sort_order' => 5],
        ] as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['key' => $plan['key']],
                array_merge([
                    'category' => 'individual',
                    'discount_percent' => 0,
                    'flat_price' => null,
                    'included_seats' => 1,
                    'additional_user_price' => 0,
                    'is_enabled' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ], $plan)
            );
        }
    }

    private function paymentGateways(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('type', 40)->default('bank');
                $table->string('name');
                $table->boolean('is_enabled')->default(true);
                $table->text('instructions')->nullable();
                $table->text('config')->nullable();
                $table->string('gateway_code')->nullable()->unique();
                $table->string('display_name')->nullable();
                $table->string('provider_type')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('sandbox_mode')->default(true);
                $table->text('token_url')->nullable();
                $table->text('collect_url')->nullable();
                $table->text('base_url')->nullable();
                $table->text('status_url')->nullable();
                $table->text('client_id')->nullable();
                $table->text('client_secret')->nullable();
                $table->text('wallet_guid')->nullable();
                $table->text('callback_url')->nullable();
                $table->text('return_url')->nullable();
                $table->boolean('supports_collection')->default(false);
                $table->boolean('supports_disbursement')->default(false);
                $table->boolean('supports_mtn')->default(false);
                $table->boolean('supports_airtel')->default(false);
                $table->timestamps();
            });
        } else {
            Schema::table('payment_gateways', function (Blueprint $table) {
                foreach ([
                    'type' => fn () => $table->string('type', 40)->default('bank'),
                    'name' => fn () => $table->string('name')->nullable(),
                    'is_enabled' => fn () => $table->boolean('is_enabled')->default(true),
                    'instructions' => fn () => $table->text('instructions')->nullable(),
                    'config' => fn () => $table->text('config')->nullable(),
                    'gateway_code' => fn () => $table->string('gateway_code')->nullable(),
                    'display_name' => fn () => $table->string('display_name')->nullable(),
                    'provider_type' => fn () => $table->string('provider_type')->nullable(),
                    'description' => fn () => $table->text('description')->nullable(),
                    'is_default' => fn () => $table->boolean('is_default')->default(false),
                    'sandbox_mode' => fn () => $table->boolean('sandbox_mode')->default(true),
                    'token_url' => fn () => $table->text('token_url')->nullable(),
                    'collect_url' => fn () => $table->text('collect_url')->nullable(),
                    'base_url' => fn () => $table->text('base_url')->nullable(),
                    'status_url' => fn () => $table->text('status_url')->nullable(),
                    'client_id' => fn () => $table->text('client_id')->nullable(),
                    'client_secret' => fn () => $table->text('client_secret')->nullable(),
                    'wallet_guid' => fn () => $table->text('wallet_guid')->nullable(),
                    'callback_url' => fn () => $table->text('callback_url')->nullable(),
                    'return_url' => fn () => $table->text('return_url')->nullable(),
                    'supports_collection' => fn () => $table->boolean('supports_collection')->default(false),
                    'supports_disbursement' => fn () => $table->boolean('supports_disbursement')->default(false),
                    'supports_mtn' => fn () => $table->boolean('supports_mtn')->default(false),
                    'supports_airtel' => fn () => $table->boolean('supports_airtel')->default(false),
                    'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                    'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
                ] as $column => $add) {
                    if (! Schema::hasColumn('payment_gateways', $column)) {
                        $add();
                    }
                }
            });
        }
    }

    private function payments(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('payment_gateway_id')->nullable()->index();
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
                $table->string('method', 40)->default('bank');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('UGX');
                $table->string('status', 40)->default('pending');
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->string('receipt_number')->nullable()->unique();
                $table->timestamps();
            });
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            foreach ([
                'user_id' => fn () => $table->unsignedBigInteger('user_id')->nullable()->index(),
                'payment_gateway_id' => fn () => $table->unsignedBigInteger('payment_gateway_id')->nullable()->index(),
                'subscription_plan_id' => fn () => $table->unsignedBigInteger('subscription_plan_id')->nullable()->index(),
                'method' => fn () => $table->string('method', 40)->default('bank'),
                'amount' => fn () => $table->decimal('amount', 12, 2)->default(0),
                'currency' => fn () => $table->string('currency', 10)->default('UGX'),
                'status' => fn () => $table->string('status', 40)->default('pending'),
                'reference' => fn () => $table->string('reference')->nullable(),
                'notes' => fn () => $table->text('notes')->nullable(),
                'receipt_number' => fn () => $table->string('receipt_number')->nullable(),
                'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('payments', $column)) {
                    $add();
                }
            }
        });
    }

    private function invoices(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->nullable()->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('enterprise_inquiry_id')->nullable()->index();
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->string('description')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('UGX');
                $table->string('status', 40)->default('unpaid');
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'invoice_number' => fn () => $table->string('invoice_number')->nullable(),
                'user_id' => fn () => $table->unsignedBigInteger('user_id')->nullable()->index(),
                'enterprise_inquiry_id' => fn () => $table->unsignedBigInteger('enterprise_inquiry_id')->nullable()->index(),
                'subscription_plan_id' => fn () => $table->unsignedBigInteger('subscription_plan_id')->nullable()->index(),
                'payment_id' => fn () => $table->unsignedBigInteger('payment_id')->nullable()->index(),
                'description' => fn () => $table->string('description')->nullable(),
                'amount' => fn () => $table->decimal('amount', 12, 2)->default(0),
                'currency' => fn () => $table->string('currency', 10)->default('UGX'),
                'status' => fn () => $table->string('status', 40)->default('unpaid'),
                'billing_period_start' => fn () => $table->date('billing_period_start')->nullable(),
                'billing_period_end' => fn () => $table->date('billing_period_end')->nullable(),
                'due_date' => fn () => $table->date('due_date')->nullable(),
                'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('invoices', $column)) {
                    $add();
                }
            }
        });
    }

    private function paymentTransactionLogs(): void
    {
        if (! Schema::hasTable('payment_transaction_logs')) {
            Schema::create('payment_transaction_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_gateway_id')->nullable()->index();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('external_reference')->nullable();
                $table->string('status', 40)->default('initiated');
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('phone_number')->nullable();
                $table->string('network')->nullable();
                $table->text('request_payload')->nullable();
                $table->text('response_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('payment_transaction_logs', function (Blueprint $table) {
            foreach ([
                'payment_gateway_id' => fn () => $table->unsignedBigInteger('payment_gateway_id')->nullable()->index(),
                'payment_id' => fn () => $table->unsignedBigInteger('payment_id')->nullable()->index(),
                'user_id' => fn () => $table->unsignedBigInteger('user_id')->nullable()->index(),
                'external_reference' => fn () => $table->string('external_reference')->nullable(),
                'status' => fn () => $table->string('status', 40)->default('initiated'),
                'amount' => fn () => $table->decimal('amount', 12, 2)->nullable(),
                'currency' => fn () => $table->string('currency', 10)->nullable(),
                'phone_number' => fn () => $table->string('phone_number')->nullable(),
                'network' => fn () => $table->string('network')->nullable(),
                'request_payload' => fn () => $table->text('request_payload')->nullable(),
                'response_payload' => fn () => $table->text('response_payload')->nullable(),
                'error_message' => fn () => $table->text('error_message')->nullable(),
                'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('payment_transaction_logs', $column)) {
                    $add();
                }
            }
        });
    }

    private function billingEventLogs(): void
    {
        if (Schema::hasTable('billing_event_logs')) {
            return;
        }

        Schema::create('billing_event_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->string('event_type');
            $table->string('recipient_email')->nullable();
            $table->string('status', 40)->default('success');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Repair migration only; do not drop billing data on rollback.
    }
};
