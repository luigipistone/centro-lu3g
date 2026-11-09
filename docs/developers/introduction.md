## Una base comune

Le API del Centro permettono a software autorizzati di leggere le risorse aziendali senza dipendere da uno specifico plugin. L'indirizzo base è `https://centro.lu3g.com/api/v1`.

## Stato della versione

Sono disponibili le API comuni di lettura per clienti, progetti, task, commenti, utenti, servizi, assenze approvate e disponibilità giornaliera con smart working, oltre ai webhook delle risorse. La versione 1.1 aggiunge creazione e modifica di clienti, progetti e task e l'aggiunta di commenti. Consulta [Creare e modificare](/developers/writes) prima di abilitare le scritture.

Page Notes conserva un contratto separato, limitato alle proprie attività. Non è un prerequisito delle API comuni.

## Prima richiesta

1. Il superadmin crea un collegamento nelle impostazioni API.
2. Seleziona gli scope e gli ambiti dei dati da autorizzare.
3. Conserva il token sul backend del software che si collega.
4. Verifica il token con `GET /api/v1/me`, poi consulta una risorsa autorizzata.

## Formato

Le risposte sono JSON UTF-8. Gli elenchi contengono `data` e `meta`; i dettagli contengono `data`. I campi possono essere null quando non valorizzati. Non assumere che un elenco vuoto significhi che nel Centro non esistano dati: potrebbe dipendere dai permessi.
