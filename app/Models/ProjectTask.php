<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'project_id', 'personal_goal_id', 'title', 'status', 'due_date', 'is_archived',];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function personalGoal() { return $this->belongsTo(PersonalGoal::class, 'personal_goal_id'); }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
