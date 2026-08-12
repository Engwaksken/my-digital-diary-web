<?php

namespace App\Http\Controllers\Api;

use App\Models\SleepLog;

class SleepLogController extends ApiCrudController
{
    protected string $model = SleepLog::class;

    protected array $rules = [
        'sleep_date' => 'required|date',
        'bed_time' => 'nullable|date_format:H:i',
        'wake_time' => 'nullable|date_format:H:i',
        'quality' => 'required|in:poor,fair,good,excellent',
        'notes' => 'nullable|string',
    ];
}
