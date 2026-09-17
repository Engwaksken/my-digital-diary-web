<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','name','category','relation_label','priority',
        'last_meaningful_interaction','next_planned_interaction',
        'interaction_notes','is_archived',
    ];

    protected $casts = [
        'last_meaningful_interaction' => 'date',
        'next_planned_interaction' => 'date',
        'is_archived' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function daysSinceLastInteraction(): ?int
    {
        return $this->last_meaningful_interaction
            ? $this->last_meaningful_interaction->startOfDay()->diffInDays(today())
            : null;
    }
}
