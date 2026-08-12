<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AI Plan</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitle { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        .content { white-space: pre-line; line-height: 1.5; }
    </style>
</head>
<body>
    <h1>AI Plan</h1>
    <p class="subtitle">
        {{ $user->name }} &middot; generated {{ $plan->created_at->format('Y-m-d H:i') }}
        @if ($plan->provider)
            &middot; {{ $plan->provider === 'anthropic' ? 'Claude' : 'ChatGPT' }}
        @endif
    </p>

    <div class="content">{{ $plan->cleanContent() }}</div>
</body>
</html>
