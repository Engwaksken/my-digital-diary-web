<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'relationship_type', 'company', 'met_through',
        'last_contact_date', 'next_follow_up_date', 'goal', 'notes', 'is_archived',];

    protected $casts = [
        'last_contact_date' => 'date',
        'next_follow_up_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
