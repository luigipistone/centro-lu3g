## Scope e ambiti

Uno scope autorizza un'operazione; un ambito stabilisce **su quali dati** può essere eseguita. Sono necessari entrambi. Il ruolo superadmin del proprietario non concede automaticamente visibilità globale al token.

| Scope | Ambito |
| --- | --- |
| clients:read | Clienti selezionati o tutti |
| projects:read | Progetti selezionati o tutti |
| tasks:read | Attività dei progetti autorizzati |
| comments:read | Commenti delle attività dei progetti autorizzati |
| clients:write | Creare e modificare clienti; richiede anche clients:read |
| projects:write | Creare e modificare progetti; richiede anche projects:read |
| tasks:write | Creare e modificare task e sottoattività; richiede anche tasks:read |
| comments:write | Aggiungere commenti; richiede comments:read e tasks:read |
| users:read | Persone selezionate o tutte, inclusa data di nascita |
| holidays:read | Festivita configurate nelle impostazioni aziendali |
| absences:read | Assenze approvate delle persone autorizzate, incluso `type` (ferie, permesso, malattia, ritardo, recupero, altro); esclusi note, motivazioni e allegati |
| availability:read | Disponibilità e smart working delle persone autorizzate |
| services:read | Catalogo dei servizi |
| webhooks:write | Sottoscrizioni del collegamento; serve anche lo scope di lettura della risorsa |

## Isolamento

Un ambito vuoto non espone alcuna risorsa. Un ID collegato nella risposta non conferisce accesso alla sua scheda: per leggere un cliente collegato a un progetto serve anche il relativo scope e ambito clienti.

L'opzione tutti comprende anche le nuove risorse create in futuro. Per accessi strettamente delimitati usa una selezione esplicita.

Le scritture non vengono abilitate automaticamente sui collegamenti esistenti. Per i nuovi collegamenti le caselle di scrittura sono disattivate inizialmente. Il superadmin può abilitarle nelle impostazioni API. I clienti e progetti creati dalla connessione vengono aggiunti al suo ambito selettivo, senza estenderlo ad altre risorse.

Assegnatari, osservatori e partecipanti devono essere utenti attivi nell'ambito persone del collegamento. I clienti e progetti collegati devono essere autorizzati. Per assegnare un servizio occorre anche services:read e un servizio attivo. Gli aggiornamenti sono attribuiti al proprietario del collegamento, senza possibilità di impersonare un'altra persona.

## Dati esclusi

Non sono esposti password, casseforti, documenti personali, informazioni bancarie, motivazioni mediche e note delle assenze. Gli account utente espongono soltanto ID, nome, email di lavoro e data di aggiornamento.
