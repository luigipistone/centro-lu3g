<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0b6ef3">
        <link rel="manifest" href="/manifest.webmanifest">
        <title>Attiva notifiche - Il Centro</title>
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                --primary: #0b6ef3;
                --primary-dark: #0756bf;
                --surface: rgba(255, 255, 255, 0.78);
                --text: #172033;
                --muted: #667085;
                --border: rgba(255, 255, 255, 0.76);
            }

            * {
                box-sizing: border-box;
            }

            body {
                min-height: 100vh;
                margin: 0;
                background: radial-gradient(circle at top left, rgba(11, 110, 243, 0.14), transparent 34%), #f6f8fb;
                color: var(--text);
                display: grid;
                place-items: center;
                padding: 24px;
            }

            .panel {
                width: min(560px, 100%);
                border: 1px solid var(--border);
                border-radius: 24px;
                background: var(--surface);
                box-shadow: 0 24px 80px rgba(28, 42, 73, 0.16);
                backdrop-filter: blur(22px);
                padding: 28px;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 22px;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                border-radius: 14px;
                display: grid;
                place-items: center;
                background: #fff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 12px 30px rgba(11, 110, 243, 0.12);
            }

            .brand-mark img {
                width: 28px;
                height: 28px;
                object-fit: contain;
            }

            h1 {
                margin: 0;
                font-size: 24px;
                line-height: 1.15;
            }

            p {
                margin: 10px 0 0;
                color: var(--muted);
                line-height: 1.55;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 24px;
            }

            button,
            a {
                min-height: 44px;
                border-radius: 14px;
                border: 1px solid transparent;
                padding: 0 16px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 700;
                font-size: 14px;
                text-decoration: none;
                cursor: pointer;
            }

            button {
                background: var(--primary);
                color: #fff;
                box-shadow: 0 14px 36px rgba(11, 110, 243, 0.24);
            }

            button:hover {
                background: var(--primary-dark);
            }

            button:disabled {
                cursor: wait;
                opacity: 0.72;
            }

            a {
                color: #344054;
                background: rgba(255, 255, 255, 0.68);
                border-color: rgba(255, 255, 255, 0.8);
            }

            .status {
                min-height: 48px;
                margin-top: 20px;
                border-radius: 16px;
                border: 1px solid rgba(11, 110, 243, 0.14);
                background: rgba(11, 110, 243, 0.08);
                color: #0756bf;
                padding: 13px 14px;
                font-size: 14px;
                font-weight: 650;
                line-height: 1.45;
            }

            .status.error {
                border-color: rgba(217, 45, 32, 0.18);
                background: rgba(217, 45, 32, 0.08);
                color: #b42318;
            }

            .status.ok {
                border-color: rgba(18, 183, 106, 0.18);
                background: rgba(18, 183, 106, 0.1);
                color: #027a48;
            }

            .hint {
                margin-top: 16px;
                font-size: 13px;
            }

            .diagnostics {
                margin-top: 16px;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.58);
                border: 1px solid rgba(255, 255, 255, 0.76);
                padding: 12px 14px;
                color: var(--muted);
                font-size: 12px;
                line-height: 1.6;
                word-break: break-word;
            }

            .manual-help {
                display: none;
                margin-top: 16px;
                border-radius: 18px;
                border: 1px solid rgba(245, 158, 11, 0.22);
                background: rgba(245, 158, 11, 0.1);
                padding: 14px;
                color: #92400e;
                font-size: 13px;
                line-height: 1.55;
            }

            .manual-help.is-visible {
                display: block;
            }

            .manual-help strong {
                display: block;
                margin-bottom: 6px;
                color: #78350f;
            }

            .manual-help ol {
                margin: 8px 0 0 18px;
                padding: 0;
            }

            .manual-help code {
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.58);
                padding: 2px 6px;
                color: #78350f;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            }
        </style>
    </head>
    <body>
        <main class="panel">
            <div class="brand">
                <span class="brand-mark">
                    <img src="/icons/icon-192.svg" alt="Il Centro">
                </span>
                <div>
                    <h1>Attiva notifiche push</h1>
                    <p>Consenti le notifiche del browser per ricevere aggiornamenti anche fuori dall'app.</p>
                </div>
            </div>

            <div class="actions">
                <button id="enablePush" type="button">Attiva notifiche</button>
                <button id="testPush" type="button">Invia test push</button>
                <a href="{{ route('notifications.index') }}">Torna alle notifiche</a>
            </div>

            <div id="status" class="status">
                Stato attuale: {{ $subscriptionCount > 0 ? 'dispositivo gia registrato' : 'dispositivo non ancora registrato' }}.
            </div>

            <div id="diagnostics" class="diagnostics">Controllo supporto browser...</div>

            <div id="manualHelp" class="manual-help">
                <strong>Opera sta bloccando la finestra automatica.</strong>
                <div>Abilita il permesso manualmente, poi torna su questa pagina: la registrazione push partirà da sola.</div>
                <ol>
                    <li>Clicca sull'icona a sinistra dell'indirizzo del sito.</li>
                    <li>Apri le impostazioni del sito per <code>centro.lu3g.com</code>.</li>
                    <li>Imposta <strong>Notifiche</strong> su <strong>Consenti</strong>.</li>
                    <li>Torna qui o ricarica la pagina.</li>
                </ol>
                <div style="margin-top: 8px;">In alternativa apri <code>opera://settings/content/notifications</code> e abilita le notifiche per questo dominio.</div>
            </div>

            <p class="hint">Se il browser mostra una finestra di conferma, scegli Consenti. Dopo l'attivazione questa pagina invia una notifica di prova.</p>
        </main>

        <script>
            const vapidPublicKey = @json($vapidPublicKey);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const enableButton = document.getElementById('enablePush');
            const testButton = document.getElementById('testPush');
            const statusBox = document.getElementById('status');
            const diagnosticsBox = document.getElementById('diagnostics');
            const manualHelp = document.getElementById('manualHelp');
            let autoRegistering = false;

            function setStatus(message, type = '') {
                statusBox.textContent = message;
                statusBox.className = `status ${type}`.trim();
            }

            function showManualHelp() {
                manualHelp.classList.add('is-visible');
            }

            function hideManualHelp() {
                manualHelp.classList.remove('is-visible');
            }

            function withTimeout(promise, milliseconds, fallback) {
                return Promise.race([
                    promise,
                    new Promise((resolve) => window.setTimeout(() => resolve(fallback), milliseconds)),
                ]);
            }

            async function readDiagnostics() {
                let permissionsState = 'non disponibile';
                try {
                    if (navigator.permissions?.query) {
                        permissionsState = (await navigator.permissions.query({ name: 'notifications' })).state;
                    }
                } catch (error) {
                    permissionsState = 'non leggibile';
                }

                return {
                    https: window.isSecureContext ? 'ok' : 'no',
                    notification: 'Notification' in window ? Notification.permission : 'non supportato',
                    permissionsApi: permissionsState,
                    serviceWorker: 'serviceWorker' in navigator ? 'ok' : 'non supportato',
                    pushManager: 'PushManager' in window ? 'ok' : 'non supportato',
                    standalone: window.matchMedia?.('(display-mode: standalone)').matches ? 'si' : 'no',
                    browser: navigator.userAgent,
                };
            }

            async function renderDiagnostics() {
                const diagnostics = await readDiagnostics();
                diagnosticsBox.innerHTML = [
                    `<strong>Diagnosi browser</strong>`,
                    `HTTPS: ${diagnostics.https}`,
                    `Permesso notifiche: ${diagnostics.notification}`,
                    `Permissions API: ${diagnostics.permissionsApi}`,
                    `Service worker: ${diagnostics.serviceWorker}`,
                    `Push manager: ${diagnostics.pushManager}`,
                    `Modalita app installata: ${diagnostics.standalone}`,
                    `Browser: ${diagnostics.browser}`,
                ].join('<br>');
            }

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);

                return Uint8Array.from([...rawData].map((character) => character.charCodeAt(0)));
            }

            async function registerPushSubscription() {
                if (!vapidPublicKey) {
                    throw new Error('Chiave pubblica push non configurata.');
                }

                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                await navigator.serviceWorker.ready;

                const existingSubscription = await registration.pushManager.getSubscription();
                const subscription = existingSubscription || await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                });

                const response = await fetch('/push-subscriptions', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(subscription.toJSON()),
                });

                if (!response.ok) {
                    throw new Error('Registrazione dispositivo non riuscita.');
                }

                await registration.showNotification('Il Centro', {
                    body: 'Notifiche push attivate correttamente.',
                    icon: '/icons/icon-192.svg',
                    badge: '/icons/icon-192.svg',
                    tag: 'centro-push-enabled',
                    data: { url: '/notifications' },
                });
            }

            async function registerIfPermissionAlreadyGranted(source = 'manuale') {
                if (autoRegistering || !('Notification' in window) || Notification.permission !== 'granted') {
                    return false;
                }

                autoRegistering = true;
                hideManualHelp();
                setStatus(`Permesso notifiche rilevato (${source}). Registro questo dispositivo...`, '');

                try {
                    await registerPushSubscription();
                    await renderDiagnostics();
                    setStatus('Notifiche push attivate. Questo dispositivo e registrato.', 'ok');
                    return true;
                } catch (error) {
                    await renderDiagnostics();
                    setStatus(error.message || 'Registrazione push non riuscita.', 'error');
                    return false;
                } finally {
                    autoRegistering = false;
                }
            }

            enableButton.addEventListener('click', async () => {
                enableButton.disabled = true;

                try {
                    if (!window.isSecureContext) {
                        throw new Error('Le notifiche push richiedono HTTPS.');
                    }

                    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                        throw new Error('Questo browser non supporta le notifiche push web.');
                    }

                    let permission = Notification.permission;
                    if (permission !== 'granted') {
                        setStatus('Sto chiedendo il permesso al browser. Se compare una finestra, scegli Consenti...', '');
                        permission = await withTimeout(Notification.requestPermission(), 12000, 'timeout');
                    }

                    await renderDiagnostics();

                    if (permission === 'timeout') {
                        showManualHelp();
                        throw new Error('Il browser non ha aperto o completato la finestra di consenso. Controlla nelle impostazioni del sito che le notifiche non siano bloccate e che il browser permetta ai siti di chiedere notifiche.');
                    }

                    if (permission !== 'granted') {
                        showManualHelp();
                        throw new Error('Permesso notifiche non concesso dal browser.');
                    }

                    hideManualHelp();
                    await renderDiagnostics();
                    setStatus('Permesso concesso. Registro questo dispositivo...', '');
                    await registerPushSubscription();
                    await renderDiagnostics();
                    setStatus('Notifiche push attivate. Questo dispositivo e registrato.', 'ok');
                    enableButton.disabled = false;
                } catch (error) {
                    await renderDiagnostics();
                    setStatus(error.message || 'Attivazione notifiche non riuscita.', 'error');
                    enableButton.disabled = false;
                }
            });

            testButton.addEventListener('click', async () => {
                testButton.disabled = true;

                try {
                    const response = await fetch('/push/test', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Invio test push non riuscito.');
                    }

                    const payload = await response.json();
                    setStatus(`Test push inviato. Dispositivi registrati: ${payload.subscriptions}.`, payload.subscriptions > 0 ? 'ok' : 'error');
                } catch (error) {
                    setStatus(error.message || 'Invio test push non riuscito.', 'error');
                } finally {
                    testButton.disabled = false;
                }
            });

            window.addEventListener('focus', () => registerIfPermissionAlreadyGranted('focus'));
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    registerIfPermissionAlreadyGranted('ritorno pagina');
                }
            });

            if (navigator.permissions?.query) {
                navigator.permissions.query({ name: 'notifications' })
                    .then((permissionStatus) => {
                        permissionStatus.onchange = () => {
                            renderDiagnostics();
                            registerIfPermissionAlreadyGranted('permesso browser');
                        };
                    })
                    .catch(() => {});
            }

            renderDiagnostics().then(() => registerIfPermissionAlreadyGranted('pagina aperta'));
        </script>
    </body>
</html>
