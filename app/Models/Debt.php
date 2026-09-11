<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','type','person_name','contact_email','contact_phone','amount','date',
        'due_date','status','notes','reminder_enabled','reminder_channel',
        'reminder_frequency','next_reminder_at','last_reminder_at','is_archived',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'reminder_enabled' => 'boolean',
        'next_reminder_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function reminderLogs() { return $this->hasMany(DebtReminderLog::class); }

    public function refreshOverdueStatus(): bool
    {
        $shouldBeOverdue = $this->status !== 'paid'
            && $this->due_date !== null
            && $this->due_date->isBefore(today());

        if ($shouldBeOverdue && $this->status !== 'overdue') {
            $this->status = 'overdue';
            return $this->save();
        }

        if (! $shouldBeOverdue && $this->status === 'overdue') {
            $this->status = 'outstanding';
            return $this->save();
        }

        return true;
    }
}

