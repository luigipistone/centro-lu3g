# Teamleader: collegamento, simulazione e sincronizzazione

Disponibile ai soli superadmin in Impostazioni > Integrazioni > Teamleader.
Il comando Esegui simulazione non crea, aggiorna o elimina task.
La sincronizzazione automatica e' distinta e abilitabile con il relativo switch.
Le chiamate remote sono sempre di sola lettura: nessuna modifica viene inviata a Teamleader.

## Configurazione

1. Aprire https://marketplace.focus.teamleader.eu/build con l'account Teamleader.
2. Creare un'integrazione OAuth per Il Centro, abilitando l'accesso ai meeting
   e agli utenti necessario per meetings.list e users.info.
3. Registrare esattamente l'URI visualizzato nel pannello. In produzione:
   https://centro.lu3g.com/settings/teamleader/callback
4. Inserire Client ID e Client secret nel pannello, salvare e collegare Teamleader.
5. Autorizzare l'accesso, scegliere il periodo e premere Esegui simulazione.
6. Per importare, scegliere Importa dal e Orizzonte futuro, abilitare la
   sincronizzazione automatica e salvare. Sincronizza ora richiede un controllo
   al prossimo passaggio dello scheduler (normalmente entro un minuto).

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
I risultati della simulazione restano solo nel browser, non sono un archivio persistente.

## Sincronizzazione automatica

Lo scheduler esegue centro:teamleader-sync ogni minuto; il comando interroga
Teamleader ogni 5 minuti, oppure quando viene richiesto un controllo manuale.
Non dipende dall'apertura del browser e non usa webhook. Le cancellazioni non sono
quindi istantanee. Il periodo iniziale resta fisso; l'orizzonte futuro avanza.

Identita' persistente: account Teamleader + ID meeting, con indice univoco.
Tutte le pagine vengono lette prima di scrivere. Per un meeting importato che
non compare nell'elenco viene chiamato meetings.info: se esiste viene aggiornato
anche quando spostato fuori periodo; soltanto HTTP 404 conferma la rimozione.
Errori di rete, 403, 429, 5xx o risposte incomplete interrompono il ciclo senza
applicare modifiche parziali. Il limite di tempo di sicurezza interrompe l'intera
lettura, non tronca l'elenco. Un cambio di account richiede revisione esplicita,
non cancella i meeting appartenenti al precedente account.

Titolo, descrizione, date, durata e assegnatari seguono Teamleader.
Cliente (precompilato alla creazione se riconosciuto), servizio, priorita' e stato
sono modificabili nel Centro. Lo stato iniziale segue open/done; una chiusura su
Teamleader imposta Fatto anche nel Centro, anche se gli altri dati sono invariati.
Uno stato open su Teamleader non riapre mai un meeting completato nel Centro.
Cliente, servizio e priorita' restano locali. La modifica dei dati sincronizzati
e l'eliminazione manuale sono bloccate anche nel backend.

La cancellazione imposta teamleader_cancelled_at: l'elemento sparisce dagli
elenchi attivi, dal calendario e dalle liste API, conservando storico e allegati.
Se ricompare con lo stesso ID viene ripristinato senza duplicarlo. Il primo
caricamento non invia notifiche in massa; le variazioni successive notificano
assegnatari e follower tramite un outbox persistente con retry.

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
