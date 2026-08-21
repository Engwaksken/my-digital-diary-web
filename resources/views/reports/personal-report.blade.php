<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Digital Diary - Personal Report</title>
    <style>
        @page { margin: 34px 38px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color:#1f2937; font-size:10px; line-height:1.42; }
        h1 { margin:0; font-size:23px; color:#065f46; }
        h2 { margin:20px 0 7px; padding-bottom:5px; border-bottom:1px solid #d1d5db; color:#064e3b; font-size:14px; }
        h3 { margin:12px 0 5px; font-size:11px; color:#374151; }
        p { margin:4px 0; }
        .subtitle { color:#6b7280; margin-top:4px; }
        .summary { margin-top:13px; width:100%; border-collapse:separate; border-spacing:6px; }
        .summary td { width:33%; border:1px solid #d1fae5; background:#ecfdf5; padding:9px; border-radius:5px; }
        .label { color:#6b7280; font-size:8.5px; text-transform:uppercase; }
        .value { color:#064e3b; font-size:14px; font-weight:bold; margin-top:3px; }
        table.data { width:100%; border-collapse:collapse; margin:5px 0 10px; }
        table.data th { background:#f3f4f6; color:#374151; text-align:left; padding:5px; border:1px solid #e5e7eb; font-size:8.5px; }
        table.data td { padding:5px; border:1px solid #e5e7eb; vertical-align:top; }
        .muted { color:#6b7280; }
        .empty { color:#9ca3af; font-style:italic; }
        .badge { display:inline-block; padding:2px 5px; border-radius:8px; background:#ecfdf5; color:#047857; }
        .page-break { page-break-before:always; }
        ul.features { columns:2; margin:5px 0 0 16px; padding:0; }
        ul.features li { margin:0 0 4px; }
        .footer { margin-top:24px; padding-top:8px; border-top:1px solid #e5e7eb; color:#9ca3af; font-size:8px; }
    </style>
</head>
<body>
    <h1>My Digital Diary - Personal Report</h1>
    <p class="subtitle">
        {{ $user->name ?? $user->email }} &middot;
        Generated {{ $snapshot['generated_at']->format('d M Y, H:i') }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Income this month</div>
                <div class="value">{{ format_money($snapshot['finances']['income_this_month']) }}</div>
            </td>
            <td>
                <div class="label">Expenses this month</div>
                <div class="value">{{ format_money($snapshot['finances']['expenses_this_month']) }}</div>
            </td>
            <td>
                <div class="label">Monthly budget</div>
                <div class="value">{{ format_money($snapshot['finances']['monthly_budget_total']) }}</div>
            </td>
        </tr>
    </table>

    <h2>What's Included in My Digital Diary</h2>
    <p class="muted">This report now reflects the newer planning, receipt scanning, meeting, reminder, billing and reporting features available across web and mobile.</p>
    <ul class="features">
        @foreach ($snapshot['feature_summary'] as $feature)
            <li>{{ $feature }}</li>
        @endforeach
    </ul>

    <h2>Annual & Monthly Plans</h2>
    @if ($snapshot['plans']['annual']->isNotEmpty())
        <table class="data">
            <thead><tr><th>Plan</th><th>Month / Period</th><th>Progress</th><th>Target</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($snapshot['plans']['annual'] as $plan)
                <tr>
                    <td>{{ $plan->title }}</td>
                    <td>{{ $plan->plan_month ? \Illuminate\Support\Carbon::create()->month((int)$plan->plan_month)->format('F') : ucfirst((string)($plan->period ?? 'annual')) }}</td>
                    <td>{{ (int)($plan->progress_percent ?? 0) }}%</td>
                    <td>{{ optional($plan->target_date)->format('d M Y') ?? '-' }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)$plan->status)) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No annual plans recorded.</p>
    @endif

    <h2>Daily Planner & Top 3</h2>
    @php $dailyPlan = $snapshot['daily_planner']; @endphp
    @if ($dailyPlan)
        <p><strong>{{ $dailyPlan->title ?: 'Daily Plan' }}</strong> &middot; {{ optional($dailyPlan->plan_date)->format('d M Y') }}</p>
        @if ($dailyPlan->items->isNotEmpty())
            <table class="data">
                <thead><tr><th>Item</th><th>Priority</th><th>Time</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($dailyPlan->items as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td>{{ ucfirst((string)$item->priority) }}</td>
                        <td>{{ $item->start_time ?: '-' }}{{ $item->end_time ? ' - '.$item->end_time : '' }}</td>
                        <td>{{ $item->is_completed ? 'Completed' : 'Pending' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No items on this daily plan.</p>
        @endif
    @else
        <p class="empty">No daily planner data recorded yet.</p>
    @endif

    <h2>Financial Planner</h2>
    @php $fp = $snapshot['financial_planner']; @endphp
    @if ($fp)
        <table class="data">
            <tbody>
                <tr><th>Current age</th><td>{{ $fp->current_age ?? '-' }}</td><th>Retirement age</th><td>{{ $fp->retirement_age ?? '-' }}</td></tr>
                <tr><th>Current retirement savings</th><td>{{ format_money((float)($fp->current_retirement_savings ?? 0)) }}</td><th>Monthly contribution</th><td>{{ format_money((float)($fp->monthly_retirement_contribution ?? 0)) }}</td></tr>
                <tr><th>Expected annual return</th><td>{{ $fp->expected_annual_return ?? '-' }}%</td><th>Inflation rate</th><td>{{ $fp->inflation_rate ?? '-' }}%</td></tr>
                <tr><th>Desired monthly retirement income</th><td>{{ format_money((float)($fp->desired_monthly_retirement_income ?? 0)) }}</td><th>Retirement years</th><td>{{ $fp->retirement_years ?? '-' }}</td></tr>
            </tbody>
        </table>
    @else
        <p class="empty">Financial Planner profile has not been completed.</p>
    @endif

    <h2>Finances & Itemized Expenses</h2>
    @if ($snapshot['finances']['top_expense_categories']->isNotEmpty())
        <h3>Top expense categories this month</h3>
        <table class="data">
            <thead><tr><th>Category</th><th>Total</th></tr></thead>
            <tbody>
            @foreach ($snapshot['finances']['top_expense_categories'] as $row)
                <tr><td>{{ $row->category }}</td><td>{{ format_money($row->total) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($snapshot['finances']['recent_itemized_expenses']->isNotEmpty())
        <h3>Recent expenses</h3>
        <table class="data">
            <thead><tr><th>Date</th><th>Category</th><th>Items</th><th>Amount</th></tr></thead>
            <tbody>
            @foreach ($snapshot['finances']['recent_itemized_expenses'] as $expense)
                <tr>
                    <td>{{ optional($expense->spent_at)->format('d M Y') }}</td>
                    <td>{{ $expense->category }}</td>
                    <td>{{ $expense->items->pluck('description')->filter()->implode(', ') ?: '-' }}</td>
                    <td>{{ format_money($expense->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Projects & Tasks</h2>
    @if ($snapshot['projects']->isNotEmpty())
        <table class="data">
            <thead><tr><th>Project</th><th>Status</th><th>Tasks</th><th>Deadline</th></tr></thead>
            <tbody>
            @foreach ($snapshot['projects'] as $project)
                <tr>
                    <td>{{ $project->name }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)$project->status)) }}</td>
                    <td>{{ $project->tasks_count }}</td>
                    <td>{{ optional($project->deadline)->format('d M Y') ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No active projects.</p>
    @endif

    <h2>Meetings, Recordings & AI Notes</h2>
    @if ($snapshot['upcoming_meetings']->isNotEmpty())
        <h3>Upcoming meetings</h3>
        <table class="data">
            <thead><tr><th>Meeting</th><th>When</th><th>Location</th></tr></thead>
            <tbody>
            @foreach ($snapshot['upcoming_meetings'] as $meeting)
                <tr><td>{{ $meeting->title }}</td><td>{{ optional($meeting->start_at)->format('d M Y H:i') }}</td><td>{{ $meeting->location ?: '-' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if ($snapshot['meeting_recordings']->isNotEmpty())
        <h3>Recent recordings</h3>
        <table class="data">
            <thead><tr><th>Meeting</th><th>Duration</th><th>Transcription</th><th>AI Summary</th></tr></thead>
            <tbody>
            @foreach ($snapshot['meeting_recordings'] as $recording)
                <tr>
                    <td>{{ $recording->meeting->title ?? 'Meeting' }}</td>
                    <td>{{ $recording->formattedDuration() }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)($recording->transcription_status ?? 'pending'))) }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)($recording->summary_status ?? 'pending'))) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @elseif ($snapshot['upcoming_meetings']->isEmpty())
        <p class="empty">No meeting or recording data to show.</p>
    @endif

    <h2>Smart Reminders</h2>
    @if ($snapshot['active_reminders']->isNotEmpty())
        <table class="data">
            <thead><tr><th>Reminder</th><th>Module</th><th>Repeats</th><th>Channel</th><th>Next</th></tr></thead>
            <tbody>
            @foreach ($snapshot['active_reminders'] as $reminder)
                <tr>
                    <td>{{ $reminder->title }}</td>
                    <td>{{ $reminder->module ?: 'General' }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)$reminder->frequency)) }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)$reminder->channel)) }}</td>
                    <td>{{ optional($reminder->next_run_at)->format('d M Y H:i') ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No active reminders.</p>
    @endif

    <div class="page-break"></div>
    <h2>Health & Wellness</h2>
    <p><strong>Average sleep, last 7 days:</strong>
        {{ $snapshot['health']['avg_sleep_minutes_last_7_days'] ? number_format($snapshot['health']['avg_sleep_minutes_last_7_days']/60,1).' hrs' : 'No data' }}
    </p>
    @if ($snapshot['health']['upcoming_checkups']->isNotEmpty())
        <table class="data">
            <thead><tr><th>Checkup</th><th>Next due</th></tr></thead>
            <tbody>@foreach ($snapshot['health']['upcoming_checkups'] as $checkup)<tr><td>{{ $checkup->checkup_type }}</td><td>{{ optional($checkup->next_due_date)->format('d M Y H:i') }}</td></tr>@endforeach</tbody>
        </table>
    @endif
    @if ($snapshot['recent_exercise']->isNotEmpty())
        <h3>Recent exercise</h3>
        <table class="data">
            <thead><tr><th>Activity</th><th>Duration</th><th>Intensity</th><th>Date</th></tr></thead>
            <tbody>@foreach ($snapshot['recent_exercise'] as $log)<tr><td>{{ $log->activity }}</td><td>{{ $log->duration_minutes }} min</td><td>{{ ucfirst((string)$log->intensity) }}</td><td>{{ optional($log->performed_at)->format('d M Y') }}</td></tr>@endforeach</tbody>
        </table>
    @endif

    <h2>Education, Network & Relationships</h2>
    @if ($snapshot['education']->isNotEmpty())
        <h3>Education plans</h3>
        <table class="data"><thead><tr><th>Plan</th><th>Level</th><th>Status</th><th>Target</th></tr></thead><tbody>
        @foreach ($snapshot['education'] as $edu)<tr><td>{{ $edu->title }}</td><td>{{ ucfirst((string)$edu->level) }}</td><td>{{ ucwords(str_replace('_',' ',(string)$edu->status)) }}</td><td>{{ optional($edu->target_completion_date)->format('d M Y') ?? '-' }}</td></tr>@endforeach
        </tbody></table>
    @endif
    @if ($snapshot['network']->isNotEmpty())
        <h3>Network follow-ups</h3>
        <table class="data"><thead><tr><th>Name</th><th>Relationship</th><th>Follow-up</th></tr></thead><tbody>
        @foreach ($snapshot['network'] as $contact)<tr><td>{{ $contact->name }}</td><td>{{ ucwords(str_replace('_',' ',(string)$contact->relationship_type)) }}</td><td>{{ optional($contact->next_follow_up_date)->format('d M Y') }}</td></tr>@endforeach
        </tbody></table>
    @endif
    @if ($snapshot['relationships']->isNotEmpty())
        <h3>Relationship check-ins</h3>
        <table class="data"><thead><tr><th>Name</th><th>Category</th><th>Priority</th><th>Next check-in</th></tr></thead><tbody>
        @foreach ($snapshot['relationships'] as $rel)<tr><td>{{ $rel->name }}</td><td>{{ ucfirst((string)$rel->category) }}</td><td>{{ ucfirst((string)$rel->priority) }}</td><td>{{ optional($rel->next_planned_interaction)->format('d M Y') ?? '-' }}</td></tr>@endforeach
        </tbody></table>
    @endif

    <h2>Debts & Savings</h2>
    @if ($snapshot['debts']->isNotEmpty())
        <h3>Outstanding debts</h3>
        <table class="data"><thead><tr><th>Type</th><th>Person</th><th>Amount</th><th>Due</th></tr></thead><tbody>
        @foreach ($snapshot['debts'] as $debt)<tr><td>{{ $debt->type === 'borrowed' ? 'I owe' : 'Owed to me' }}</td><td>{{ $debt->person_name }}</td><td>{{ format_money($debt->amount) }}</td><td>{{ optional($debt->due_date)->format('d M Y') ?? '-' }}</td></tr>@endforeach
        </tbody></table>
    @endif
    @if ($snapshot['savings_goals']->isNotEmpty())
        <h3>Savings goals</h3>
        <table class="data"><thead><tr><th>Goal</th><th>Saved</th><th>Target</th><th>Status</th></tr></thead><tbody>
        @foreach ($snapshot['savings_goals'] as $goal)<tr><td>{{ $goal->name }}</td><td>{{ format_money($goal->contributions_sum_amount ?? 0) }}</td><td>{{ format_money($goal->target_amount) }}</td><td>{{ ucwords(str_replace('_',' ',(string)$goal->status)) }}</td></tr>@endforeach
        </tbody></table>
    @endif

    <h2>Spiritual Growth</h2>
    <p>{{ $snapshot['spiritual_practices_this_week'] }} practice(s) logged this week.</p>

    <h2>Subscription, Invoices & Receipts</h2>
    @php $billing = $snapshot['billing']; @endphp
    <p><strong>Current plan:</strong> {{ $billing['subscription_plan']->name ?? 'Not assigned' }}</p>
    @if ($billing['recent_payments']->isNotEmpty())
        <table class="data">
            <thead><tr><th>Date</th><th>Plan</th><th>Method</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            @foreach ($billing['recent_payments'] as $payment)
                <tr>
                    <td>{{ optional($payment->created_at)->format('d M Y') }}</td>
                    <td>{{ $payment->plan->name ?? '-' }}</td>
                    <td>{{ ucwords(str_replace('_',' ',(string)$payment->method)) }}</td>
                    <td>{{ ($payment->currency ?? 'UGX').' '.number_format((float)$payment->amount, 0) }}</td>
                    <td>{{ ucfirst((string)$payment->status) }}</td>
                    <td>{{ $payment->receipt_number ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No subscription payments recorded.</p>
    @endif

    <div class="footer">
        Generated by My Digital Diary. This report is a summary of information recorded in the user's account at the generation time.
    </div>
</body>
</html>
