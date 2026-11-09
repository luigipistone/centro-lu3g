## Leggere tutti i risultati

Gli elenchi sono paginati: `page` parte da 1 e `per_page` accetta da 1 a 100, con valore predefinito 50. Non c'è un limite complessivo al numero di risorse leggibili.

Continua aumentando `page` finché `meta.has_more` è false. `meta.total` conta i risultati autorizzati che soddisfano i filtri, non soltanto quelli nella pagina.

## Ricerca

`search` cerca per nome o titolo prima della paginazione. Gli altri filtri disponibili sono indicati nella pagina di ogni risorsa. Ripeti gli stessi filtri nelle richieste delle pagine successive.

## Aggiornamenti

`updated_since` usa un confronto inclusivo. Invia data, ora e offset ISO 8601; deduplica localmente per ID. L'elenco non è uno snapshot: se i dati cambiano durante la lettura, effettua periodicamente una riconciliazione completa.

L'assenza da una risposta non prova una cancellazione: controlla filtri e ambiti. L'endpoint non fornisce ancora un registro delle eliminazioni.
