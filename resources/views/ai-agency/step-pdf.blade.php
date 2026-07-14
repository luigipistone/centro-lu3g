<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $step->name }}</title>
    <style>
        @page { margin: 95px 58px 65px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #243047; font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; line-height: 1.58; }
        header { position: fixed; top: -72px; left: 0; right: 0; height: 58px; border-bottom: 1px solid #dce3ed; }
        footer { position: fixed; bottom: -45px; left: 0; right: 0; border-top: 1px solid #e6ebf2; padding-top: 9px; color: #7c8799; font-size: 8pt; }
        .logo { float: left; width: 34px; height: 34px; margin-top: 7px; }
        .brand { float: left; margin: 11px 0 0 10px; font-size: 13pt; font-weight: bold; color: #172033; }
        .meta { float: right; margin-top: 8px; text-align: right; color: #68758a; font-size: 8.5pt; }
        .eyebrow { margin: 0 0 8px; color: #1677ff; font-size: 8.5pt; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; color: #172033; font-size: 23pt; line-height: 1.18; }
        .subtitle { margin: 9px 0 28px; color: #68758a; font-size: 10pt; }
        .content h1 { margin: 27px 0 10px; font-size: 18pt; }
        .content h2 { margin: 25px 0 9px; padding-bottom: 6px; border-bottom: 1px solid #dce3ed; color: #172033; font-size: 14pt; }
        .content h3 { margin: 19px 0 7px; color: #263650; font-size: 11.5pt; }
        .content p { margin: 0 0 10px; }
        .content ul, .content ol { margin: 6px 0 14px; padding-left: 22px; }
        .content li { margin-bottom: 5px; }
        .content blockquote { margin: 14px 0; padding: 10px 14px; border-left: 3px solid #1677ff; background: #f3f7fd; color: #44516a; }
        .content table { width: 100%; margin: 14px 0; border-collapse: collapse; font-size: 9pt; }
        .content th, .content td { padding: 7px 8px; border: 1px solid #dce3ed; text-align: left; vertical-align: top; }
        .content th { background: #eff5fc; color: #172033; }
        .content a { color: #1269db; text-decoration: none; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
<header>
    <img class="logo" src="{{ public_path('brand/logo-gestionale-webapp.svg') }}" alt="Il Centro">
    <div class="brand">Il Centro</div>
    <div class="meta">Agenzia AI<br>{{ $generatedAt->timezone('Europe/Rome')->format('d/m/Y · H:i') }}</div>
</header>
<footer>
    <span>{{ $run->project_name }} · {{ $step->name }}</span>
    <span style="float:right">Pagina <span class="page-number"></span></span>
</footer>
<main>
    <p class="eyebrow">{{ $step->service_name ?: 'Workflow operativo' }}</p>
    <h1>{{ $step->name }}</h1>
    <p class="subtitle">Progetto: {{ $run->project_name }}@if($run->client_name) · Cliente: {{ $run->client_name }}@endif</p>
    <div class="content">{!! $content !!}</div>
</main>
</body>
</html>
