<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyPlanItem;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DailyPlannerTaskReminderService
{
    public const OFFSETS = [0, 5, 15, 30, 60, 120, 1440];

    public function sync(DailyPlanItem $item, User $user, ?Carbon $occurrenceDate = null): ?Reminder
    {
        $item->loadMissing('plan');

        if (! Schema::hasTable('reminders')) {
            return null;
        }

        if (! $item->reminder_enabled || $item->is_completed) {
            $this->cancel($item, $user);
            return null;
        }

        $when = $this->remindAt($item, $occurrenceDate);
        if (! $when) {
            $this->cancel($item, $user);
            return null;
        }

        $columns = Schema::getColumnListing('reminders');
        $lookup = ['user_id' => $user->id];

        if (in_array('source_type', $columns, true) && in_array('source_id', $columns, true)) {
            $lookup['source_type'] = 'daily_plan_item';
            $lookup['source_id'] = $item->id;
        } elseif ($item->reminder_id) {
            $lookup['id'] = $item->reminder_id;
        }

        $values = [];
        $this->put($values, $columns, 'title', 'Task: '.$item->title);
        $this->put($values, $columns, 'name', 'Task: '.$item->title);
        $this->put($values, $columns, 'message', $item->description ?: 'Daily Planner task reminder');
        $this->put($values, $columns, 'description', $item->description ?: 'Daily Planner task reminder');
        $this->put($values, $columns, 'module', 'daily-planner');
        $this->put($values, $columns, 'related_module', 'daily-planner');
        $this->put($values, $columns, 'next_run_at', $when);
        $this->put($values, $columns, 'remind_at', $when);
        $this->put($values, $columns, 'reminder_at', $when);
        $this->put($values, $columns, 'starts_at', $when);
        $this->put($values, $columns, 'is_active', true);
        $this->put($values, $columns, 'status', 'active');
        $this->put($values, $columns, 'source_type', 'daily_plan_item');
        $this->put($values, $columns, 'source_id', $item->id);

        $channels = array_values(array_filter((array) ($item->reminder_channels ?: ['in_app', 'push'])));
        if (in_array('channels', $columns, true)) {
            $values['channels'] = $channels;
        }
        if (in_array('channel', $columns, true)) {
            $values['channel'] = count($channels) === 1 ? $channels[0] : 'multiple';
        }

        $reminder = Reminder::query()->updateOrCreate($lookup, $values);

        if (Schema::hasColumn('daily_plan_items', 'reminder_id') && (int) $item->reminder_id !== (int) $reminder->id) {
            $item->forceFill(['reminder_id' => $reminder->id])->saveQuietly();
        }

        return $reminder;
    }

    public function cancel(DailyPlanItem $item, User $user): void
    {
        if (! Schema::hasTable('reminders')) return;

        $columns = Schema::getColumnListing('reminders');
        $query = Reminder::query()->where('user_id', $user->id);

        if (in_array('source_type', $columns, true) && in_array('source_id', $columns, true)) {
            $query->where('source_type', 'daily_plan_item')->where('source_id', $item->id);
        } elseif ($item->reminder_id) {
            $query->whereKey($item->reminder_id);
        } else {
            return;
        }

        if (in_array('is_active', $columns, true)) {
            $query->update(['is_active' => false]);
        } elseif (in_array('status', $columns, true)) {
            $query->update(['status' => 'cancelled']);
        } else {
            $query->delete();
        }
    }

    public function remindAt(DailyPlanItem $item, ?Carbon $occurrenceDate = null): ?Carbon
    {
        $item->loadMissing('plan');
        $timezone = $item->plan?->user?->timezone ?: config('app.timezone', 'Africa/Kampala');

        if ($item->reminder_custom_at) {
            return Carbon::parse($item->reminder_custom_at, $timezone);
        }

        if (! $item->start_time) return null;

        $date = $occurrenceDate?->copy()
            ?? Carbon::parse($item->plan->plan_date, $timezone);

        $time = substr((string) $item->start_time, 0, 8);
        $taskAt = Carbon::parse($date->toDateString().' '.$time, $timezone);
        $offset = max(0, (int) ($item->reminder_offset_minutes ?? 0));

        return $taskAt->subMinutes($offset);
    }

    private function put(array &$values, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $values[$column] = $value;
        }
    }
}
