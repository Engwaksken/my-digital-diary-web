<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AI Plan</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitle { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        .content { white-space: pre-line; line-height: 1.6; overflow-wrap: break-word; }
        .page { width: 100%; }
        .header { border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
    <h1>AI Plan</h1>
    <p class="subtitle">
        {{ $user->name }} &middot; generated {{ $plan->created_at->format('d M Y, g:i A') }}
        @if ($plan->provider)
            &middot; {{ $plan->provider === 'anthropic' ? 'Claude' : 'ChatGPT' }}
        @endif
    </p>
    </div>

    <div class="content">{{ $plan->cleanContent() }}</div>
</div>
</body>
</html>
