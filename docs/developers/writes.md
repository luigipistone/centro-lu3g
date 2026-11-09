## Operazioni disponibili

| Metodo | Percorso | Operazione |
| --- | --- | --- |
| POST | /api/v1/clients | Crea cliente |
| PATCH | /api/v1/clients/{id} | Modifica o archivia cliente |
| POST | /api/v1/projects | Crea progetto |
| PATCH | /api/v1/projects/{id} | Modifica o archivia progetto |
| POST | /api/v1/tasks | Crea task, continuativa, meeting o sottoattività |
| PATCH | /api/v1/tasks/{id} | Modifica attività |
| POST | /api/v1/tasks/{id}/comments | Aggiunge commento |

Usa una connessione di tipo `general`, token Bearer e `Content-Type: application/json`. Le risposte contengono `data`. Creazione: 201; modifica: 200. Gli ID sono UUID generati dal Centro. Le modifiche applicano le regole del Centro e le notifiche previste per task, progetti e commenti.

Utenti, assenze, disponibilità e catalogo servizi restano in sola lettura. Non sono pubblicate cancellazioni definitive di dati aziendali, approvazioni di assenze, modifica di commenti, upload di allegati o modifiche delle dipendenze. L'eliminazione di una sottoscrizione webhook resta disponibile. Page Notes è un contratto separato: non usarne gli endpoint per aggirare questi limiti.

## Creare senza duplicati

Ogni POST di creazione richiede `Idempotency-Key`, una stringa non vuota di massimo 128 caratteri. Genera un UUID per ogni nuova operazione e conservalo fino al risultato. La chiave è unica per collegamento, anche fra risorse diverse.

```bash
curl --fail-with-body -X POST 'https://centro.lu3g.com/api/v1/clients' \
  -H "Authorization: Bearer $CENTRO_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: 600c8231-bf92-4c75-a362-8da0790f8559' \
  -d '{"name":"CLIENTE ESEMPIO","legal_name":"Cliente Esempio Srl","active":true}'
```

Stessa chiave e stesso contenuto: 200, header `Idempotency-Replayed: true`, dettaglio corrente della risorsa senza nuove scritture o notifiche. Stessa chiave con contenuto diverso: 409. Una richiesta fallita prima del salvataggio non consuma la chiave. Se la risorsa non è più accessibile, il ritentativo restituisce 404 e non la ricrea. Le chiavi non scadono finché il collegamento esiste.

## Modificare senza sovrascrivere

Leggi `GET /api/v1/{risorsa}/{id}` e conserva l'header `ETag`, incluse le virgolette. Invia una PATCH con `If-Match` uguale a quel valore. Manca l'header: 428. La versione è cambiata: 412 e nessuna modifica. Anche la risposta alla scrittura restituisce l'ETag corrente.

```bash
curl -i 'https://centro.lu3g.com/api/v1/clients/UUID_CLIENTE' \
  -H "Authorization: Bearer $CENTRO_TOKEN"

curl --fail-with-body -X PATCH 'https://centro.lu3g.com/api/v1/clients/UUID_CLIENTE' \
  -H "Authorization: Bearer $CENTRO_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'If-Match: "VALORE_ETAG_DALLA_LETTURA"' \
  -d '{"phone":"0212345678"}'
```

I campi omessi restano invariati. `null` svuota soltanto campi facoltativi; non può svuotare nome, titolo o valori obbligatori. `[]` svuota una lista di persone. Campi sconosciuti e corpo vuoto restituiscono 422. Non inviare l'intera risposta GET: contiene campi non scrivibili.

## Clienti

Obbligatorio in creazione: `name` (massimo 255 caratteri). `active` è true per impostazione predefinita. `legal_name` è la ragione sociale. Gli altri campi scrivibili sono elencati nella [scheda Clienti](/developers/clients). Email deve essere valida; i campi anagrafici sono stringhe fino a 255 caratteri. `active:false` archivia, `active:true` riattiva senza eliminare progetti o task. L'importazione Report può successivamente aggiornare i campi gestiti dalla sincronizzazione: le API non la disabilitano.

## Progetti

Obbligatorio: `name` (massimo 255 caratteri). Predefiniti: `status:active`, `color:#0B6EF3`. Stati: `active`, `on_hold`, `completed`, `archived`. `user_ids` sostituisce i partecipanti; se omesso in PATCH li conserva. `client_id` e `service_id` sono facoltativi e soggetti agli ambiti del collegamento. Non si può cambiare o rimuovere il cliente di un progetto che contiene task (409).

`description` accetta testo semplice fino a 50.000 caratteri, non HTML. Il Centro lo converte in HTML sicuro, mantenendo gli a capo. In lettura viene restituito HTML: sanificalo prima di visualizzarlo in un'applicazione esterna.

## Task e sottoattività

Obbligatorio: `title` (massimo 255 caratteri). Predefiniti: `task_type:task`, `status:todo`, `priority:medium`, ricorrenza disabilitata. Tipi: `task`, `project`, `ongoing`, `meeting`. I tipi `task` e `project` richiedono un progetto. Per attività senza progetto occorre l'ambito tutti i progetti.

Stati: `todo`, `in_progress`, `in_review`, `done`. Priorità: `low`, `medium`, `high`, `urgent`. Date `start_date` e `due_date` in YYYY-MM-DD; fine non precedente all'inizio. `due_time` in HH:mm, fuso Europe/Rome. Le regole sulle festività del Centro restano applicate. Le date non vengono spostate automaticamente dalla PATCH: invia entrambi gli estremi per mantenere la durata di una task.

Il cliente viene derivato dal progetto se non specificato. Un cliente diverso deve essere un sottocliente diretto del cliente del progetto, comunque autorizzato. Non si possono creare o spostare task in progetti archiviati. `assignee_ids` e `follower_ids` sostituiscono solo la lista inviata; la lista omessa resta invariata. Non viene assegnato automaticamente un utente scelto dal software esterno.

Per una sottoattività invia `parent_task_id` nel POST: progetto e cliente vengono ereditati dal genitore, salvo valori espliciti che devono coincidere. Il genitore deve essere accessibile e non può essere cambiato successivamente. Una task con sottoattività non può essere spostata ad altro progetto/cliente via API. Non si può completare una task con dipendenze o sottoattività aperte.

Ricorrenze: `recurring_enabled`, `recurring_interval_value` (1–365), `recurring_interval_unit` (`week`, `month`), `recurring_mode` (`fixed`, `relative`), `recurring_weekday` (1–7), `recurring_month_day` (1–31). Si applica lo stesso comportamento della schermata Task. La descrizione segue le stesse regole dei progetti.

## Commenti

Invia `{"body":"Testo del commento"}` a `POST /api/v1/tasks/{id}/comments` con Idempotency-Key. Testo semplice, massimo 20.000 caratteri. Sono richiesti `comments:write`, `comments:read` e `tasks:read`. L'autore è il proprietario del collegamento. Una scritta `@Nome` non equivale a una menzione strutturata. I webhook `comments.created`, `comments.updated`, `comments.removed` seguono la stessa logica delle altre risorse.

## Verifica dell'integrazione

Prova prima su record di test autorizzati: crea, ritenta con la stessa chiave, verifica che l'ID sia invariato, modifica con ETag, riprova con ETag precedente e attendi 412. Verifica che un ID fuori ambito non sia leggibile/modificabile e che revocare lo scope write impedisca nuove scritture. Non usare record reali per provare cancellazioni o completamenti.
