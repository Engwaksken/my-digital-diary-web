<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversation extends Model
{
    protected $fillable = [
        'user_id', 'assigned_to_user_id', 'status', 'subject', 'last_message_at', 'assigned_at',
        'ended_at', 'ended_by', 'rated_support_user_id', 'support_rating', 'support_rating_comment',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
        'support_rating' => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function ratedSupport() { return $this->belongsTo(User::class, 'rated_support_user_id'); }
    public function messages() { return $this->hasMany(SupportMessage::class); }
}
