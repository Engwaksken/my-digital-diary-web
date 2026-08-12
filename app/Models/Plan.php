<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'period', 'description', 'target_date', 'status',
        'plan_year', 'progress_percent', 'is_archived',
    ];

    protected $casts = [
        'target_date' => 'date',
        'plan_year' => 'integer',
        'progress_percent' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
