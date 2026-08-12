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
        'next_due_date' => 'nullable|date',
        'findings' => 'nullable|string',
    ];
}
