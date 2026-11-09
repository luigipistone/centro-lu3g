# Il Centro · API v1

Base URL: https://centro.lu3g.com/api/v1
Prima integrazione: LU3G Page Notes. Questa versione usa token server-to-server; il collegamento OAuth con pulsante «Collega al Centro» non è ancora disponibile.

## Collegamento

Il superadmin apre Impostazioni → Integrazioni → API e webhook → Collega sito. Sceglie sito HTTPS, cliente, progetto, permessi e scadenza (massimo un anno). Copia il token, mostrato una sola volta, nelle impostazioni server di Page Notes. Il plugin deve implementare questo contratto: creare un token sul Centro non aggiorna automaticamente il plugin WordPress.

Header per ogni richiesta:
```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Non inserire il token in URL, JavaScript pubblico, log o email. Il Centro conserva solo l'hash del token tramite Sanctum. La revoca è immediata; task e commenti esistenti restano disponibili nel Centro. Una nuova connessione ha una nuova identità: non usare un nuovo collegamento come semplice sostituzione del precedente durante sincronizzazioni ancora in corso.

Ogni connessione è limitata a UN cliente e UN progetto. Le API task espongono solo le attività create da quella connessione, non tutte quelle del progetto. Clienti e progetti possono essere letti solo se previsti dai permessi. Il superadmin può modificare i permessi e revocare il collegamento. Un amministratore sospeso o che perde il ruolo superadmin non può mantenere attiva l'autorizzazione API.

## Permessi

- `clients:read`: leggi ID e nome del cliente attivo collegato.
- `projects:read`: leggi ID, nome, cliente e stato del progetto collegato.
- `tasks:read`: leggi task e sottoattività della connessione.
- `tasks:write`: crea task/sottoattività e modifica sottoattività della connessione.
- `comments:write`: aggiungi commenti alle sottoattività della connessione.
- `webhooks:write`: registra/rimuovi webhook sul sito collegato e ricevi gli eventi autorizzati. Questo permesso concede la lettura dei dati inclusi negli eventi.

Assenze, smart working, password, documenti, cancellazioni e operazioni globali non sono esposti in questa prima versione.

## Regole comuni

ID: UUID stringa, da conservare senza conversioni numeriche.
Date/ora restituite: ISO 8601 con fuso; due_date: YYYY-MM-DD.
Limiti: 60 richieste/minuto per connessione; ulteriore limite di 120/minuto per IP. Un 429 include Retry-After.
Paginazione: page (da 1), per_page (1–100, default 50); proseguire fino al totale. Non esiste un limite al totale dei record consultabili.
Risposta elenco: {"data":[],"meta":{"page":1,"per_page":50,"total":0}}.
Errori: {"error":{"code":"validation_error","message":"Dati non validi.","fields":{"title":["…"]}}}.
HTTP: 200 lettura/riutilizzo, 201 creato, 204 cancellazione webhook, 401 token invalido/scaduto/revocato, 403 permesso insufficiente, 404 risorsa non disponibile nell'ambito, 409 conflitto, 422 validazione, 429 limite, 500 errore temporaneo. Il plugin riprova 429/5xx con attesa; non riprova indefinitamente 401/403/409/422.

## Verifica e risorse

GET /me
Restituisce connection_id, token_name, scopes, client_id, project_id, expires_at.

POST /connect/revoke
Revoca il collegamento corrente. Risposta: {"revoked":true}.

GET /clients?search=rossi&page=1&per_page=100
Restituisce soltanto il cliente attivo autorizzato: id, name.

GET /projects?page=1&per_page=100
Restituisce il progetto autorizzato: id, name, client_id, status.

GET /tasks
Elenco delle task principali create da questo collegamento.

GET /tasks/{id}
Dettaglio task. Un 404 non autorizza a cercare risorse di altri collegamenti.

## Una task per sito

POST /tasks
{"title":"Revisione sito Rossi","description":"Note sul sito Rossi","external_ref":"site:UUID-INSTALLAZIONE","external_url":"https://rossi.it/wp-admin/","client_id":"UUID-CLIENTE","project_id":"UUID-PROGETTO"}

Obbligatori: title (max 255), description (max 20000), external_ref (max 190).
client_id e project_id possono essere omessi: vengono presi dalla connessione. Se presenti devono coincidere. Il progetto non è facoltativo nella configurazione del collegamento. Non si creano nuove task su cliente/progetto archiviato. Il creatore del collegamento è l'assegnatario iniziale, modificabile dal Centro. Le attività hanno traccia api_created con il nome del collegamento.

Facoltativi: external_url (HTTP/HTTPS max 2000), due_date, status (open/done), author_name, author_email, meta.

Risposta task/sottotask:
{"id":"UUID","task_id":null,"title":"Revisione sito Rossi","description":"<p>…</p>","status":"open","version":1,"external_ref":"site:UUID-INSTALLAZIONE","external_url":"https://rossi.it/wp-admin/","meta":{},"client_id":"UUID","project_id":"UUID","url":"https://centro.lu3g.com/tasks/UUID","updated_at":"2026-11-09T14:00:00+01:00"}

Le descrizioni in ingresso sono testo semplice, convertito in HTML sicuro per l'interfaccia. Quelle restituite sono HTML; un consumatore deve sanificarle prima di visualizzarle. Autori esterni sono dichiarazioni del plugin, non identità verificate o privilegi del Centro. meta: massimo 30 chiavi e 16 KB serializzati; non inviare credenziali o dati non necessari.

## Note come sottoattività

POST /tasks/{id}/subtasks
{"title":"#42 Correggere il titolo","description":"Sostituire il testo\nPagina: https://rossi.it/servizi","external_ref":"note:42","external_url":"https://rossi.it/servizi?lu3g-note=42","author_name":"Mario Rossi","author_email":"mario@example.com","status":"open","meta":{"page_url":"https://rossi.it/servizi","element":".titolo","viewport":1280}}

Stesse regole della task. La sottoattività eredita cliente e progetto. Non sono ammesse sottoattività di sottoattività via API.

GET /tasks/{id}/subtasks?updated_since=2026-11-09T12:00:00Z&page=1&per_page=100
Elenco paginato. updated_since è inclusivo: sovrapporre leggermente le finestre e deduplicare per ID. Utile per riallineare lo stato anche se il webhook è fallito. Per una riconciliazione completa leggere periodicamente tutte le pagine.

PATCH /subtasks/{id}
{"status":"done","version":1}
Accetta status, title, description e version. Version è consigliato: un valore vecchio produce 409; rileggere prima di decidere come risolvere il conflitto. Senza version prevale l'ultima scrittura ricevuta. Gli stati interni diversi da done sono rappresentati come open. Un aggiornamento di stato identico non genera una nuova modifica. Per eliminare una nota sul sito, chiuderla e aggiungere un commento: non è prevista cancellazione della task tramite API.

Il completamento restituisce 409 se ci sono dipendenze aperte o ulteriori sottoattività aperte aggiunte manualmente nel Centro: il plugin non può chiuderle indirettamente. Descrizioni modificate conservano il collegamento alla pagina e i metadati esterni. Creazioni, modifiche e commenti usano le notifiche del Centro, rispettando le preferenze dei destinatari.

## Commenti

POST /subtasks/{id}/comments
{"body":"Corretto, controlla","author_name":"Luca","external_ref":"reply:57"}
body ed external_ref obbligatori. Risposta: {"id":"UUID-COMMENTO"}.
Il commento è attribuito all'autorizzazione del collegamento e riporta esplicitamente l'autore esterno e Page Notes.

## Idempotenza

Conservare external_ref stabile per installazione, nota e commento. La chiave include il collegamento, il tipo di risorsa e la task genitore: siti diversi non collidono. Ripetere la stessa creazione con gli stessi dati restituisce 200 e lo stesso ID. Stessa chiave con dati diversi restituisce 409; per modificare una nota usare PATCH. La protezione include richieste contemporanee. Se un record viene cancellato manualmente dal Centro, il riferimento resta riservato: una nuova task richiede un nuovo external_ref. Non ricreare automaticamente in loop.

## Webhook

POST /webhooks
{"task_id":"UUID-TASK","url":"https://rossi.it/wp-json/lu3g-notes/v1/centro","events":["subtask.updated","comment.created"]}
Risposta 201: {"id":"UUID-WEBHOOK","secret":"SEGRETO-MOSTRATO-UNA-VOLTA"}.
Un webhook per task/collegamento. Per sostituirlo: DELETE /webhooks/{id} e nuova registrazione. La cancellazione rimuove anche le consegne collegate.
URL HTTPS, porta 443, stesso dominio del collegamento, nessuna credenziale in URL; destinazioni IP/private/locali vietate. DNS ricontrollato a ogni consegna e IP fissato per quella richiesta; nessun redirect seguito.

Il Centro rileva lo stato delle sottoattività collegate ogni minuto. Alla prima osservazione può inviare uno stato iniziale. Cambi multipli tra due rilevazioni sono accorpati all'ultimo stato: è una sincronizzazione dello stato, non un audit di ogni transizione. I nuovi commenti del Centro vengono notificati singolarmente; quelli arrivati dal plugin non vengono rimandati indietro. I commenti preesistenti possono essere inclusi alla prima rilevazione; modifiche/cancellazioni dei commenti non sono sincronizzate in v1.

Evento:
{"id":"UUID-EVENTO","event":"subtask.updated","occurred_at":"2026-11-09T14:00:00+01:00","origin":"centro","data":{"subtask_id":"UUID","external_ref":"note:42","status":"done","title":"…","description":"<p>…</p>","version":2}}

comment.created include subtask_id, external_ref della nota, comment_id, body HTML e by (nome autore interno).

Header:
```http
X-Centro-Event-ID: UUID evento
X-Centro-Timestamp: Unix timestamp della consegna
X-Centro-Signature: sha256=<HMAC-SHA256(timestamp + "." + corpo HTTP grezzo, secret)>
```

ATTENZIONE: la firma comprende anche il timestamp, diversamente dalla prima specifica PDF. Non ricalcolare il corpo da un oggetto JSON. Usare confronto a tempo costante, rifiutare timestamp oltre ±300 secondi, deduplicare per ID evento. I tentativi dello stesso evento mantengono ID e corpo ma ricevono timestamp/firma nuovi. Applicare solo versioni più recenti e non rimandare al Centro un evento ricevuto dal Centro.

Rispondere 2xx dopo aver conservato l'evento. In caso di errore: ritentativi dopo circa 1, 5, 30, 120 minuti; massimo 5 tentativi complessivi. Timeout 8 secondi. L'ordine di consegna non è garantito. La revoca interrompe le nuove consegne. I tentativi falliti sono consultabili e reinviabili dalle impostazioni.

## Gestione e operatività

Impostazioni → Integrazioni → API e webhook: collegamenti, permessi, revoca, ultimi 50 accessi e ultimi 50 tentativi, documentazione e download OpenAPI.
Log delle richieste: metodo, percorso, collegamento, stato HTTP e data; mai token o contenuti. Conservazione 30 giorni. Consegne riuscite/annullate eliminate dopo 30 giorni; quelle fallite restano per diagnosi/reinvio. Il payload contiene il testo della nota/commento: limitare l'accesso ai superadmin.
Il server deve eseguire Laravel schedule:run ogni minuto. Il comando centro:integration-webhooks usa la pianificazione esistente e non richiede un ulteriore servizio esterno. I batch di invio sono limitati per non occupare il server; il resto rimane in attesa.

## Esempio cURL

```bash
curl --fail-with-body --header "Authorization: Bearer $CENTRO_TOKEN" --header "Accept: application/json" https://centro.lu3g.com/api/v1/me

curl --fail-with-body --request POST --header "Authorization: Bearer $CENTRO_TOKEN" --header "Content-Type: application/json" --data '{"title":"Revisione sito","description":"Note dal sito","external_ref":"site:INSTALLAZIONE"}' https://centro.lu3g.com/api/v1/tasks
```

## Checklist plugin

1. Salva URL/token solo lato server; prova /me.
2. Conserva ID e riferimenti esterni in modo persistente.
3. Crea task, note e commenti con idempotenza; salva localmente prima della rete.
4. Aggiorna le note con version; gestisci 409 esplicitamente.
5. Registra il webhook, verifica la firma comprensiva del timestamp e deduplica gli eventi.
6. Non generare richieste di ritorno applicando eventi del Centro.
7. Recupera periodicamente lo stato completo delle sottoattività.
8. Gestisci token revocato/scaduto e riconnessione senza ricreare doppioni.
