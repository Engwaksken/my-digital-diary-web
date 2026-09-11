<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'project_id', 'personal_goal_id', 'title', 'status', 'progress_percent', 'due_date', 'due_time',
        'reminder_enabled', 'reminder_offset_minutes', 'reminder_custom_at', 'reminder_channel', 'reminder_id',
        'is_archived',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_enabled' => 'boolean',
        'progress_percent' => 'integer',
        'reminder_offset_minutes' => 'integer',
        'reminder_custom_at' => 'datetime',
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
