<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','name','relationship_type','company','email','phone','network_groups',
        'met_through','last_contact_date','next_follow_up_date','goal','opportunities',
        'action_points','notes','personal_relationship_id','is_archived',
    ];

    protected $casts = [
        'last_contact_date' => 'date',
        'next_follow_up_date' => 'date',
        'is_archived' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function relationship() { return $this->belongsTo(PersonalRelationship::class, 'personal_relationship_id'); }
}
