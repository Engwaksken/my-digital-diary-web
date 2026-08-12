<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #1e293b; font-size: 11px; }
        h1 { color: #00897B; font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F0FDFA; color: #00897B; text-align: left; padding: 6px 8px; border-bottom: 2px solid #00897B; font-size: 10px; }
        td { padding: 6px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
        tr:nth-child(even) td { background: #F8FAFC; }
    </style>
</head>
<body>
    <h1>{{ $moduleLabel }} Report</h1>
    <p class="meta">
        Generated for {{ $generatedFor }} on {{ now()->format('F j, Y') }} — {{ $items->count() }} item(s), archived items excluded.
    </p>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            $value = $item->{$column};
                            if (is_bool($value)) { $value = $value ? 'Yes' : 'No'; }
                            elseif ($value instanceof \Illuminate\Support\Carbon) { $value = $value->format('M j, Y'); }
                            elseif (is_null($value)) { $value = ''; }
                            elseif (is_string($value) && str_starts_with($column, 'status')) { $value = ucwords(str_replace('_', ' ', $value)); }
                        @endphp
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No items to show.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
