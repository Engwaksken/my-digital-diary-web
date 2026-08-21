<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRecycleBinItem extends Model
{
    protected $fillable = ['user_id','model_type','original_id','label','payload','deleted_at','expires_at'];
    protected $casts = ['payload'=>'array','deleted_at'=>'datetime','expires_at'=>'datetime'];

    public function user(){ return $this->belongsTo(User::class); }
}
