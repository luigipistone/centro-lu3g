<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }} {{ $document->number ?: 'bozza' }}</title>
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.45; }
        h1, h2, h3, p { margin: 0; }
        .top { display: table; width: 100%; margin-bottom: 32px; }
        .top > div { display: table-cell; vertical-align: top; width: 50%; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .title { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; margin-bottom: 18px; }
        .label { text-transform: uppercase; letter-spacing: .06em; font-size: 10px; color: #9ca3af; font-weight: 700; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f9fafb; color: #6b7280; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; margin-top: 20px; width: 280px; }
        .totals td { border-bottom: 0; padding: 5px 0; }
        .totals .grand td { border-top: 1px solid #d1d5db; padding-top: 10px; font-size: 16px; font-weight: 700; }
        .footer { margin-top: 36px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <div class="top">
        <div>
            <h2>{{ $settings->company_name ?? config('app.name') }}</h2>
            <p class="muted">
                {{ trim(($settings->street ?? '').' '.($settings->street_number ?? '')) }}<br>
                {{ trim(($settings->postal_code ?? '').' '.($settings->city ?? '').' '.($settings->province ? '('.$settings->province.')' : '')) }}<br>
                P.IVA {{ $settings->vat_number ?? '-' }} · CF {{ $settings->tax_code ?? '-' }}<br>
                {{ $settings->email ?? '' }}
            </p>
        </div>
        <div class="right">
            <div class="title">{{ $typeLabel }}</div>
            <p class="muted">
                Numero: <strong>{{ $document->number ?: 'bozza' }}</strong><br>
                Data: {{ \Carbon\Carbon::parse($document->issue_date)->format('d/m/Y') }}<br>
                Scadenza: {{ $document->due_date ? \Carbon\Carbon::parse($document->due_date)->format('d/m/Y') : '-' }}
            </p>
        </div>
    </div>

    <div class="box">
        <div class="label">Cliente</div>
        <strong>{{ $client->legal_name ?: $client->name }}</strong><br>
        {{ trim(($client->street ?: $client->address ?: '').' '.($client->street_number ?: '')) }}<br>
        {{ trim(($client->postal_code ?: '').' '.($client->city ?: '').' '.($client->province ? '('.$client->province.')' : '')) }}<br>
        P.IVA {{ $client->vat_number ?: '-' }} · CF {{ $client->tax_code ?: '-' }}<br>
        {{ $client->email ?: '' }}
    </div>

    @if($document->causale)
        <div class="box">
            <div class="label">Causale</div>
            {{ $document->causale }}
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Descrizione</th>
                <th class="num">Qta</th>
                <th class="num">Prezzo</th>
                <th class="num">IVA</th>
                <th class="num">Subtotale</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ number_format((float) $line->quantity, 2, ',', '.') }}</td>
                    <td class="num">€ {{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $line->vat_rate, 2, ',', '.') }}%</td>
                    <td class="num">€ {{ number_format((float) $line->subtotal, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Nessuna riga.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Imponibile</td><td class="num">€ {{ number_format((float) $document->total_taxable, 2, ',', '.') }}</td></tr>
        <tr><td>IVA</td><td class="num">€ {{ number_format((float) $document->total_vat, 2, ',', '.') }}</td></tr>
        @if((float) $document->total_pension_fund)
            <tr><td>Cassa</td><td class="num">€ {{ number_format((float) $document->total_pension_fund, 2, ',', '.') }}</td></tr>
        @endif
        @if((float) $document->total_withholding)
            <tr><td>Ritenuta</td><td class="num">- € {{ number_format((float) $document->total_withholding, 2, ',', '.') }}</td></tr>
        @endif
        <tr class="grand"><td>Totale</td><td class="num">€ {{ number_format((float) $document->total_amount, 2, ',', '.') }}</td></tr>
    </table>

    @if($document->footer_notes)
        <div class="footer">{{ $document->footer_notes }}</div>
    @endif
</body>
</html>
