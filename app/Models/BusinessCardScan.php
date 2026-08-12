<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCardScan extends Model
{
    protected $fillable = ['business_card_id', 'ip_address'];

    public function businessCard()
    {
        return $this->belongsTo(BusinessCard::class);
    }
}
