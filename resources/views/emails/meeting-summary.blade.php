@php
    $meeting = $recording->meeting;

    $summary = is_array($recording->summary)
        ? $recording->summary
        : (array) ($recording->summary ?? []);

    $tz = $meeting?->user?->timezone
        ?? 'Africa/Kampala';
@endphp

<!doctype html>

<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>
        Meeting Notes & Summary
    </title>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#f8fafc;
        font-family:Arial,Helvetica,sans-serif;
        color:#334155;
    "
>
<table
    role="presentation"
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="background:#f8fafc;padding:24px 12px;"
>
<tr>
<td align="center">

<table
    role="presentation"
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        max-width:720px;
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:16px;
        overflow:hidden;
    "
>

<tr>
<td
    style="
        padding:24px;
        background:#0f766e;
        color:#ffffff;
    "
>
    <div
        style="
            font-size:12px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            opacity:.85;
        "
    >
        My Digital Diary
    </div>

    <h1
        style="
            margin:8px 0 0;
            font-size:22px;
            line-height:1.3;
        "
    >
        {{ $meeting?->title ?: 'Meeting' }}
    </h1>

    <p
        style="
            margin:8px 0 0;
            font-size:13px;
            opacity:.9;
        "
    >
        Meeting notes, transcript and summary
    </p>
</td>
</tr>

<tr>
<td style="padding:24px;">

    <p
        style="
            margin:0 0 16px;
            font-size:14px;
            line-height:1.6;
        "
    >
        Hello,
    </p>

    <p
        style="
            margin:0 0 20px;
            font-size:14px;
            line-height:1.6;
        "
    >
        {{ $senderName }} has shared information from
        <strong>{{ $meeting?->title ?: 'this meeting' }}</strong>
        with you.
    </p>

    @if($personalMessage)
        <div
            style="
                margin-bottom:20px;
                padding:14px 16px;
                border-left:4px solid #0d9488;
                background:#f0fdfa;
                border-radius:8px;
            "
        >
            <div
                style="
                    margin-bottom:6px;
                    font-size:12px;
                    font-weight:700;
                    color:#0f766e;
                "
            >
                Message from {{ $senderName }}
            </div>

            <div
                style="
                    white-space:pre-line;
                    font-size:14px;
                    line-height:1.6;
                "
            >{{ $personalMessage }}</div>
        </div>
    @endif

    <table
        role="presentation"
        width="100%"
        cellpadding="0"
        cellspacing="0"
        style="
            margin-bottom:22px;
            border-collapse:collapse;
            border:1px solid #e2e8f0;
        "
    >
        @if($meeting?->start_at)
            <tr>
                <td
                    style="
                        width:130px;
                        padding:10px 12px;
                        background:#f8fafc;
                        border-bottom:1px solid #e2e8f0;
                        font-size:12px;
                        font-weight:700;
                    "
                >
                    Date
                </td>

                <td
                    style="
                        padding:10px 12px;
                        border-bottom:1px solid #e2e8f0;
                        font-size:13px;
                    "
                >
                    {{
                        optional($meeting->start_at)
                            ->timezone($tz)
                            ->format('d M Y, g:i A')
                    }}
                </td>
            </tr>
        @endif

        @if($meeting?->location)
            <tr>
                <td
                    style="
                        width:130px;
                        padding:10px 12px;
                        background:#f8fafc;
                        font-size:12px;
                        font-weight:700;
                    "
                >
                    Location / Link
                </td>

                <td
                    style="
                        padding:10px 12px;
                        font-size:13px;
                        word-break:break-word;
                    "
                >
                    @if(
                        filter_var(
                            $meeting->location,
                            FILTER_VALIDATE_URL
                        )
                    )
                        <a
                            href="{{ $meeting->location }}"
                            style="color:#0f766e;"
                        >
                            {{ $meeting->location }}
                        </a>
                    @else
                        {{ $meeting->location }}
                    @endif
                </td>
            </tr>
        @endif
    </table>

    @if($includeNotes)
        <div style="margin-bottom:26px;">
            <h2
                style="
                    margin:0 0 10px;
                    font-size:17px;
                    color:#0f172a;
                "
            >
                Meeting Notes
            </h2>

            @if(filled($meeting?->notes))
                <div
                    style="
                        padding:14px;
                        background:#f8fafc;
                        border-radius:10px;
                        font-size:14px;
                        line-height:1.7;
                        white-space:pre-line;
                    "
                >{{ $meeting->notes }}</div>
            @else
                <p
                    style="
                        color:#94a3b8;
                        font-size:13px;
                    "
                >
                    No meeting notes were recorded.
                </p>
            @endif
        </div>
    @endif

    @if($includeTranscript && filled($recording->transcript))
        <div style="margin-bottom:26px;">
            <h2
                style="
                    margin:0 0 10px;
                    font-size:17px;
                    color:#0f172a;
                "
            >
                Transcript
            </h2>

            <div
                style="
                    max-height:600px;
                    padding:14px;
                    background:#f8fafc;
                    border-radius:10px;
                    font-size:13px;
                    line-height:1.7;
                    white-space:pre-line;
                "
            >{{ $recording->transcript }}</div>
        </div>
    @endif

    @if($includeSummary && !empty($summary))
        <div style="margin-bottom:10px;">

            <h2
                style="
                    margin:0 0 16px;
                    font-size:17px;
                    color:#0f172a;
                "
            >
                AI Meeting Summary
            </h2>

            <h3
                style="
                    margin:0 0 8px;
                    font-size:14px;
                    color:#334155;
                "
            >
                Main Discussion Points
            </h3>

            @if(!empty($summary['main_points']))
                <ul
                    style="
                        margin:0 0 20px;
                        padding-left:22px;
                        font-size:14px;
                        line-height:1.7;
                    "
                >
                    @foreach($summary['main_points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            @else
                <p
                    style="
                        margin:0 0 20px;
                        color:#94a3b8;
                        font-size:13px;
                    "
                >
                    No discussion points were identified.
                </p>
            @endif

            <h3
                style="
                    margin:0 0 8px;
                    font-size:14px;
                    color:#334155;
                "
            >
                Decisions
            </h3>

            @if(!empty($summary['decisions']))
                <ul
                    style="
                        margin:0 0 20px;
                        padding-left:22px;
                        font-size:14px;
                        line-height:1.7;
                    "
                >
                    @foreach($summary['decisions'] as $decision)
                        <li>{{ $decision }}</li>
                    @endforeach
                </ul>
            @else
                <p
                    style="
                        margin:0 0 20px;
                        color:#94a3b8;
                        font-size:13px;
                    "
                >
                    No decisions were identified.
                </p>
            @endif

            <h3
                style="
                    margin:0 0 8px;
                    font-size:14px;
                    color:#334155;
                "
            >
                Action Items
            </h3>

            @if(!empty($summary['action_items']))
                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    style="
                        margin-bottom:20px;
                        border-collapse:collapse;
                    "
                >
                    @foreach($summary['action_items'] as $item)
                        <tr>
                            <td
                                style="
                                    padding:9px 10px;
                                    border-bottom:1px solid #e2e8f0;
                                    font-size:13px;
                                    line-height:1.5;
                                "
                            >
                                @if(is_array($item))
                                    <strong>
                                        {{ $item['task'] ?? 'Action item' }}
                                    </strong>

                                    @if(!empty($item['assigned_to']))
                                        <br>
                                        Assigned to:
                                        {{ $item['assigned_to'] }}
                                    @endif

                                    @if(!empty($item['deadline']))
                                        <br>
                                        Deadline:
                                        {{ $item['deadline'] }}
                                    @endif
                                @else
                                    {{ $item }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p
                    style="
                        margin:0 0 20px;
                        color:#94a3b8;
                        font-size:13px;
                    "
                >
                    No action items were identified.
                </p>
            @endif

            @if(
                !empty(
                    $summary[
                        'questions_for_followup'
                    ]
                )
            )
                <h3
                    style="
                        margin:0 0 8px;
                        font-size:14px;
                        color:#334155;
                    "
                >
                    Follow-up Questions
                </h3>

                <ul
                    style="
                        margin:0;
                        padding-left:22px;
                        font-size:14px;
                        line-height:1.7;
                    "
                >
                    @foreach(
                        $summary[
                            'questions_for_followup'
                        ] as $question
                    )
                        <li>
                            {{ $question }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

</td>
</tr>

<tr>
<td
    style="
        padding:18px 24px;
        background:#f8fafc;
        border-top:1px solid #e2e8f0;
        color:#64748b;
        font-size:11px;
        line-height:1.6;
    "
>
    This email was shared through My Digital Diary by
    {{ $senderName }}.

    <br>

    Please contact the meeting organiser if any information
    requires clarification.
</td>
</tr>

</table>

</td>
</tr>
</table>
</body>
</html>