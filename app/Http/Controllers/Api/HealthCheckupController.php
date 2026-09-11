<?php

namespace App\Http\Controllers\Api;

use App\Models\HealthCheckup;

class HealthCheckupController extends ApiCrudController
{
    protected string $model = HealthCheckup::class;

    protected array $rules = [
        'checkup_type' => 'required|string|max:255',
        'checkup_date' => 'required|date',
        'doctor_name' => 'nullable|string|max:255',
        'weight_kg' => 'nullable|numeric|min:1|max:500',
        'blood_pressure_systolic' => 'nullable|integer|min:40|max:300',
        'blood_pressure_diastolic' => 'nullable|integer|min:20|max:200',
        'heart_rate_bpm' => 'nullable|integer|min:20|max:250',
        'next_due_date' => 'nullable|date',
        'findings' => 'nullable|string',
    ];
}
