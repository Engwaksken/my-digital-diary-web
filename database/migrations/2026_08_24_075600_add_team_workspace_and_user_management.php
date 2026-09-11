<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasTable('organization_shared_items')) Schema::create('organization_shared_items', function(Blueprint $t){
   $t->id(); $t->unsignedBigInteger('organization_id')->index(); $t->unsignedBigInteger('shared_by_user_id')->index();
   $t->unsignedBigInteger('shared_with_user_id')->nullable()->index(); $t->string('shareable_type',191); $t->unsignedBigInteger('shareable_id');
   $t->string('item_type',80)->nullable()->index(); $t->string('title')->nullable(); $t->enum('permission',['view','edit','manage'])->default('view');
   $t->boolean('is_team_wide')->default(true); $t->timestamp('revoked_at')->nullable(); $t->timestamps();
   $t->index(['organization_id','shareable_type','shareable_id'],'org_shared_lookup');
  });
  if(!Schema::hasTable('organization_workspace_files')) Schema::create('organization_workspace_files', function(Blueprint $t){
   $t->id(); $t->unsignedBigInteger('organization_id')->index(); $t->unsignedBigInteger('uploaded_by_user_id')->index();
   $t->string('disk',40)->default('local'); $t->string('path'); $t->string('original_name'); $t->string('mime_type',150)->nullable();
   $t->unsignedBigInteger('size_bytes')->default(0); $t->enum('permission',['view','edit','manage'])->default('view'); $t->boolean('is_team_wide')->default(true); $t->timestamps();
  });
  if(!Schema::hasTable('organization_workspace_activity')) Schema::create('organization_workspace_activity', function(Blueprint $t){
   $t->id(); $t->unsignedBigInteger('organization_id')->index(); $t->unsignedBigInteger('actor_user_id')->nullable()->index();
   $t->string('event',120)->index(); $t->string('subject_type',191)->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->json('metadata')->nullable();
   $t->timestamp('created_at')->useCurrent(); $t->index(['organization_id','created_at'],'org_activity_recent');
  });
  if(Schema::hasTable('users')) Schema::table('users', function(Blueprint $t){
   if(!Schema::hasColumn('users','account_status')) $t->string('account_status',30)->default('active')->index();
   if(!Schema::hasColumn('users','suspended_at')) $t->timestamp('suspended_at')->nullable();
   if(!Schema::hasColumn('users','suspended_reason')) $t->text('suspended_reason')->nullable();
   if(!Schema::hasColumn('users','system_role')) $t->string('system_role',50)->default('user')->index();
  });
 }
 public function down(): void { Schema::dropIfExists('organization_workspace_activity'); Schema::dropIfExists('organization_workspace_files'); Schema::dropIfExists('organization_shared_items'); }
};