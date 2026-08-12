<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'level', 'institution', 'status',
        'start_date', 'target_completion_date', 'cost', 'notes', 'is_archived',];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
