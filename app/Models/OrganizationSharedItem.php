<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationSharedItem extends Model {
 protected $fillable=['organization_id','shared_by_user_id','shared_with_user_id','shareable_type','shareable_id','item_type','title','permission','is_team_wide','revoked_at'];
 protected $casts=['is_team_wide'=>'boolean','revoked_at'=>'datetime'];
 public function shareable(){ return $this->morphTo(); }
 public function sharedBy(){ return $this->belongsTo(User::class,'shared_by_user_id'); }
 public function sharedWith(){ return $this->belongsTo(User::class,'shared_with_user_id'); }
}