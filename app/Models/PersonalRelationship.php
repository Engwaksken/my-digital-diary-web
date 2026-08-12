<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'category', 'relation_label', 'priority',
        'last_meaningful_interaction', 'next_planned_interaction', 'strengthening_goal', 'notes', 'is_archived',];

    protected $casts = [
        'last_meaningful_interaction' => 'date',
        'next_planned_interaction' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
