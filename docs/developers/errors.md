## Errori JSON

Gli errori API contengono `error.code`, `error.message` e `error.fields`. Per gli errori di validazione, `fields` associa i nomi dei parametri ai relativi messaggi.

| HTTP | Significato | Azione |
| --- | --- | --- |
| 401 | Token assente, scaduto o revocato | Verifica la credenziale |
| 403 | Scope mancante o proprietario non autorizzato | Verifica autorizzazioni |
| 404 | Risorsa inesistente o fuori ambito | Verifica ID e ambiti |
| 422 | Parametri non validi | Correggi i campi segnalati |
| 429 | Troppe richieste | Rispetta Retry-After |
| 500 | Errore del servizio | Riprova con attesa progressiva |

## Limiti

Sono consentite 60 richieste al minuto per collegamento e 120 per minuto per IP. I risultati paginati sono limitati a 100 elementi per risposta. Usa attese progressive con una variazione casuale ed evita tentativi simultanei ripetuti.

Le risposte autenticate usano `Cache-Control: no-store`. Non archiviare credenziali nei log delle richieste.
