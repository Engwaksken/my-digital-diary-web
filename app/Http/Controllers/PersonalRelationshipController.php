<?php

namespace App\Http\Controllers;

use App\Models\PersonalRelationship;
use Illuminate\Http\Request;

class PersonalRelationshipController extends CrudController
{
    protected string $model = PersonalRelationship::class;
    protected string $routeName = 'relationships';
    protected string $title = 'Relationship';
    protected string $icon = 'fa-solid fa-heart';
    protected string $accent = 'red';
    protected string $dateField = 'next_planned_interaction';

    protected array $fields = [
        ['name'=>'name','label'=>'Person','type'=>'text','required'=>true],
        ['name'=>'category','label'=>'Category','type'=>'select','required'=>true,'options'=>[
            'family'=>'Family','friend'=>'Friend','colleague'=>'Colleague','mentor'=>'Mentor',
            'partner'=>'Partner','client'=>'Client','professional'=>'Professional','other'=>'Other',
        ]],
        ['name'=>'relation_label','label'=>'Relationship','type'=>'text'],
        ['name'=>'priority','label'=>'Priority','type'=>'select','required'=>true,'options'=>['high'=>'High','medium'=>'Medium','low'=>'Low']],
        ['name'=>'last_meaningful_interaction','label'=>'Last Interaction','type'=>'date'],
        ['name'=>'next_planned_interaction','label'=>'Next Call / Meeting / Check-in','type'=>'date'],
        ['name'=>'interaction_notes','label'=>'Previous Interaction / Conversation Notes','type'=>'textarea'],
    ];

    protected array $rules = [
        'name'=>'required|string|max:255','category'=>'required|in:family,friend,colleague,mentor,partner,client,professional,other',
        'relation_label'=>'nullable|string|max:255','priority'=>'required|in:high,medium,low',
        'last_meaningful_interaction'=>'nullable|date','next_planned_interaction'=>'nullable|date',
        'interaction_notes'=>'nullable|string',
    ];

    public function index(Request $request)
    {
        return $this->renderIndex(
            $request,
            PersonalRelationship::where('user_id', $request->user()->id),
            ['purposeText' => 'Use Relationships to intentionally maintain important people, remember context and plan meaningful follow-ups.']
        );
    }

    protected function stats(Request $request): array
    {
        $base = PersonalRelationship::where('user_id',$request->user()->id)->where('is_archived', false);
        return [
            ['label'=>'Check-ins due','value'=>(string) (clone $base)->whereNotNull('next_planned_interaction')->whereDate('next_planned_interaction','<=',today()->addDays(7))->count(),'icon'=>'fa-solid fa-phone','color'=>'rose'],
            ['label'=>'High priority','value'=>(string) (clone $base)->where('priority','high')->count(),'icon'=>'fa-solid fa-star','color'=>'amber'],
            ['label'=>'People tracked','value'=>(string) $base->count(),'icon'=>'fa-solid fa-heart','color'=>'red'],
        ];
    }
}
