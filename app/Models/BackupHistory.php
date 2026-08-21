<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupHistory extends Model
{
    protected $fillable = ['created_by','trigger','backup_type','provider','status','path','size_bytes','error_message','started_at','completed_at'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
