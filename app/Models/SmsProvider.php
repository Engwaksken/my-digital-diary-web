<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsProvider extends Model
{
    protected $fillable = [
        'name','endpoint','http_method','auth_type','api_key','sender_id',
        'recipient_field','message_field','sender_field','extra_headers',
        'extra_payload','is_enabled','is_default',
    ];

    protected $casts = [
        'extra_headers' => 'array',
        'extra_payload' => 'array',
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['api_key'];
}
