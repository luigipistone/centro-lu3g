<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 42px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #263244; font-size: 10px; }
        .brand { color: #1767d2; font-size: 10px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .rule { height: 3px; background: #1767d2; margin: 9px 0 22px; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .header td { vertical-align: middle; }
        .photo-cell { width: 94px; }
        .photo { width: 76px; height: 76px; }
        .initials { width: 76px; height: 76px; line-height: 76px; text-align: center; background: #eaf2ff; color: #1767d2; font-size: 25px; font-weight: bold; border-radius: 38px; }
        .eyebrow { color: #7c8798; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        h1 { margin: 3px 0 6px; color: #192a41; font-size: 21px; line-height: 1.2; }
        .subtitle { color: #657287; font-size: 10px; }
        .details { width: 100%; border-collapse: collapse; }
        .details td, .details th { padding: 7px 10px; border-bottom: 1px solid #e6ebf1; text-align: left; vertical-align: top; }
        .details th { padding: 10px; background: #edf4fd; color: #1859ad; font-size: 10px; text-transform: uppercase; }
        .details .label { width: 37%; color: #5b687a; font-weight: bold; }
        .details .value { color: #263244; }
        .details .spacer { height: 14px; padding: 0; border: 0; }
        .details tr { page-break-inside: avoid; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; border-top: 1px solid #dfe5ed; padding-top: 8px; color: #8993a2; font-size: 8px; }
    </style>
</head>
<body>
    <div class="brand">Il Centro</div>
    <div class="rule"></div>
    <table class="header">
        <tr>
            <td class="photo-cell">
                @if ($avatar)
                    <img class="photo" src="{{ $avatar }}" alt="Foto di {{ $person->name }}">
                @else
                    <div class="initials">{{ mb_strtoupper(mb_substr(trim($person->name ?: '?'), 0, 1)) }}</div>
                @endif
            </td>
            <td>
                <div class="eyebrow">Scheda persona</div>
                <h1>{{ $person->name }}</h1>
                <div class="subtitle">{{ $person->email }}</div>
            </td>
        </tr>
    </table>
    <table class="details">
        @foreach ($rows as [$label, $value])
            @if ($label === '')
                <tr><td colspan="2" class="spacer"></td></tr>
            @elseif (in_array($label, ['Dati personali', 'Rapporto di lavoro', 'Dati contrattuali riservati'], true))
                <tr><th colspan="2">{{ $label }}</th></tr>
            @else
                <tr><td class="label">{{ $label }}</td><td class="value">{{ $value ?: '—' }}</td></tr>
            @endif
        @endforeach
    </table>
    <div class="footer">Scheda generata il {{ $generatedAt }} · Uso interno</div>
</body>
</html>
