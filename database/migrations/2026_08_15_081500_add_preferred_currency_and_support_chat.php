<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'preferred_currency_code')) {
                $table->string('preferred_currency_code', 8)->nullable();
            }
        });

        if (!Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('ai');
                $table->string('subject')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'last_message_at']);
            });
        }

        if (!Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('sender_type', 20); // user, ai, support
                $table->longText('message');
                $table->timestamps();
                $table->index(['support_conversation_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferred_currency_code')) {
                $table->dropColumn('preferred_currency_code');
            }
        });
    }
};
