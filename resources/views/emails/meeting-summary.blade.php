@component('mail::message')
# Meeting Summary: {{ $recording->meeting->title }}

Shared by **{{ $senderName }}**.

@if (! empty($recording->summary['main_points']))
## Main Discussion Points
@foreach ($recording->summary['main_points'] as $point)
- {{ $point }}
@endforeach
@endif

@if (! empty($recording->summary['decisions']))
## Decisions Made
@foreach ($recording->summary['decisions'] as $decision)
- {{ $decision }}
@endforeach
@endif

@if (! empty($recording->summary['action_items']))
## Action Items
@foreach ($recording->summary['action_items'] as $item)
- {{ $item['task'] ?? '' }}
@if (! empty($item['assigned_to'])) (Assigned: {{ $item['assigned_to'] }}) @endif
@if (! empty($item['deadline'])) (Deadline: {{ $item['deadline'] }}) @endif
@endforeach
@endif

@if (! empty($recording->summary['questions_for_followup']))
## Questions Requiring Follow-up
@foreach ($recording->summary['questions_for_followup'] as $question)
- {{ $question }}
@endforeach
@endif

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
