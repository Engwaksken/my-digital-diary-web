<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ProjectTaskReminderService
{
    public const OFFSETS = [0,5,15,30,60,120,1440];

    public function sync(ProjectTask $task, User $user): ?Reminder
    {
        if (! Schema::hasTable('reminders')) return null;

        if (! $task->reminder_enabled || $task->status === 'done') {
            $this->cancel($task, $user);
            return null;
        }

        $at = $this->remindAt($task, $user);
        if (! $at) {
            $this->cancel($task, $user);
            return null;
        }

        $columns = Schema::getColumnListing('reminders');
        $lookup = ['user_id' => $user->id];
        if (in_array('source_type',$columns,true) && in_array('source_id',$columns,true)) {
            $lookup['source_type']='project_task';
            $lookup['source_id']=$task->id;
        } elseif ($task->reminder_id) {
            $lookup['id']=$task->reminder_id;
        }

        $values=[];
        $this->put($values,$columns,'title','Project Task: '.$task->title);
        $this->put($values,$columns,'message','Reminder for project task “'.$task->title.'”.');
        $this->put($values,$columns,'description','Reminder for project task “'.$task->title.'”.');
        $this->put($values,$columns,'frequency','once');
        $this->put($values,$columns,'next_run_at',$at);
        $this->put($values,$columns,'remind_at',$at);
        $this->put($values,$columns,'reminder_at',$at);
        $this->put($values,$columns,'channel',$task->reminder_channel ?: 'push');
        $this->put($values,$columns,'is_active',true);
        $this->put($values,$columns,'alarm_enabled',true);
        $this->put($values,$columns,'status','active');
        $this->put($values,$columns,'module','project-tasks');
        $this->put($values,$columns,'source_type','project_task');
        $this->put($values,$columns,'source_id',$task->id);

        $reminder=Reminder::query()->updateOrCreate($lookup,$values);
        if (Schema::hasColumn('project_tasks','reminder_id') && (int)$task->reminder_id !== (int)$reminder->id) {
            $task->forceFill(['reminder_id'=>$reminder->id])->saveQuietly();
        }
        return $reminder;
    }

    public function cancel(ProjectTask $task, User $user): void
    {
        if (! Schema::hasTable('reminders')) return;
        $columns=Schema::getColumnListing('reminders');
        $query=Reminder::query()->where('user_id',$user->id);
        if (in_array('source_type',$columns,true) && in_array('source_id',$columns,true)) {
            $query->where('source_type','project_task')->where('source_id',$task->id);
        } elseif ($task->reminder_id) {
            $query->whereKey($task->reminder_id);
        } else return;

        if (in_array('is_active',$columns,true)) $query->update(['is_active'=>false]);
        elseif (in_array('status',$columns,true)) $query->update(['status'=>'cancelled']);
        else $query->delete();
    }

    private function remindAt(ProjectTask $task, User $user): ?Carbon
    {
        $tz=$user->timezone ?: config('app.timezone','Africa/Kampala');
        if ($task->reminder_custom_at) return Carbon::parse($task->reminder_custom_at,$tz);
        if (! $task->due_date || ! $task->due_time) return null;
        $due=Carbon::parse($task->due_date->format('Y-m-d').' '.substr((string)$task->due_time,0,8),$tz);
        return $due->subMinutes(max(0,(int)($task->reminder_offset_minutes ?? 0)));
    }

    private function put(array &$values,array $columns,string $key,mixed $value): void
    {
        if (in_array($key,$columns,true)) $values[$key]=$value;
    }
}
