<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyFoodJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'journal_date',
        'daily_food_notes',
        'meal_type',
        'food_items',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'food_items' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
