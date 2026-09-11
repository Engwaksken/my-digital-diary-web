<?php
namespace App\Http\Controllers\Api;
use App\Models\NetworkContact;
class NetworkContactController extends ApiCrudController
{
    protected string $model = NetworkContact::class;
    protected array $rules = [
        'name'=>'required|string|max:255',
        'relationship_type'=>'required|in:business,career,investor,mentor,friend,community,association,client,partner,other',
        'network_groups'=>'nullable|string|max:1000','company'=>'nullable|string|max:255',
        'email'=>'nullable|email|max:255','phone'=>'nullable|string|max:40',
        'met_through'=>'nullable|string|max:255','last_contact_date'=>'nullable|date',
        'next_follow_up_date'=>'nullable|date','opportunities'=>'nullable|string',
        'action_points'=>'nullable|string','goal'=>'nullable|string','notes'=>'nullable|string',
    ];
}
