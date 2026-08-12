<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderItem extends Model
{
    protected $fillable = ['reminder_id', 'item_id', 'item_label', 'item_datetime'];

    protected $casts = [
        'item_datetime' => 'datetime',
    ];

    public function reminder()
    {
        return $this->belongsTo(Reminder::class);
    }
}
