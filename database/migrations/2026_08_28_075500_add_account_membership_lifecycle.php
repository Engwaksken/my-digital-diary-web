<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('independent_activation_requests')) {
            Schema::create('independent_activation_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'account_mode')) {
                    $table->string('account_mode', 30)->default('independent')->index();
                }
                if (! Schema::hasColumn('users', 'workspace_access_status')) {
                    $table->string('workspace_access_status', 30)->default('independent')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'workspace_access_status')) {
                    $table->dropColumn('workspace_access_status');
                }
                if (Schema::hasColumn('users', 'account_mode')) {
                    $table->dropColumn('account_mode');
                }
            });
        }
        Schema::dropIfExists('independent_activation_requests');
    }
};
