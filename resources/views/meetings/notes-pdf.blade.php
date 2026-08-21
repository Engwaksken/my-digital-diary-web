<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Meeting Notes</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; }
        h1 { font-size: 19px; margin: 0 0 3px; }
        h2 { font-size: 14px; margin: 18px 0 7px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 12px 0 5px; }
        .subtitle { color: #64748b; font-size: 9px; margin-bottom: 16px; }
        .content { white-space: pre-wrap; }
        ul { margin: 4px 0 8px 18px; padding: 0; }
        li { margin-bottom: 3px; }
        .recording { margin-top: 14px; page-break-inside: avoid; }
        .muted { color: #64748b; font-size: 9px; }
        .section { margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>{{ $meeting->title }}</h1>
    <p class="subtitle">
        {{ $meeting->start_at->format('l, M j, Y \a\t g:i A') }}
        @if ($meeting->location) &middot; {{ $meeting->location }} @endif
        @if ($meeting->attendees) &middot; Attendees: {{ $meeting->attendees }} @endif
        <br>Prepared for {{ $user->name }} &middot; {{ now()->format('Y-m-d H:i') }}
    </p>

    @if ($meeting->notes)
        <h2>Meeting Notes / Agenda</h2>
        <div class="content">{{ $meeting->notes }}</div>
    @endif


    @php
        // Some Meeting PDF controller paths do not pass $recordings.
        // Load them from the meeting relation as a safe fallback so the PDF
        // still includes transcripts/summaries without throwing an exception.
        if (isset($recordings)) {
            if ($recordings instanceof \Illuminate\Contracts\Pagination\Paginator) {
                $pdfRecordings = collect($recordings->items());
            } elseif ($recordings instanceof \Illuminate\Support\Collection) {
                $pdfRecordings = $recordings;
            } else {
                $pdfRecordings = collect($recordings);
            }
        } else {
            $pdfRecordings = $meeting->recordings()->orderBy('id')->get();
        }
    @endphp

    @foreach ($pdfRecordings as $recording)
        @php
            $summary = $recording->summary;
            if (is_string($summary)) {
                $decodedSummary = json_decode($summary, true);
                $summary = is_array($decodedSummary) ? $decodedSummary : [];
            }
            $summary = is_array($summary) ? $summary : [];
        @endphp

        @if ($recording->transcript || !empty($summary))
            <div class="recording">
                <h2>Recording {{ $loop->count > 1 ? '#' . $loop->iteration : '' }}</h2>
                <p class="muted">
                    {{ $recording->created_at?->format('M j, Y g:i A') }}
                    @if ($recording->duration_seconds) &middot; {{ $recording->formattedDuration() }} @endif
                </p>

                @if ($recording->transcript)
                    <h3>Transcript</h3>
                    <div class="content">{{ $recording->transcript }}</div>
                @endif

                @if (!empty($summary))
                    <h3>AI Summary</h3>

                    @if (!empty($summary['main_points']))
                        <div class="section"><strong>Key Points</strong>
                            <ul>@foreach ($summary['main_points'] as $point)<li>{{ $point }}</li>@endforeach</ul>
                        </div>
                    @endif

                    @if (!empty($summary['decisions']))
                        <div class="section"><strong>Decisions</strong>
                            <ul>@foreach ($summary['decisions'] as $decision)<li>{{ $decision }}</li>@endforeach</ul>
                        </div>
                    @endif

                    @if (!empty($summary['action_items']))
                        <div class="section"><strong>Action Items</strong>
                            <ul>
                                @foreach ($summary['action_items'] as $item)
                                    <li>
                                        {{ $item['task'] ?? '' }}
                                        @if (!empty($item['assigned_to'])) — {{ $item['assigned_to'] }} @endif
                                        @if (!empty($item['deadline'])) (Due: {{ $item['deadline'] }}) @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($summary['questions_for_followup']))
                        <div class="section"><strong>Follow-up Questions</strong>
                            <ul>@foreach ($summary['questions_for_followup'] as $question)<li>{{ $question }}</li>@endforeach</ul>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    @endforeach
</body>
</html>
