<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WellbeingGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WellbeingGoalController extends Controller
{
    public function index(Request $request)
    {
        return redirect('/wellbeing?open_goals=1');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'steps_target' => ['required', 'integer', 'min:1000', 'max:10000'],
            'meals_target' => ['required', 'integer', 'min:1', 'max:8'],
            'sleep_hours_target' => ['required', 'numeric', 'min:4', 'max:12'],
            'exercise_minutes_target' => ['required', 'integer', 'min:5', 'max:300'],
            'water_ml_target' => ['required', 'integer', 'min:250', 'max:10000'],
            'health_checkup_due_date' => ['nullable', 'date'],
            'reminders_enabled' => ['nullable', 'boolean'],
            'reminder_interval_hours' => ['required', 'integer', 'in:6,12,24'],
        ]);

        if (! Schema::hasTable('wellbeing_goals')) {
            return redirect('/wellbeing?open_goals=1')
                ->withInput()
                ->with(
                    'error',
                    'Wellbeing goals are not ready yet. Please run the latest database migration, then try again.'
                );
        }

        try {
            DB::transaction(function () use ($request, $data): void {
                $goal = WellbeingGoal::query()->firstOrNew([
                    'user_id' => $request->user()->id,
                ]);

                $oldCheckupDate = $goal->health_checkup_due_date
                    ? $goal->health_checkup_due_date->toDateString()
                    : null;

                $newCheckupDate = $data['health_checkup_due_date'] ?? null;

                $goal->steps_target = (int) $data['steps_target'];
                $goal->meals_target = (int) $data['meals_target'];
                $goal->sleep_hours_target = (float) $data['sleep_hours_target'];
                $goal->exercise_minutes_target = (int) $data['exercise_minutes_target'];
                $goal->water_ml_target = (int) $data['water_ml_target'];
                $goal->health_checkup_due_date = $newCheckupDate ?: null;
                $goal->reminders_enabled = $request->boolean('reminders_enabled');
                $goal->reminder_interval_hours = (int) $data['reminder_interval_hours'];
                $goal->is_active = true;

                if ($oldCheckupDate !== $newCheckupDate) {
                    $goal->health_checkup_goal_set_at = $newCheckupDate ? now() : null;
                }

                $goal->save();

                /*
                 * Keep the user's existing step-goal infrastructure aligned
                 * only when those optional columns actually exist.
                 */
                if (
                    Schema::hasTable('users')
                    && Schema::hasColumn('users', 'current_step_goal')
                ) {
                    DB::table('users')
                        ->where('id', $request->user()->id)
                        ->update([
                            'current_step_goal' => (int) $goal->steps_target,
                            'updated_at' => now(),
                        ]);
                }

                if (
                    Schema::hasTable('daily_steps')
                    && Schema::hasColumn('daily_steps', 'user_id')
                    && Schema::hasColumn('daily_steps', 'tracking_date')
                    && Schema::hasColumn('daily_steps', 'daily_goal')
                ) {
                    DB::table('daily_steps')
                        ->where('user_id', $request->user()->id)
                        ->whereDate('tracking_date', now()->toDateString())
                        ->update([
                            'daily_goal' => (int) $goal->steps_target,
                            'updated_at' => now(),
                        ]);
                }
            });

            return redirect('/wellbeing')
                ->with('success', 'Wellbeing goals updated successfully.');
        } catch (Throwable $e) {
            Log::error('Wellbeing goals save failed', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect('/wellbeing?open_goals=1')
                ->withInput()
                ->with(
                    'error',
                    'We could not save your wellbeing goals. Please try again after the database update is complete.'
                );
        }
    }
}
