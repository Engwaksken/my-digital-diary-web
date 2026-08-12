<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpiritualPractice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'practice_type', 'title', 'practiced_at',
        'duration_minutes', 'reflection', 'next_planned_date', 'is_archived',];

    protected $casts = [
        'practiced_at' => 'date',
        'next_planned_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
