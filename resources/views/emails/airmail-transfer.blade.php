<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>File ricevuti con Airmail - Il Centro</title>
    <style>
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .card { border-radius: 22px !important; }
            .content { padding: 24px !important; }
            .button { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f3f8ff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#172033;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $sender }} ti ha inviato dei file con Airmail.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f8ff;margin:0;padding:28px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" class="container" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:600px;">
                    <tr>
                        <td style="padding:0 0 16px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span style="display:inline-block;width:44px;height:44px;border-radius:16px;background:#ffffff;border:1px solid #dbeafe;text-align:center;vertical-align:middle;box-shadow:0 10px 28px rgba(11,110,243,0.12);">
                                            <img src="{{ $logoUrl }}" width="30" height="30" alt="Il Centro" style="display:block;margin:7px auto;border:0;outline:none;text-decoration:none;">
                                        </span>
                                        <span style="display:inline-block;margin-left:10px;vertical-align:middle;font-size:18px;font-weight:800;color:#0f172a;letter-spacing:0;">Il Centro</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="card" style="overflow:hidden;border-radius:28px;background:#ffffff;border:1px solid #dbeafe;box-shadow:0 24px 70px rgba(28,42,73,0.12);">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr><td style="height:5px;background:#0b6ef3;font-size:0;line-height:0;">&nbsp;</td></tr>
                                <tr>
                                    <td class="content" style="padding:32px;">
                                        <p style="margin:0 0 8px 0;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#0b6ef3;">Airmail</p>
                                        <h1 style="margin:0 0 16px 0;font-size:26px;line-height:1.18;font-weight:800;color:#101828;">{{ $sender }} ti ha inviato dei file</h1>
                                        <div style="margin:0 0 24px 0;padding:18px;border-radius:18px;background:#f8fbff;border:1px solid #e6f0ff;font-size:16px;line-height:1.55;color:#263247;">
                                            @if ($message)
                                                {!! nl2br(e($message)) !!}
                                            @else
                                                I file sono pronti per il download.
                                            @endif
                                        </div>
                                        <a href="{{ $url }}" class="button" style="display:inline-block;border-radius:16px;background:#0b6ef3;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;padding:14px 22px;box-shadow:0 14px 28px rgba(11,110,243,0.24);">Apri e scarica i file</a>
                                        <p style="margin:22px 0 0 0;font-size:13px;line-height:1.5;color:#667085;">Il link scade il {{ $expiresAt }}.</p>
                                        <p style="margin:14px 0 0 0;font-size:12px;line-height:1.5;color:#667085;">Se il pulsante non funziona, copia e incolla questo link nel browser:<br><a href="{{ $url }}" style="color:#0b6ef3;text-decoration:none;word-break:break-all;">{{ $url }}</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr><td style="padding:18px 6px 0 6px;text-align:center;font-size:12px;line-height:1.5;color:#7a8699;">File condivisi con Airmail · Il Centro</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
