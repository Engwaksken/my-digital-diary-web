<?php
namespace App\Http\Controllers\Api;
use App\Models\PersonalRelationship;
class RelationshipController extends ApiCrudController
{
    protected string $model = PersonalRelationship::class;
    protected array $rules = [
        'name'=>'required|string|max:255',
        'category'=>'required|in:family,friend,colleague,mentor,partner,client,professional,other',
        'relation_label'=>'nullable|string|max:255','priority'=>'required|in:high,medium,low',
        'email'=>'nullable|email|max:255','phone'=>'nullable|string|max:40',
        'birthday'=>'nullable|date','anniversary'=>'nullable|date',
        'last_meaningful_interaction'=>'nullable|date','next_planned_interaction'=>'nullable|date',
        'interaction_notes'=>'nullable|string','interests'=>'nullable|string',
        'commitments'=>'nullable|string','follow_up_items'=>'nullable|string',
        'strengthening_goal'=>'nullable|string','notes'=>'nullable|string',
    ];
}
