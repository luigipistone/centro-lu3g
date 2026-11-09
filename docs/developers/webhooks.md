## Sottoscrizione

`POST /api/v1/webhooks` richiede `webhooks:write` e lo scope di lettura di ogni risorsa richiesta. Il callback deve essere HTTPS, sul dominio configurato nel collegamento, porta 443. Indirizzi privati e redirect non sono consentiti.

```json
{"url":"https://tuo-software.it/centro/events","events":["tasks.updated","absences.created","absences.removed"]}
```

La risposta 201 contiene `data.id` e `data.secret`. Conserva il segreto nel backend: viene mostrato una sola volta. La registrazione inizializza lo stato corrente senza inviare tutto lo storico. Un callback già registrato per lo stesso collegamento restituisce 409.

## Eventi

Le risorse `clients`, `projects`, `tasks`, `users`, `absences`, `services` supportano i suffissi `.created`, `.updated`, `.removed`.

Il rilevamento avviene ogni minuto confrontando i dati esposti. Non è un registro di ogni operazione: modifiche intermedie tra due scansioni possono essere accorpate. `removed` indica che una risorsa non è più leggibile nell'elenco (per esempio assenza revocata o utente disattivato), non necessariamente una cancellazione fisica.

Le modifiche agli orari di lavoro, ai giorni smart ricorrenti o alle festività non generano ancora eventi di disponibilità: per questi dati interroga `/availability` periodicamente.

## Payload

```json
{"id":"uuid-evento","event":"tasks.updated","observed_at":"2026-11-09T10:00:00+01:00","data":{"id":"uuid-task"}}
```

Il payload contiene soltanto l'ID della risorsa. Recupera lo stato aggiornato tramite GET. Non assumere ordine di consegna; deduplica per ID evento. Le autorizzazioni vengono ricontrollate prima dell'invio, anche per i tentativi successivi.

## Verifica della firma

Header: `X-Centro-Timestamp`, `X-Centro-Signature`, `X-Centro-Event-ID`.

Calcola HMAC-SHA256 del testo `timestamp + "." + corpo_originale` usando il segreto. La firma ricevuta è `sha256=` seguita dal risultato esadecimale. Confrontala a tempo costante e rifiuta timestamp con uno scarto superiore a 5 minuti. Verifica il corpo originale prima di decodificare il JSON.

```php
$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300
    || !hash_equals($expected, $receivedSignature)) {
    http_response_code(401);
    exit;
}
```

## Consegna e tentativi

Rispondi rapidamente con un codice 2xx dopo aver accodato l'evento nel tuo sistema. Sono previsti fino a 5 tentativi: il primo disponibile, poi attese di 1, 5, 30 e 120 minuti. Il timeout è 8 secondi. Il worker elabora fino a 10 consegne comuni per ciclo: le altre rimangono in coda.

I tentativi falliti sono visibili nelle impostazioni e possono essere rimessi in coda dal superadmin. Revoca, scadenza o rimozione dei permessi impediscono ulteriori consegne. Mantieni comunque una riconciliazione periodica: dopo l'esaurimento dei tentativi è necessario intervenire.

## Gestione

`GET /api/v1/webhooks` elenca le sottoscrizioni del collegamento senza segreti. `DELETE /api/v1/webhooks/{id}` elimina sottoscrizione e consegne associate e restituisce 204. Per modificare eventi o callback elimina la sottoscrizione e registrane una nuova.

Il contratto Page Notes resta separato e non è necessario per usare questi webhook.
