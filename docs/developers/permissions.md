## Scope e ambiti

Uno scope autorizza un'operazione; un ambito stabilisce **su quali dati** può essere eseguita. Sono necessari entrambi. Il ruolo superadmin del proprietario non concede automaticamente visibilità globale al token.

| Scope | Ambito |
| --- | --- |
| clients:read | Clienti selezionati o tutti |
| projects:read | Progetti selezionati o tutti |
| tasks:read | Attività dei progetti autorizzati |
| users:read | Persone selezionate o tutte |
| absences:read | Assenze approvate delle persone autorizzate |
| availability:read | Disponibilità e smart working delle persone autorizzate |
| services:read | Catalogo dei servizi |
| webhooks:write | Sottoscrizioni del collegamento; serve anche lo scope di lettura della risorsa |

## Isolamento

Un ambito vuoto non espone alcuna risorsa. Un ID collegato nella risposta non conferisce accesso alla sua scheda: per leggere un cliente collegato a un progetto serve anche il relativo scope e ambito clienti.

L'opzione tutti comprende anche le nuove risorse create in futuro. Per accessi strettamente delimitati usa una selezione esplicita.

## Dati esclusi

Non sono esposti password, casseforti, documenti personali, informazioni bancarie, motivazioni mediche e note delle assenze. Gli account utente espongono soltanto ID, nome, email di lavoro e data di aggiornamento.
