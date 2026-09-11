<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationWorkspaceActivity extends Model {
 public $timestamps=false; protected $table='organization_workspace_activity';
 protected $fillable=['organization_id','actor_user_id','event','subject_type','subject_id','metadata','created_at'];
 protected $casts=['metadata'=>'array','created_at'=>'datetime'];
 public function actor(){ return $this->belongsTo(User::class,'actor_user_id'); }
}