<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtReminderLog extends Model
{
    protected $fillable = [
        'user_id','debt_id','recipient_scope','recipient_name','recipient_address',
        'channel','status','message','provider_response','sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    public function debt() { return $this->belongsTo(Debt::class); }
    public function user() { return $this->belongsTo(User::class); }
}
