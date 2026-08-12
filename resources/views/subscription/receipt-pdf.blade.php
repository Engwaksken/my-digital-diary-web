<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #1e293b; font-size: 13px; }
        .header { border-bottom: 2px solid #00897B; padding-bottom: 12px; margin-bottom: 20px; display: table; width: 100%; }
        .header-logo { display: table-cell; vertical-align: middle; width: 60px; }
        .header-logo img { height: 48px; }
        .header-text { display: table-cell; vertical-align: middle; }
        .header-text h1 { color: #00897B; font-size: 20px; margin: 0; }
        .header-text p { margin: 2px 0 0; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        td:first-child { color: #64748b; width: 40%; }
        .total { font-size: 16px; font-weight: bold; color: #00897B; }
    </style>
</head>
<body>
    <div class="header">
        @if ($siteSettings->logoDataUri())
            <div class="header-logo"><img src="{{ $siteSettings->logoDataUri() }}" alt=""></div>
        @endif
        <div class="header-text">
            <h1>{{ $siteSettings->site_name ?? config('app.name') }}</h1>
            <p>Payment Receipt</p>
        </div>
    </div>

    <table>
        <tr><td>Receipt #</td><td>{{ $payment->receipt_number ?? $payment->id }}</td></tr>
        <tr><td>Date</td><td>{{ $payment->created_at->format('F j, Y g:i A') }}</td></tr>
        <tr><td>Billed To</td><td>{{ $payment->user->name }} ({{ $payment->user->email }})</td></tr>
        <tr><td>Plan</td><td>{{ $payment->plan?->name ?? '—' }}</td></tr>
        @if ($payment->invoice?->billing_period_start && $payment->invoice?->billing_period_end)
            <tr><td>Subscription Period</td><td>{{ $payment->invoice->billing_period_start->format('M j, Y') }} – {{ $payment->invoice->billing_period_end->format('M j, Y') }}</td></tr>
        @endif
        <tr><td>Payment Method</td><td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td></tr>
        <tr><td>Transaction Reference</td><td>{{ $payment->reference }}</td></tr>
        <tr><td>Status</td><td>{{ ucfirst($payment->status) }}</td></tr>
        <tr><td class="total">Amount Paid</td><td class="total">{{ format_money_in($payment->amount, $payment->currency) }}</td></tr>
    </table>
</body>
</html>
