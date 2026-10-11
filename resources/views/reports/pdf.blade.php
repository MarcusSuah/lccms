<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 28px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .notice { border: 1px solid #b45309; color: #92400e; padding: 6px 8px; margin: 8px 0; }
        dl { margin: 8px 0 10px; }
        dt { display: inline; font-weight: bold; }
        dd { display: inline; margin: 0 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; font-weight: bold; }
        th, td { border: 1px solid #d1d5db; padding: 3px 5px; vertical-align: top; }
        td.number, th.number { text-align: right; }
        tr:nth-child(even) td { background: #fafafa; }
        footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>
    <footer>
        Liberia Community Census Management System · {{ $title }} · Generated {{ $generatedAt }} for {{ $generatedFor }} · Ref {{ $reference }}
    </footer>

    <h1>{{ $title }}</h1>
    <div class="muted">{{ $description }}</div>

    @if ($personal)
        <div class="notice">Contains personal data. Handle according to the data protection policy; do not share beyond the stated purpose.</div>
    @endif

    <dl>
        @foreach ($filters as $label => $value)
            <dt>{{ $label }}:</dt><dd>{{ $value }}</dd>
        @endforeach
    </dl>

    @if ($truncated)
        <div class="notice">Only the first {{ number_format($maxRows) }} rows are included. Use CSV or Excel for the full data.</div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class(['number' => $column['numeric']])>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        @php($value = $row[$column['key']] ?? null)
                        <td @class(['number' => $column['numeric']])>{{ $value === null ? '—' : (is_int($value) ? number_format($value) : $value) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted">No records match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
