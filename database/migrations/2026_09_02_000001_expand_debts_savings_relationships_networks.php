<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('debts')) {
            Schema::table('debts', function (Blueprint $table): void {
                if (! Schema::hasColumn('debts', 'contact_email')) {
                    $table->string('contact_email')->nullable()->after('person_name');
                }
                if (! Schema::hasColumn('debts', 'contact_phone')) {
                    $table->string('contact_phone', 40)->nullable()->after('contact_email');
                }
                if (! Schema::hasColumn('debts', 'reminder_enabled')) {
                    $table->boolean('reminder_enabled')->default(false)->after('notes');
                }
                if (! Schema::hasColumn('debts', 'reminder_channel')) {
                    $table->string('reminder_channel', 20)->default('email')->after('reminder_enabled');
                }
                if (! Schema::hasColumn('debts', 'reminder_frequency')) {
                    $table->string('reminder_frequency', 30)->default('once')->after('reminder_channel');
                }
                if (! Schema::hasColumn('debts', 'next_reminder_at')) {
                    $table->dateTime('next_reminder_at')->nullable()->after('reminder_frequency');
                }
                if (! Schema::hasColumn('debts', 'last_reminder_at')) {
                    $table->dateTime('last_reminder_at')->nullable()->after('next_reminder_at');
                }
            });
        }

        if (! Schema::hasTable('debt_reminder_logs')) {
            Schema::create('debt_reminder_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
                $table->string('recipient_scope', 20)->default('counterparty');
                $table->string('recipient_name')->nullable();
                $table->string('recipient_address')->nullable();
                $table->string('channel', 10);
                $table->string('status', 20)->default('pending');
                $table->text('message')->nullable();
                $table->text('provider_response')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['debt_id', 'sent_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('sms_providers')) {
            Schema::create('sms_providers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('endpoint');
                $table->string('http_method', 10)->default('POST');
                $table->string('auth_type', 20)->default('bearer');
                $table->text('api_key')->nullable();
                $table->string('sender_id')->nullable();
                $table->string('recipient_field')->default('to');
                $table->string('message_field')->default('message');
                $table->string('sender_field')->nullable();
                $table->json('extra_headers')->nullable();
                $table->json('extra_payload')->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('savings_goals')) {
            Schema::table('savings_goals', function (Blueprint $table): void {
                if (! Schema::hasColumn('savings_goals', 'reminder_enabled')) {
                    $table->boolean('reminder_enabled')->default(false)->after('notes');
                }
                if (! Schema::hasColumn('savings_goals', 'reminder_channel')) {
                    $table->string('reminder_channel', 20)->default('email')->after('reminder_enabled');
                }
                if (! Schema::hasColumn('savings_goals', 'reminder_frequency')) {
                    $table->string('reminder_frequency', 30)->default('weekly')->after('reminder_channel');
                }
                if (! Schema::hasColumn('savings_goals', 'next_reminder_at')) {
                    $table->dateTime('next_reminder_at')->nullable()->after('reminder_frequency');
                }
                if (! Schema::hasColumn('savings_goals', 'last_reminder_at')) {
                    $table->dateTime('last_reminder_at')->nullable()->after('next_reminder_at');
                }
            });
        }

        if (Schema::hasTable('personal_relationships')) {
            Schema::table('personal_relationships', function (Blueprint $table): void {
                foreach ([
                    'email' => ['string', 255],
                    'phone' => ['string', 40],
                    'birthday' => ['date'],
                    'anniversary' => ['date'],
                    'interests' => ['text'],
                    'commitments' => ['text'],
                    'follow_up_items' => ['text'],
                    'interaction_notes' => ['text'],
                ] as $name => $definition) {
                    if (! Schema::hasColumn('personal_relationships', $name)) {
                        $type = $definition[0];
                        $type === 'string'
                            ? $table->string($name, $definition[1])->nullable()
                            : $table->{$type}($name)->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('network_contacts')) {
            Schema::table('network_contacts', function (Blueprint $table): void {
                foreach ([
                    'email' => ['string', 255],
                    'phone' => ['string', 40],
                    'network_groups' => ['text'],
                    'opportunities' => ['text'],
                    'action_points' => ['text'],
                ] as $name => $definition) {
                    if (! Schema::hasColumn('network_contacts', $name)) {
                        $type = $definition[0];
                        $type === 'string'
                            ? $table->string($name, $definition[1])->nullable()
                            : $table->{$type}($name)->nullable();
                    }
                }
                if (! Schema::hasColumn('network_contacts', 'personal_relationship_id')) {
                    $table->unsignedBigInteger('personal_relationship_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_reminder_logs');
        Schema::dropIfExists('sms_providers');

        if (Schema::hasTable('debts')) {
            Schema::table('debts', function (Blueprint $table): void {
                foreach ([
                    'contact_email','contact_phone','reminder_enabled','reminder_channel',
                    'reminder_frequency','next_reminder_at','last_reminder_at'
                ] as $column) {
                    if (Schema::hasColumn('debts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('savings_goals')) {
            Schema::table('savings_goals', function (Blueprint $table): void {
                foreach ([
                    'reminder_enabled','reminder_channel','reminder_frequency',
                    'next_reminder_at','last_reminder_at'
                ] as $column) {
                    if (Schema::hasColumn('savings_goals', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('personal_relationships')) {
            Schema::table('personal_relationships', function (Blueprint $table): void {
                foreach ([
                    'email','phone','birthday','anniversary','interests',
                    'commitments','follow_up_items','interaction_notes'
                ] as $column) {
                    if (Schema::hasColumn('personal_relationships', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('network_contacts')) {
            Schema::table('network_contacts', function (Blueprint $table): void {
                foreach ([
                    'email','phone','network_groups','opportunities','action_points',
                    'personal_relationship_id'
                ] as $column) {
                    if (Schema::hasColumn('network_contacts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
