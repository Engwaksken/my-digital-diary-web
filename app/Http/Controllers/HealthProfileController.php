<?php

namespace App\Http\Controllers;

use App\Models\HealthProfile;
use Illuminate\Http\Request;

class HealthProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'min:20', 'max:350'],
            'height_cm' => ['nullable', 'numeric', 'min:100', 'max:250'],
            'age_range' => ['nullable', 'in:18-24,25-34,35-44,45-54,55-64,65+'],
            'activity_level' => ['nullable', 'in:low,light,moderate,high'],
            'health_goal' => ['nullable', 'in:general_wellbeing,maintain_weight,gain_weight,lose_weight,better_sleep,more_energy'],
            'food_allergies' => ['nullable', 'string', 'max:1500'],
            'dietary_preferences' => ['nullable', 'string', 'max:1500'],
            'health_conditions' => ['nullable', 'string', 'max:1500'],
            'sleep_challenges' => ['nullable', 'string', 'max:1500'],
            'usual_wake_time' => ['nullable', 'date_format:H:i'],
            'usual_bed_time' => ['nullable', 'date_format:H:i'],
        ]);

        $profile = HealthProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        // Any profile change can alter the recommendation, so force a fresh
        // recommendation the next time Sleep or Diet is opened.
        $profile->forceFill([
            'sleep_advice' => null,
            'sleep_advice_generated_at' => null,
            'diet_advice' => null,
            'diet_advice_generated_at' => null,
        ])->save();

        return back()->with('success', 'Health profile updated. Your AI wellbeing advice will refresh automatically.');
    }
}
