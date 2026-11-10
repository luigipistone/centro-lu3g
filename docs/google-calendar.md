# Google Calendar -> Meeting del Centro

## Attivazione

1. Nel progetto Google Cloud aziendale abilitare Google Calendar API.
2. Configurare Google Auth Platform/consenso OAuth. Per un account Workspace usare l'audience interna, se disponibile. Una app esterna lasciata in Testing puo avere refresh token con durata limitata: pubblicarla secondo le regole Google prima dell'uso continuativo.
3. Creare un client OAuth di tipo Applicazione web, non una chiave API o un service account.
4. Inserire tra gli URI di reindirizzamento autorizzati l'indirizzo esatto mostrato in Centro, Impostazioni > Integrazioni > Google Calendar: `https://centro.lu3g.com/settings/google-calendar/callback`.
5. Un superadmin salva Client ID e Client secret nel Centro e clicca Collega Google. Selezionare un account con accesso in lettura al calendario aziendale desiderato e concedere entrambi i permessi richiesti.
6. Scegliere calendario, data iniziale e orizzonte futuro (3, 6, 12 o 24 mesi); attivare e salvare.

Il collegamento Drive esistente e separato. Non serve concedere scrittura a Calendar. Non vengono creati o modificati appuntamenti Google dal Centro.

## Funzionamento

- `calendar_id + event.id` identifica un Meeting, incluse le singole occorrenze ricorrenti. Non si abbinano meeting manuali per titolo o data.
- Google gestisce titolo, descrizione, luogo, inizio/fine, Meet e assegnatari. Le schermate mostrano questi dati in sola lettura con il rimando a Google. Anche API e drag-and-drop non possono sovrascriverli.
- Email dei partecipanti e dell'organizzatore confrontate esattamente (ignorando maiuscole e spazi esterni) con utenti attivi del Centro. Nessun nuovo utente automatico. Risorse/sale e invitati che hanno rifiutato esclusi. Email senza corrispondenza o ambigue elencate nel riepilogo per il superadmin.
- Date visualizzate nel fuso del calendario; timestamp UTC conservati. Fine degli eventi giornalieri esclusiva, come previsto da Google. Nessuno spostamento per weekend o festivita.
- Importazione dalla data scelta fino a oggi + orizzonte futuro. L'orizzonte avanza con una rilettura quotidiana: le serie infinite non sono troncate silenziosamente a un numero di eventi. Le occorrenze sono espanse da Google, comprese eccezioni e modifiche alla serie.
- Le cancellazioni o uscite dal calendario/range corrente rendono non operative le schede, senza distruggere commenti e allegati. La riapparizione della stessa occorrenza ripristina la stessa scheda. Lo storico antecedente alla data iniziale non viene cancellato.
- Stato di completamento, commenti, allegati e follower interni sono preservati. Prima importazione senza notifiche massive; successivi cambi notificano partecipanti precedenti/attuali e follower secondo le preferenze del Centro.
- Cambiando calendario, i meeting del calendario precedente vengono conservati come non operativi. Sospensione/disconnessione fermano gli aggiornamenti ma mantengono i meeting esistenti.

## Affidabilita e sicurezza

Il comando `php artisan centro:google-calendar-sync` viene eseguito ogni minuto dallo scheduler Laravel esistente. Controlla Google ogni 5 minuti, oppure dopo una notifica/richiesta manuale. `--force` richiede subito una rilettura completa del periodo.

Le notifiche Google arrivano a `POST /api/google-calendar/watch`. Il canale e autenticato con identificativo, token casuale e resource ID, rinnovato automaticamente. Non contiene dati degli eventi. Se il canale fallisce resta attivo il controllo periodico, con avviso nelle impostazioni.

Il cursore incrementale legge le variazioni; in presenza di cambi si scarica integralmente il periodo espanso. Solo dopo tutte le pagine valide vengono applicate modifiche e cancellazioni in transazione. Un errore conserva il cursore precedente e consente un nuovo tentativo; HTTP 410 forza una rilettura completa. Lock condiviso tra sincronizzazione e configurazione, identita uniche e revisioni locali evitano duplicati e riscritture inutili. Eventuali aggiornamenti arrivati durante una lettura restano da elaborare. Le email di utenti creati/modificati vengono riconsiderate al massimo alla rilettura quotidiana.

Client secret e refresh token cifrati con APP_KEY, mai restituiti al browser. OAuth state monouso con scadenza, accesso esclusivo superadmin, HTML importato reso sicuro, URL esterni verificati. Il token e revocato su disconnessione (se Google e raggiungibile) e comunque eliminato dal Centro.

Le notifiche interne sono accodate in una outbox nella stessa transazione dei cambi; inviate successivamente e riprovate se necessario. La consegna esterna segue le garanzie del servizio notifiche esistente, non una promessa di exactly-once SMTP.

## Verifica

Test automatici in `tests/Feature/GoogleCalendarTest.php`, interfaccia in `tests/Browser/google-calendar.mjs`.
Dopo il primo collegamento reale verificare un evento di prova: creazione, cambio orario e invitati, cancellazione, serie ricorrente con una singola eccezione. Controllare riepilogo e ultimo controllo nelle impostazioni. Nessuna credenziale Google reale viene usata nei test simulati.

Riferimenti ufficiali: https://developers.google.com/workspace/calendar/api/guides/sync, https://developers.google.com/workspace/calendar/api/guides/push, https://developers.google.com/identity/protocols/oauth2/web-server.
