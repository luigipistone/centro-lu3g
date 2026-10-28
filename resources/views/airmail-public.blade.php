<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>File ricevuti · Airmail</title>
    <style>
        :root { color-scheme: light; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7fb; color: #172033; }
        main { width: min(100% - 32px, 720px); margin: min(10vh, 80px) auto 64px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 28px; color: #1666d6; font-size: 15px; font-weight: 750; }
        .brand-mark { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 9px; background: #1666d6; color: #fff; font-size: 19px; }
        h1 { margin: 0 0 8px; font-size: clamp(25px, 4vw, 34px); line-height: 1.2; }
        .intro { margin: 0; color: #657084; font-size: 15px; line-height: 1.6; }
        .panel { margin-top: 28px; overflow: hidden; border: 1px solid #dfe5ed; border-radius: 8px; background: #fff; box-shadow: 0 12px 32px rgba(34, 53, 79, .05); }
        .panel-header { display: flex; justify-content: space-between; gap: 16px; padding: 19px 22px; border-bottom: 1px solid #e9edf3; font-size: 14px; font-weight: 700; }
        .expiry { color: #657084; font-weight: 500; }
        .file { display: flex; align-items: center; gap: 14px; min-width: 0; padding: 16px 22px; }
        .file + .file { border-top: 1px solid #edf0f4; }
        .file-icon { display: grid; flex: 0 0 36px; height: 36px; place-items: center; border-radius: 8px; background: #eaf2ff; color: #1666d6; font-weight: 700; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { overflow-wrap: anywhere; font-size: 14px; font-weight: 650; }
        .file-size { margin-top: 3px; color: #657084; font-size: 12px; }
        .download { display: inline-flex; flex: none; align-items: center; justify-content: center; min-height: 36px; padding: 0 13px; border-radius: 7px; background: #1666d6; color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; }
        .download:hover, .download:focus-visible { background: #0f55bb; }
        .message { margin-top: 22px; padding: 18px 22px; border-left: 3px solid #1666d6; border-radius: 5px; background: #fff; color: #384458; font-size: 14px; line-height: 1.6; overflow-wrap: anywhere; }
        @media (max-width: 480px) { .panel-header { flex-direction: column; gap: 4px; } .file { padding: 14px; } .download { padding: 0 10px; } }
    </style>
</head>
<body>
    <main>
        <div class="brand"><span class="brand-mark">A</span>Airmail · Il Centro</div>
        <h1>{{ $sender }} ti ha inviato dei file</h1>
        <p class="intro">Scarica i file che ti servono prima della scadenza del link.</p>
        <section class="panel" aria-label="File disponibili">
            <div class="panel-header">
                <span>{{ $files->count() }} {{ $files->count() === 1 ? 'file disponibile' : 'file disponibili' }}</span>
                <span class="expiry">Scade il {{ \Illuminate\Support\Carbon::parse($transfer->expires_at)->timezone('Europe/Rome')->format('d/m/Y \a\l\l\e H:i') }}</span>
            </div>
            @foreach ($files as $file)
                <div class="file">
                    <span class="file-icon" aria-hidden="true">↓</span>
                    <div class="file-info">
                        <div class="file-name">{{ $file->original_name }}</div>
                        <div class="file-size">{{ number_format($file->size / 1024 / 1024, 1, ',', '.') }} MB</div>
                    </div>
                    <a class="download" href="{{ route('airmail.download', [$token, $file->id]) }}">Scarica</a>
                </div>
            @endforeach
        </section>
        @if ($transfer->message)
            <div class="message">{!! nl2br(e($transfer->message)) !!}</div>
        @endif
    </main>
</body>
</html>
