<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b6ef3">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/brand/logo-gestionale-webapp.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>File condivisi · Airmail · Il Centro</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
            --foreground: hsl(220 25% 12%);
            --muted: hsl(220 10% 46%);
            --primary: hsl(213 100% 50%);
            --primary-dark: hsl(213 92% 45%);
            --radius: 1.125rem;
            --radius-sm: 0.875rem;
            --shadow-soft: 0 18px 50px hsl(220 28% 16% / 0.08), inset 0 1px 0 hsl(0 0% 100% / 0.74);
        }
        * { box-sizing: border-box; }
        html { min-height: 100%; }
        body {
            min-height: 100vh;
            margin: 0;
            background: radial-gradient(circle at 18% 0%, hsl(213 100% 88% / 0.34), transparent 34rem), linear-gradient(135deg, hsl(216 45% 98%) 0%, hsl(205 45% 96%) 44%, hsl(216 42% 97%) 100%);
            color: var(--foreground);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        a:focus-visible { outline: 2px solid var(--primary); outline-offset: 3px; }
        .site-header { border-bottom: 1px solid hsl(220 18% 88% / 0.7); background: hsl(0 0% 100% / 0.72); }
        .site-header-inner { width: min(100% - 32px, 1120px); min-height: 76px; margin: auto; display: flex; align-items: center; gap: 12px; }
        .brand-mark { width: 40px; height: 40px; display: grid; place-items: center; flex: none; overflow: hidden; border-radius: var(--radius-sm); background: hsl(0 0% 100% / 0.86); box-shadow: inset 0 1px 0 hsl(0 0% 100% / 0.82), 0 12px 24px hsl(213 100% 50% / 0.16); }
        .brand-mark img { width: 34px; height: 34px; object-fit: contain; transform: translateY(8%) scale(1.08); }
        .brand-name { font-size: 16px; font-weight: 750; }
        .brand-divider { width: 1px; height: 20px; margin-inline: 2px; background: hsl(220 18% 88%); }
        .brand-service { color: var(--muted); font-size: 14px; font-weight: 600; }
        main { width: min(100% - 32px, 1120px); margin: 48px auto 80px; }
        .page-heading { margin-bottom: 24px; }
        h1 { margin: 0; font-size: 24px; line-height: 1.3; font-weight: 750; overflow-wrap: anywhere; }
        .intro { margin: 7px 0 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .surface { border: 1px solid hsl(0 0% 100% / 0.74); border-radius: var(--radius); background: linear-gradient(180deg, hsl(0 0% 100% / 0.94), hsl(0 0% 100% / 0.82)); box-shadow: var(--shadow-soft); }
        .file-panel { padding: 20px; }
        .panel-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px 20px; padding-bottom: 18px; }
        .panel-title { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 16px; font-weight: 700; }
        .section-icon, .file-icon { display: grid; place-items: center; flex: none; border-radius: var(--radius-sm); color: var(--primary); background: linear-gradient(180deg, hsl(213 100% 96% / 0.96), hsl(213 94% 91% / 0.66)); box-shadow: inset 0 1px 0 hsl(0 0% 100% / 0.74), 0 8px 18px hsl(213 100% 50% / 0.11); }
        .section-icon { width: 32px; height: 32px; }
        .file-icon { width: 42px; height: 42px; }
        .section-icon svg, .file-icon svg, .download svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
        .expiry { color: var(--muted); font-size: 13px; font-weight: 500; }
        .file-list { border-top: 1px solid hsl(220 18% 88% / 0.7); }
        .file { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 14px 0; }
        .file + .file { border-top: 1px solid hsl(220 18% 88% / 0.7); }
        .file:last-child { padding-bottom: 0; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { overflow-wrap: anywhere; font-size: 14px; font-weight: 600; }
        .file-size { margin-top: 3px; color: var(--muted); font-size: 12px; }
        .download { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; gap: 8px; flex: none; padding: 0 14px; border-radius: var(--radius-sm); background: linear-gradient(180deg, hsl(213 100% 58%), var(--primary-dark)); box-shadow: 0 14px 28px hsl(213 100% 50% / 0.24), inset 0 1px 0 hsl(0 0% 100% / 0.24); color: white; font-size: 14px; font-weight: 600; text-decoration: none; transition: filter 180ms ease, transform 180ms ease; }
        .download:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .message-panel { margin-top: 20px; padding: 20px; }
        .message-text { margin: 14px 0 0; color: hsl(220 25% 30%); font-size: 14px; line-height: 1.65; overflow-wrap: anywhere; }
        @media (max-width: 600px) {
            .site-header-inner { min-height: 68px; }
            main { margin-top: 30px; }
            h1 { font-size: 21px; }
            .file-panel, .message-panel { padding: 16px; }
            .file { flex-wrap: wrap; }
            .download { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) { .download { transition: none; } .download:hover { transform: none; } }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="site-header-inner">
            <span class="brand-mark"><img src="/brand/logo-gestionale-webapp.svg" alt="" width="34" height="34"></span>
            <span class="brand-name">Il Centro</span>
            <span class="brand-divider" aria-hidden="true"></span>
            <span class="brand-service">Airmail</span>
        </div>
    </header>
    <main>
        <div class="page-heading">
            <h1>File condivisi da {{ $sender }}</h1>
            <p class="intro">I file sono disponibili fino alla scadenza indicata.</p>
        </div>
        <section class="surface file-panel" aria-labelledby="files-heading">
            <div class="panel-heading">
                <h2 id="files-heading" class="panel-title">
                    <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/></svg></span>
                    {{ $files->count() }} {{ $files->count() === 1 ? 'file disponibile' : 'file disponibili' }}
                </h2>
                <span class="expiry">Scade il {{ \Illuminate\Support\Carbon::parse($transfer->expires_at)->format('d/m/Y H:i') }}</span>
            </div>
            <div class="file-list">
                @foreach ($files as $file)
                    <div class="file">
                        <span class="file-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg></span>
                        <div class="file-info">
                            <div class="file-name">{{ $file->original_name }}</div>
                            <div class="file-size">{{ number_format($file->size / 1024 / 1024, 1, ',', '.') }} MB</div>
                        </div>
                        <a class="download" href="{{ route('airmail.download', [$token, $file->id]) }}" aria-label="Scarica {{ $file->original_name }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>Scarica</a>
                    </div>
                @endforeach
            </div>
        </section>
        @if ($transfer->message)
            <section class="surface message-panel" aria-labelledby="message-heading">
                <h2 id="message-heading" class="panel-title">Messaggio</h2>
                <p class="message-text">{!! nl2br(e($transfer->message)) !!}</p>
            </section>
        @endif
    </main>
</body>
</html>
