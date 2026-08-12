{{--
    Deliberately NOT extending layouts.app — that layout loads Tailwind via
    a CDN <script> tag, which requires JavaScript execution to generate
    styles. DomPDF cannot run JavaScript, so PDF views need real, static CSS
    of their own, kept simple (DomPDF only supports a subset of CSS2.1/3 —
    no flexbox/grid, limited web fonts).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Personal Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 20px; margin-bottom: 2px; }
        .subtitle { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        th { background-color: #f1f5f9; color: #475569; }
        .stat-row td { padding: 6px 6px; }
        .empty { color: #94a3b8; font-style: italic; }
        .overdue { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Personal Report</h1>
    <p class="subtitle">
        {{ $user->name }} &middot; generated {{ $snapshot['generated_at']->format('Y-m-d H:i') }}
    </p>

    <h2>Finances (this month)</h2>
    <table>
        <tr class="stat-row">
            <td><strong>Income</strong></td>
            <td>{{ format_money($snapshot['finances']['income_this_month']) }}</td>
        </tr>
        <tr class="stat-row">
            <td><strong>Expenses</strong></td>
            <td>{{ format_money($snapshot['finances']['expenses_this_month']) }}</td>
        </tr>
        <tr class="stat-row">
            <td><strong>Monthly budget</strong></td>
            <td>{{ format_money($snapshot['finances']['monthly_budget_total']) }}</td>
        </tr>
    </table>

    @if ($snapshot['finances']['top_expense_categories']->isNotEmpty())
        <table>
            <thead><tr><th>Category</th><th>Spent</th></tr></thead>
            <tbody>
                @foreach ($snapshot['finances']['top_expense_categories'] as $row)
                    <tr><td>{{ $row->category }}</td><td>{{ format_money($row->total) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Plans</h2>
    @if ($snapshot['plans']['overdue_or_upcoming']->isNotEmpty())
        <table>
            <thead><tr><th>Title</th><th>Period</th><th>Target Date</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($snapshot['plans']['overdue_or_upcoming'] as $plan)
                    <tr>
                        <td>{{ $plan->title }}</td>
                        <td>{{ ucfirst($plan->period) }}</td>
                        <td>{{ optional($plan->target_date)->format('Y-m-d') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $plan->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No pending plans with a target date.</p>
    @endif

    <h2>Health</h2>
    <p>
        Average sleep (last 7 days):
        {{ $snapshot['health']['avg_sleep_minutes_last_7_days']
            ? number_format($snapshot['health']['avg_sleep_minutes_last_7_days'] / 60, 1) . ' hrs'
            : 'No data' }}
    </p>
    @if ($snapshot['health']['upcoming_checkups']->isNotEmpty())
        <table>
            <thead><tr><th>Checkup</th><th>Due</th></tr></thead>
            <tbody>
                @foreach ($snapshot['health']['upcoming_checkups'] as $checkup)
                    <tr><td>{{ $checkup->checkup_type }}</td><td>{{ $checkup->next_due_date->format('Y-m-d H:i') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No upcoming checkups scheduled.</p>
    @endif

    <h2>Projects</h2>
    @if ($snapshot['projects']->isNotEmpty())
        <table>
            <thead><tr><th>Project</th><th>Status</th><th>Tasks</th><th>Deadline</th></tr></thead>
            <tbody>
                @foreach ($snapshot['projects'] as $project)
                    <tr>
                        <td>{{ $project->name }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $project->status)) }}</td>
                        <td>{{ $project->tasks_count }}</td>
                        <td>{{ optional($project->deadline)->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No active projects.</p>
    @endif

    <h2>Education</h2>
    @if ($snapshot['education']->isNotEmpty())
        <table>
            <thead><tr><th>Title</th><th>Level</th><th>Status</th><th>Target</th></tr></thead>
            <tbody>
                @foreach ($snapshot['education'] as $edu)
                    <tr>
                        <td>{{ $edu->title }}</td>
                        <td>{{ ucfirst($edu->level) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $edu->status)) }}</td>
                        <td>{{ optional($edu->target_completion_date)->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No education plans in progress.</p>
    @endif

    <h2>Network</h2>
    @if ($snapshot['network']->isNotEmpty())
        <table>
            <thead><tr><th>Name</th><th>Type</th><th>Next Follow-up</th></tr></thead>
            <tbody>
                @foreach ($snapshot['network'] as $contact)
                    <tr>
                        <td>{{ $contact->name }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $contact->relationship_type)) }}</td>
                        <td>{{ $contact->next_follow_up_date->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No follow-ups scheduled.</p>
    @endif

    <h2>Relationships</h2>
    @if ($snapshot['relationships']->isNotEmpty())
        <table>
            <thead><tr><th>Name</th><th>Category</th><th>Priority</th><th>Next Check-in</th></tr></thead>
            <tbody>
                @foreach ($snapshot['relationships'] as $rel)
                    @php $overdue = $rel->next_planned_interaction && $rel->next_planned_interaction->isPast(); @endphp
                    <tr>
                        <td>{{ $rel->name }}</td>
                        <td>{{ ucfirst($rel->category) }}</td>
                        <td>{{ ucfirst($rel->priority) }}</td>
                        <td class="{{ $overdue ? 'overdue' : '' }}">
                            {{ optional($rel->next_planned_interaction)->format('Y-m-d') ?? '—' }}
                            @if ($overdue) (overdue) @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No relationship check-ins planned.</p>
    @endif

    {{--
        Debts, Savings Goals, Meetings, Reminders, Spiritual Growth, and
        Exercise were all added as modules after this report was first
        built, and were never added here — see UserSnapshotService's
        docblock. Fixed below.
    --}}
    <h2>Debts</h2>
    @if ($snapshot['debts']->isNotEmpty())
        <table>
            <thead><tr><th>Type</th><th>Person</th><th>Amount</th><th>Due</th></tr></thead>
            <tbody>
                @foreach ($snapshot['debts'] as $debt)
                    <tr>
                        <td>{{ $debt->type === 'borrowed' ? 'I owe' : 'Owed to me' }}</td>
                        <td>{{ $debt->person_name }}</td>
                        <td>{{ format_money($debt->amount) }}</td>
                        <td>{{ optional($debt->due_date)->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No outstanding debts.</p>
    @endif

    <h2>Savings Goals</h2>
    @if ($snapshot['savings_goals']->isNotEmpty())
        <table>
            <thead><tr><th>Goal</th><th>Saved</th><th>Target</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($snapshot['savings_goals'] as $goal)
                    <tr>
                        <td>{{ $goal->name }}</td>
                        <td>{{ format_money($goal->contributions_sum_amount ?? 0) }}</td>
                        <td>{{ format_money($goal->target_amount) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $goal->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No savings goals yet.</p>
    @endif

    <h2>Upcoming Meetings</h2>
    @if ($snapshot['upcoming_meetings']->isNotEmpty())
        <table>
            <thead><tr><th>Title</th><th>When</th><th>Location</th></tr></thead>
            <tbody>
                @foreach ($snapshot['upcoming_meetings'] as $meeting)
                    <tr>
                        <td>{{ $meeting->title }}</td>
                        <td>{{ $meeting->start_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $meeting->location ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No meetings scheduled.</p>
    @endif

    <h2>Active Reminders</h2>
    @if ($snapshot['active_reminders']->isNotEmpty())
        <table>
            <thead><tr><th>Title</th><th>Repeats</th><th>Next</th></tr></thead>
            <tbody>
                @foreach ($snapshot['active_reminders'] as $reminder)
                    <tr>
                        <td>{{ $reminder->title }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $reminder->frequency)) }}</td>
                        <td>{{ $reminder->next_run_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No active reminders.</p>
    @endif

    <h2>Spiritual Growth</h2>
    <p>{{ $snapshot['spiritual_practices_this_week'] }} practice(s) logged this week.</p>

    <h2>Exercise</h2>
    @if ($snapshot['recent_exercise']->isNotEmpty())
        <table>
            <thead><tr><th>Activity</th><th>Duration</th><th>Intensity</th><th>When</th></tr></thead>
            <tbody>
                @foreach ($snapshot['recent_exercise'] as $log)
                    <tr>
                        <td>{{ $log->activity }}</td>
                        <td>{{ $log->duration_minutes }} min</td>
                        <td>{{ ucfirst($log->intensity) }}</td>
                        <td>{{ $log->performed_at->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No exercise logged recently.</p>
    @endif
</body>
</html>
