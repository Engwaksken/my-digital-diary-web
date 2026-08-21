<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('support_conversations', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('assigned_at');
            }
            if (! Schema::hasColumn('support_conversations', 'ended_by')) {
                $table->string('ended_by', 20)->nullable()->after('ended_at');
            }
            if (! Schema::hasColumn('support_conversations', 'rated_support_user_id')) {
                $table->foreignId('rated_support_user_id')->nullable()->after('ended_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_conversations', 'support_rating')) {
                $table->unsignedTinyInteger('support_rating')->nullable()->after('rated_support_user_id');
            }
            if (! Schema::hasColumn('support_conversations', 'support_rating_comment')) {
                $table->text('support_rating_comment')->nullable()->after('support_rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('support_conversations', 'rated_support_user_id')) {
                $table->dropConstrainedForeignId('rated_support_user_id');
            }
            foreach (['support_rating_comment', 'support_rating', 'ended_by', 'ended_at'] as $column) {
                if (Schema::hasColumn('support_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
