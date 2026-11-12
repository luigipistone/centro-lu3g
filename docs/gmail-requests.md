# Gmail - Richieste clienti

## Configurazione Google

1. Nel progetto Google Cloud di Il Centro abilita Gmail API.
2. Usa un client OAuth di tipo applicazione web. Puoi riutilizzare quello esistente aggiungendo, senza rimuovere gli altri URI, `https://centro.lu3g.com/requests/gmail/callback` agli URI di reindirizzamento autorizzati.
3. Configura il consenso per `https://www.googleapis.com/auth/gmail.readonly`. Per gli account della stessa organizzazione Workspace usa, quando disponibile, un'app interna. Per app esterne valgono i requisiti Google relativi agli scope Gmail e alla pubblicazione/verifica; in test l'accesso e la durata dei token possono essere limitati.
4. Il superadmin inserisce Client ID e Client secret in Impostazioni > Integrazioni > Gmail. Il segreto viene cifrato e non restituito al browser.
5. Ogni manager crea in Gmail un'etichetta dedicata, per esempio `Invia al Centro`. Il Centro non crea o modifica etichette: il consenso e' di sola lettura.
6. Ogni manager apre Richieste clienti > La mia casella, collega Google usando la stessa email del proprio profilo Centro, seleziona l'etichetta, attiva il controllo e salva.

Non condividere password Gmail. Ogni casella ha un consenso personale e un token separato. Il collegamento Google Calendar/Drive non viene modificato. Scollegare Gmail nel Centro elimina il token locale ma non revoca l'intero consenso Google, che potrebbe servire anche alle altre integrazioni.

## Flusso

- Il processo pianificato controlla le caselle abilitate circa ogni due minuti; Controlla ora richiede un passaggio al prossimo minuto. Non si tratta di push Gmail e il tempo dipende anche da rete e quantita' di messaggi.
- Applicando l'etichetta vengono importate anche email precedenti. Viene acquisito il contesto della conversazione e vengono controllate le risposte successive, che non sempre ereditano l'etichetta. Non vengono importate conversazioni estranee.
- Togliere l'etichetta o cancellare un'email non cancella le richieste o le task del Centro. Per fermare il controllo disattiva o scollega la casella.
- Le richieste sono condivise fra superadmin e manager autorizzati con `requests.manage`. La configurazione della casella resta personale. I dipendenti non accedono alle email originali.
- Titolo e descrizione sono ricavati dall'email, non da un modello AI. Cliente proposto solo con corrispondenza email univoca fra clienti attivi e referenti; progetto solo se univoco. Nessuna data, urgenza o interpretazione della richiesta viene inventata. Priorita' iniziale Media, assegnatario iniziale il revisore: da confermare.
- Prendi in carico, verifica, modifica la proposta e approva. Puoi creare una task, collegare una task esistente oppure scartare la richiesta. La creazione riutilizza i controlli e le notifiche delle task normali, incluso il progetto obbligatorio.
- La presa in carico impedisce conferme concorrenti; se arrivano nuove email durante la revisione, la conferma viene respinta finche' non si ricarica la richiesta.
- Gli ID Gmail rendono i tentativi ripetuti idempotenti. Lo stesso Message-ID fra caselle genera un avviso di possibile duplicato; non e' una deduplicazione semantica di inoltri riscritti.
- Una nuova risposta segnala aggiornamenti, ma non cambia automaticamente una task gia' approvata. Controllare anche il filtro Elaborate/Tutte per i seguiti delle richieste gia' convertite.
- Gli allegati sono consultabili/scaricabili dalla richiesta attraverso Gmail; vengono conservati i metadati, non una copia permanente dei file. Non vengono trasferiti automaticamente su Drive o nella task. Il download richiede che l'email esista ancora e la casella sia collegata.

## Esercizio e sicurezza

Comando: `php artisan centro:gmail-requests-sync`, invocato dallo scheduler Laravel ogni minuto con protezione da sovrapposizioni e controllo per casella. Per funzionare richiede il normale cron `schedule:run`.

I token sono cifrati con APP_KEY. OAuth usa state monouso con scadenza e verifica dell'account. Le email sono mostrate come testo, mai HTML eseguibile; nessun contenuto remoto viene caricato. Gli allegati sono serviti come download autenticato, non inline. Non vengono inviate email, modificati messaggi Gmail o creati task in background. Il contenuto importato e' conservato nel database del Centro: includerlo nelle politiche aziendali di accesso, backup e conservazione.

Errori temporanei conservano quanto importato e consentono di riprovare senza ricrearlo. Stato ed eventuale errore sono visibili nel pannello della casella. I dati di test usano Gmail simulato: la prova reale richiede il consenso di almeno un manager.

Riferimenti: https://developers.google.com/workspace/gmail/api/auth/scopes e https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/list
