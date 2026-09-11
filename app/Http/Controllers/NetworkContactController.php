<?php

namespace App\Http\Controllers;

use App\Models\NetworkContact;
use Illuminate\Http\Request;

class NetworkContactController extends CrudController
{
    protected string $model = NetworkContact::class;
    protected string $routeName = 'network-contacts';
    protected string $title = 'Network Contact';
    protected string $icon = 'fa-solid fa-people-arrows';
    protected string $accent = 'sky';
    protected string $dateField = 'next_follow_up_date';

    protected array $fields = [
        ['name'=>'name','label'=>'Person','type'=>'text','required'=>true],
        ['name'=>'relationship_type','label'=>'Connection Type','type'=>'select','required'=>true,'options'=>[
            'business'=>'Business','career'=>'Career','investor'=>'Investor','mentor'=>'Mentor',
            'friend'=>'Friend','community'=>'Community','association'=>'Professional Association',
            'client'=>'Client','partner'=>'Partner','other'=>'Other',
        ]],
        ['name'=>'network_groups','label'=>'Networks / Groups','type'=>'text','placeholder'=>'Business, Investors, Community'],
        ['name'=>'company','label'=>'Company / Organisation','type'=>'text'],
        ['name'=>'email','label'=>'Email','type'=>'text'],
        ['name'=>'phone','label'=>'Phone','type'=>'text'],
        ['name'=>'met_through','label'=>'Where / How You Met','type'=>'text'],
        ['name'=>'last_contact_date','label'=>'Last Contact','type'=>'date'],
        ['name'=>'next_follow_up_date','label'=>'Next Follow-up','type'=>'date'],
        ['name'=>'opportunities','label'=>'Opportunities Connected to This Person / Network','type'=>'textarea'],
        ['name'=>'action_points','label'=>'Action Points','type'=>'textarea'],
        ['name'=>'goal','label'=>'Networking Goal','type'=>'textarea'],
        ['name'=>'notes','label'=>'Notes','type'=>'textarea'],
    ];

    protected array $rules = [
        'name'=>'required|string|max:255',
        'relationship_type'=>'required|in:business,career,investor,mentor,friend,community,association,client,partner,other',
        'network_groups'=>'nullable|string|max:1000','company'=>'nullable|string|max:255',
        'email'=>'nullable|email|max:255','phone'=>'nullable|string|max:40','met_through'=>'nullable|string|max:255',
        'last_contact_date'=>'nullable|date','next_follow_up_date'=>'nullable|date',
        'opportunities'=>'nullable|string','action_points'=>'nullable|string','goal'=>'nullable|string','notes'=>'nullable|string',
    ];

    public function index(Request $request)
    {
        $query = NetworkContact::where('user_id', $request->user()->id);

        if ($request->filled('network')) {
            $query->where('network_groups', 'like', '%' . $request->string('network') . '%');
        }

        return $this->renderIndex(
            $request,
            $query,
            ['purposeText' => 'Use Networks to organise where your connections belong, capture opportunities and make follow-up part of your growth process.']
        );
    }

    protected function stats(Request $request): array
    {
        $base = NetworkContact::where('user_id',$request->user()->id)->where('is_archived', false);
        return [
            ['label'=>'Follow-ups due','value'=>(string) (clone $base)->whereNotNull('next_follow_up_date')->whereDate('next_follow_up_date','<=',today()->addDays(7))->count(),'icon'=>'fa-solid fa-user-clock','color'=>'sky'],
            ['label'=>'Overdue','value'=>(string) (clone $base)->whereNotNull('next_follow_up_date')->whereDate('next_follow_up_date','<',today())->count(),'icon'=>'fa-solid fa-triangle-exclamation','color'=>'rose'],
            ['label'=>'Contacts','value'=>(string) $base->count(),'icon'=>'fa-solid fa-people-group','color'=>'blue'],
        ];
    }
}
