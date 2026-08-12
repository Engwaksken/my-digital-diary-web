<?php

namespace App\Http\Controllers\Api;

use App\Models\SpiritualPractice;

class SpiritualPracticeController extends ApiCrudController
{
    protected string $model = SpiritualPractice::class;

    protected array $rules = [
        'practice_type' => 'required|in:prayer,meditation,scripture_reading,worship,fasting,service,journaling,other',
        'title' => 'nullable|string|max:255',
        'practiced_at' => 'required|date',
        'duration_minutes' => 'nullable|integer|min:0',
        'next_planned_date' => 'nullable|date',
        'reflection' => 'nullable|string',
    ];
}
