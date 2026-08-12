<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthCheckup extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'checkup_type', 'checkup_date', 'doctor_name', 'findings', 'next_due_date', 'is_archived',];

    protected $casts = [
        'checkup_date' => 'datetime',
        'next_due_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
