<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->string('type', 30)->default('channel')->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_general')->default(false);
            $table->boolean('is_announcement_only')->default(false);
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });

        Schema::create('team_chat_conversation_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 30)->default('member');
            $table->timestamp('last_read_at')->nullable()->index();
            $table->timestamp('muted_until')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'tccm_conversation_user_unique');
        });

        Schema::create('team_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('reply_to_id')->nullable()->index();
            $table->text('body')->nullable();
            $table->boolean('is_announcement')->default(false)->index();
            $table->boolean('notify_all')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('team_chat_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('uploaded_by')->index();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('team_chat_mentions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'tcm_message_user_unique');
        });

        Schema::create('team_chat_reactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('reaction', 32);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'reaction'], 'tcr_message_user_reaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_reactions');
        Schema::dropIfExists('team_chat_mentions');
        Schema::dropIfExists('team_chat_attachments');
        Schema::dropIfExists('team_chat_messages');
        Schema::dropIfExists('team_chat_conversation_members');
        Schema::dropIfExists('team_chat_conversations');
    }
};
