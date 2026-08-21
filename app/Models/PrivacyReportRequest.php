<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PrivacyReportRequest extends Model {
 protected $fillable=['user_id','reason','modules','date_from','date_to','status','file_path','error_message','completed_at'];
 protected $casts=['modules'=>'array','date_from'=>'date','date_to'=>'date','completed_at'=>'datetime'];
 public function user(){return $this->belongsTo(User::class);}
}
