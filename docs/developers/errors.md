## Errori JSON

Gli errori API contengono `error.code`, `error.message` e `error.fields`. Per gli errori di validazione, `fields` associa i nomi dei parametri ai relativi messaggi.

| HTTP | Significato | Azione |
| --- | --- | --- |
| 401 | Token assente, scaduto o revocato | Verifica la credenziale |
| 403 | Scope mancante o proprietario non autorizzato | Verifica autorizzazioni |
| 404 | Risorsa inesistente o fuori ambito | Verifica ID e ambiti |
| 409 | Chiave già usata con dati diversi o relazione incompatibile | Usa una nuova chiave per una nuova operazione; controlla le relazioni |
| 412 | La risorsa è cambiata dopo la lettura | Rileggi e confronta i dati prima di riprovare |
| 422 | Parametri non validi | Correggi i campi segnalati |
| 428 | Manca If-Match | Usa l'ETag restituito dal dettaglio |
| 429 | Troppe richieste | Rispetta Retry-After |
| 500 | Errore del servizio | Riprova con attesa progressiva |

## Limiti

Sono consentite 60 richieste al minuto per collegamento e 120 per minuto per IP. I risultati paginati sono limitati a 100 elementi per risposta. Usa attese progressive con una variazione casuale ed evita tentativi simultanei ripetuti.

Le risposte autenticate usano `Cache-Control: no-store`. Non archiviare credenziali nei log delle richieste.

Riprova una creazione con lo stesso JSON e la stessa Idempotency-Key: non genera duplicati. Dopo un errore di rete su PATCH rileggi la risorsa: la modifica potrebbe essere già stata salvata. Non sostituire automaticamente If-Match con la versione nuova senza verificare i dati.
