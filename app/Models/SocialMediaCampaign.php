<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaCampaign extends Model
{
    protected $fillable = [
        'user_id', 'name', 'objective', 'starts_on', 'ends_on', 'status',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class, 'campaign_id');
    }
}
