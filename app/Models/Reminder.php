<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'module', 'message', 'frequency', 'interval_minutes',
        'next_run_at', 'channel', 'is_active', 'alarm_enabled',
        'source_type', 'source_id', 'source_signature', 'is_archived',];

    protected $casts = [
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
        'alarm_enabled' => 'boolean',
        'interval_minutes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ReminderItem::class);
    }

    /**
     * Push this reminder's next_run_at forward based on its frequency.
     * Sets is_active=false for a one-off reminder once it has fired.
     *
     * NOTE: for 'every_n_minutes'/'hourly' reminders to actually fire on
     * time, routes/console.php must run `reminders:send` frequently
     * (everyMinute(), not hourly()) — see the comment there. This method
     * only decides what the NEXT run time should be; how often the
     * scheduler checks for due reminders is a separate setting.
     */
    public function scheduleNext(): void
    {
        switch ($this->frequency) {
            case 'every_n_minutes':
                $this->next_run_at = $this->next_run_at->copy()->addMinutes(max(1, $this->interval_minutes ?: 15));
                break;
            case 'hourly':
                $this->next_run_at = $this->next_run_at->copy()->addHour();
                break;
            case 'daily':
                $this->next_run_at = $this->next_run_at->copy()->addDay();
                break;
            case 'weekly':
                $this->next_run_at = $this->next_run_at->copy()->addWeek();
                break;
            case 'monthly':
                $this->next_run_at = $this->next_run_at->copy()->addMonthNoOverflow();
                break;
            case 'annually':
                $this->next_run_at = $this->next_run_at->copy()->addYearNoOverflow();
                break;
            case 'once':
            default:
                $this->is_active = false;
                break;
        }

        $this->save();
    }
}
