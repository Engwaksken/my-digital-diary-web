<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DietLog extends Model
{
    use HasFactory;

    protected $table = 'diet_logs';

    protected $fillable = ['user_id', 'meal_type', 'food_items', 'calories', 'logged_at', 'notes', 'is_archived',];

    protected $casts = [
        'logged_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
