<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PersonalGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PersonalHealthGoalProgressService
{
    public function sync(User $user): void
    {
        if (!Schema::hasTable('personal_goals')) return;

        PersonalGoal::query()
            ->where('user_id',$user->id)
            ->where('module','health')
            ->where('is_archived',false)
            ->whereIn('status',['not_started','in_progress','paused'])
            ->get()
            ->each(fn(PersonalGoal $goal) => $this->syncGoal($user,$goal));
    }

    public function syncGoal(User $user, PersonalGoal $goal): PersonalGoal
    {
        if ((int)$goal->user_id !== (int)$user->id || $goal->module !== 'health') return $goal;

        $tz=$user->timezone ?: config('app.timezone','Africa/Kampala');
        $today=Carbon::now($tz)->startOfDay();
        $start=$goal->start_date ? Carbon::parse($goal->start_date,$tz)->startOfDay()
            : ($goal->created_at ? Carbon::parse($goal->created_at,$tz)->startOfDay() : $today->copy());
        if ($start->gt($today)) $start=$today->copy();

        $end=$today->copy();
        if ($goal->target_date) {
            $target=Carbon::parse($goal->target_date,$tz)->startOfDay();
            if ($target->lt($end)) $end=$target;
        }
        if ($end->lt($start)) $end=$start->copy();
        if ($start->diffInDays($end)>365) $start=$end->copy()->subDays(365);

        $targets=$this->targets($user);
        $days=collect();
        for($d=$start->copy();$d->lte($end);$d->addDay()) $days->push($d->toDateString());

        $steps=$this->steps($user,$start,$end);
        $diet=$this->dailyAggregate('diet_logs','logged_at','COUNT(*)',$user,$start,$end);
        $sleep=$this->dailyAggregate('sleep_logs','sleep_date','SUM(COALESCE(duration_minutes,0))',$user,$start,$end);
        $exercise=$this->dailyAggregate('exercise_logs','performed_at','SUM(COALESCE(duration_minutes,0))',$user,$start,$end);

        $pSteps=$this->avg($days,
            fn($date)=>(float)data_get($steps,$date.'.steps',0),
            fn($date)=>(float)max(1,data_get($steps,$date.'.daily_goal',$targets['steps'])));
        $pDiet=$this->avg($days,fn($date)=>(float)($diet[$date]??0),fn()=>max(1,$targets['meals']));
        $pSleep=$this->avg($days,fn($date)=>((float)($sleep[$date]??0))/60,fn()=>max(.1,$targets['sleep_hours']));
        $pExercise=$this->avg($days,fn($date)=>(float)($exercise[$date]??0),fn()=>max(1,$targets['exercise_minutes']));

        $daily=round(($pSteps+$pDiet+$pSleep+$pExercise)/4,1);
        $checkup=$this->checkup($user,$goal,$start,$end,$targets);
        $progress=$checkup['included'] ? round($daily*.9+$checkup['percent']*.1) : round($daily);
        $progress=max(0,min(100,(int)$progress));

        $status=(string)$goal->status;
        if ($status!=='paused') {
            if ($progress>0 && $status==='not_started') $status='in_progress';
            if ($progress>=100 && $goal->target_date && Carbon::parse($goal->target_date,$tz)->startOfDay()->lte($today)) {
                $status='completed';
            }
        }

        $goal->forceFill([
            'current_value'=>$progress,
            'progress_percent'=>$progress,
            'status'=>$status,
        ])->saveQuietly();

        return $goal->fresh();
    }

    private function targets(User $user): array
    {
        $defaults=['steps'=>5000,'meals'=>3,'sleep_hours'=>8.0,'exercise_minutes'=>30,'health_checkup_due_date'=>null];
        if (!Schema::hasTable('wellbeing_goals')) return $defaults;

        $q=DB::table('wellbeing_goals')->where('user_id',$user->id);
        if (Schema::hasColumn('wellbeing_goals','is_active')) $q->where('is_active',true);
        $row=$q->orderByDesc('id')->first();
        if (!$row) return $defaults;

        return [
            'steps'=>max(1,(int)($row->steps_target??5000)),
            'meals'=>max(1,(int)($row->meals_target??3)),
            'sleep_hours'=>max(.1,(float)($row->sleep_hours_target??8)),
            'exercise_minutes'=>max(1,(int)($row->exercise_minutes_target??30)),
            'health_checkup_due_date'=>$row->health_checkup_due_date??null,
        ];
    }

    private function steps(User $user, Carbon $start, Carbon $end): Collection
    {
        if (!Schema::hasTable('daily_steps')) return collect();
        return DB::table('daily_steps')
            ->where('user_id',$user->id)
            ->whereBetween('tracking_date',[$start->toDateString(),$end->toDateString()])
            ->get(['tracking_date','steps','daily_goal'])
            ->mapWithKeys(fn($r)=>[
                Carbon::parse($r->tracking_date)->toDateString()=>[
                    'steps'=>(int)($r->steps??0),
                    'daily_goal'=>max(1,(int)($r->daily_goal??5000)),
                ],
            ]);
    }

    private function dailyAggregate(string $table,string $dateColumn,string $aggregate,User $user,Carbon $start,Carbon $end): Collection
    {
        if (!Schema::hasTable($table)) return collect();
        $q=DB::table($table)->where('user_id',$user->id);
        if (Schema::hasColumn($table,'is_archived')) $q->where('is_archived',false);
        $from=str_contains($dateColumn,'_at') ? $start->copy()->startOfDay() : $start->toDateString();
        $to=str_contains($dateColumn,'_at') ? $end->copy()->endOfDay() : $end->toDateString();

        return $q->whereBetween($dateColumn,[$from,$to])
            ->selectRaw("DATE($dateColumn) as day, $aggregate as total")
            ->groupByRaw("DATE($dateColumn)")
            ->pluck('total','day');
    }

    private function checkup(User $user, PersonalGoal $goal, Carbon $start, Carbon $end, array $targets): array
    {
        if (!Schema::hasTable('health_checkups')) return ['included'=>false,'percent'=>0];

        $q=DB::table('health_checkups')
            ->where('user_id',$user->id)
            ->whereBetween('checkup_date',[$start->copy()->startOfDay(),$end->copy()->endOfDay()]);
        if (Schema::hasColumn('health_checkups','is_archived')) $q->where('is_archived',false);
        $done=$q->exists();

        $text=strtolower(trim((string)$goal->title.' '.(string)$goal->description));
        $mentions=str_contains($text,'checkup')||str_contains($text,'check-up')||
            str_contains($text,'doctor')||str_contains($text,'clinic')||str_contains($text,'medical');

        return [
            'included'=>$done || filled($targets['health_checkup_due_date']) || $mentions,
            'percent'=>$done ? 100 : 0,
        ];
    }

    private function avg(Collection $days, callable $current, callable $target): float
    {
        if ($days->isEmpty()) return 0;
        $sum=$days->sum(function($date) use($current,$target){
            $c=max(0,(float)$current($date)); $t=max(.0001,(float)$target($date));
            return min(100,($c/$t)*100);
        });
        return round($sum/$days->count(),1);
    }
}
