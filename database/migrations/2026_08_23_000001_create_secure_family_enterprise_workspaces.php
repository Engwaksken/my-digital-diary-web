<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 160);
                $table->enum('type', ['family', 'small_team', 'enterprise']);
                $table->string('slug', 180)->unique();
                $table->string('status', 30)->default('active')->index();
                $table->unsignedInteger('member_limit')->nullable();
                $table->string('billing_owner_type', 30)->default('user');
                $table->unsignedBigInteger('billing_owner_id')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->index(['owner_user_id', 'status']);
                $table->index(['type', 'status']);
            });
        }

        if (! Schema::hasTable('workspace_members')) {
            Schema::create('workspace_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('role', ['owner', 'admin', 'manager', 'finance', 'member'])->default('member');
                $table->string('status', 30)->default('active')->index();
                $table->json('permissions')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('workspace_invitations')) {
            Schema::create('workspace_invitations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('email', 255);
                $table->enum('role', ['admin', 'manager', 'finance', 'member'])->default('member');
                $table->string('token_hash', 128)->unique();
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'email', 'status']);
            });
        }

        if (! Schema::hasTable('workspace_audit_logs')) {
            Schema::create('workspace_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 120)->index();
                $table->string('subject_type', 160)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('workspace_shared_items')) {
            Schema::create('workspace_shared_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('item_type', 80)->index();
                $table->unsignedBigInteger('item_id');
                $table->enum('visibility', ['workspace', 'selected_members'])->default('workspace');
                $table->json('shared_with_user_ids')->nullable();
                $table->timestamp('shared_at')->useCurrent();
                $table->timestamps();
                $table->unique(['workspace_id', 'item_type', 'item_id'], 'workspace_shared_item_unique');
            });
        }

        if (Schema::hasTable('subscription_plans')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('subscription_plans', 'max_workspace_members')) {
                    $table->unsignedInteger('max_workspace_members')->nullable();
                }
                if (! Schema::hasColumn('subscription_plans', 'workspace_type')) {
                    $table->string('workspace_type', 30)->nullable();
                }
                if (! Schema::hasColumn('subscription_plans', 'contact_sales')) {
                    $table->boolean('contact_sales')->default(false);
                }
            });

            foreach (DB::table('subscription_plans')->get() as $plan) {
                $name = strtolower(trim((string) ($plan->name ?? $plan->title ?? '')));
                $slug = strtolower(trim((string) ($plan->slug ?? '')));
                $haystack = $name.' '.$slug;

                if (str_contains($haystack, 'family') || str_contains($haystack, 'small team')) {
                    DB::table('subscription_plans')->where('id', $plan->id)->update([
                        'workspace_type' => 'family',
                        'max_workspace_members' => 5,
                        'contact_sales' => false,
                    ]);
                } elseif (str_contains($haystack, 'enterprise')) {
                    DB::table('subscription_plans')->where('id', $plan->id)->update([
                        'workspace_type' => 'enterprise',
                        'max_workspace_members' => null,
                        'contact_sales' => true,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_plans')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                foreach (['max_workspace_members', 'workspace_type', 'contact_sales'] as $column) {
                    if (Schema::hasColumn('subscription_plans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('workspace_shared_items');
        Schema::dropIfExists('workspace_audit_logs');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
