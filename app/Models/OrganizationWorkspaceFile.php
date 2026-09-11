<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationWorkspaceFile extends Model {
 protected $fillable=['organization_id','uploaded_by_user_id','disk','path','original_name','mime_type','size_bytes','permission','is_team_wide'];
 protected $casts=['is_team_wide'=>'boolean'];
 public function uploader(){ return $this->belongsTo(User::class,'uploaded_by_user_id'); }
 public function getHumanSizeAttribute(): string { $b=max(0,(int)$this->size_bytes); return $b<1024?$b.' B':($b<1048576?number_format($b/1024,1).' KB':number_format($b/1048576,1).' MB');}
}