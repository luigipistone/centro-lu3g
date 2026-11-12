# Teamleader: collegamento e simulazione

Disponibile ai soli superadmin in Impostazioni > Integrazioni > Teamleader.
Questa fase non crea, aggiorna o elimina task. Non registra webhook e non avvia
sincronizzazioni automatiche. Le richieste POST verso meetings.list/users.info
sono letture secondo il contratto API Teamleader.

## Configurazione

1. Aprire https://marketplace.focus.teamleader.eu/build con l'account Teamleader.
2. Creare un'integrazione OAuth per Il Centro, abilitando l'accesso ai meeting
   e agli utenti necessario per meetings.list e users.info.
3. Registrare esattamente l'URI visualizzato nel pannello. In produzione:
   https://centro.lu3g.com/settings/teamleader/callback
4. Inserire Client ID e Client secret nel pannello, salvare e collegare Teamleader.
5. Autorizzare l'accesso, scegliere il periodo e premere Esegui simulazione.

I nomi dei permessi nel portale vanno verificati nella schermata di creazione:
non sono imposti parametri scope inventati nell'URL OAuth.

## Lettura e associazioni

Tutte le pagine del periodo vengono richieste, 20 meeting per richiesta.
Il numero 20 e' una dimensione di pagina, non un limite totale.
Gli utenti partecipanti vengono risolti tramite users.info; si confronta l'email
normalizzata con quella degli utenti attivi del Centro. Nessuna associazione
per creatore, nome o contatto esterno; associazioni ambigue restano da verificare.
Il cliente viene riconosciuto attraverso report_client_links.teamleader_id e
source_type, mai per nome. Le associazioni assenti sono mostrate nell'anteprima.
Non viene ancora eseguita deduplicazione con i meeting provenienti da Google:
prima di attivare una futura importazione serve stabilire la fonte autorevole.

Una lettura interrotta e' indicata come parziale, non come simulazione completata.
La paginazione Teamleader non garantisce uno snapshot: duplicati durante la
lettura interrompono la simulazione e richiedono una nuova lettura.
I risultati restano solo nel browser, non sono un archivio persistente.

## Sicurezza

Segreti e token sono cifrati con Laravel Crypt, mai restituiti al browser.
OAuth state e' monouso, scade dopo 10 minuti ed e' legato alla configurazione.
Un lock condiviso serializza lettura, rinnovo token e modifiche del collegamento.
I refresh token Teamleader sono rotanti: vengono salvati insieme all'access token.
Nessun retry automatico dello scambio token. Dopo un rinnovo ambiguo o interrotto
potrebbe essere necessario ricollegare il servizio.
Scollega rimuove i token locali; per revocare anche il consenso remoto rimuovere
l'integrazione dal proprio account Teamleader.

Riferimento: https://github.com/teamleadercrm/api/blob/master/apiary.apib
