<?php

namespace App\Http\Controllers\Api;

use App\Models\NetworkContact;

class NetworkContactController extends ApiCrudController
{
    protected string $model = NetworkContact::class;

    protected array $rules = [
        'name' => 'required|string|max:255',
        'relationship_type' => 'required|in:mentor,mentee,peer,industry_contact,recruiter,client,other',
        'company' => 'nullable|string|max:255',
        'met_through' => 'nullable|string|max:255',
        'last_contact_date' => 'nullable|date',
        'next_follow_up_date' => 'nullable|date',
        'goal' => 'nullable|string',
        'notes' => 'nullable|string',
    ];
}
