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
- Togliere l'etichetta dall'intera conversazione interrompe il monitoraggio dal controllo successivo, senza cancellare richieste, messaggi importati o task. Se almeno un messaggio conserva l'etichetta, la conversazione resta monitorata. Riapplicando l'etichetta si riprende la stessa richiesta e si acquisiscono anche le risposte arrivate durante la pausa, senza duplicare quelle gia' importate. Per fermare tutte le conversazioni disattiva o scollega la casella.
- Le richieste sono condivise fra superadmin e manager autorizzati con `requests.manage`. La configurazione della casella resta personale. I dipendenti non accedono alle email originali.
- Prendi in carico e seleziona uno o piu' messaggi ancora da verificare. Viene preselezionato l'ultimo messaggio non proveniente da un utente Centro (in alternativa, l'ultimo disponibile). Lo storico elaborato resta consultabile ma non selezionabile nuovamente.
- Prepara usa soltanto i messaggi selezionati, senza AI: titolo e corpo originali, modificabili prima della conferma. Eventuali citazioni gia' incluse nel corpo di un'email restano visibili e modificabili: non vengono eliminate automaticamente per evitare perdite di contenuto.
- Il cliente viene cercato fra gli attivi, in ordine: email esatta del cliente/referente, dominio del mittente, domini dei link citati. Si confrontano anche siti nelle password condivise accessibili al revisore, leggendo esclusivamente client_id e URL. Sono escluse tutte le casseforti personali, anche proprie; le credenziali non vengono lette. Domini generici/condivisi noti sono esclusi dal confronto per dominio. Non si usano somiglianze di nome o domini parziali. Più candidati allo stesso livello richiedono una scelta manuale; il motivo viene mostrato. Questi suggerimenti sono calcolati al momento e non salvati per altri revisori.
- Progetto proposto solo se univoco. Nessuna data, urgenza o interpretazione viene inventata. Priorita' iniziale Media, assegnatario iniziale il revisore: da confermare.
- Approva crea una task con i controlli e le notifiche normali, incluso il progetto obbligatorio. In alternativa aggiungi i messaggi selezionati come commento a una task esistente, senza cambiarne descrizione, stato o assegnatari, oppure ignora i soli messaggi selezionati. Gli altri restano da verificare.
- La presa in carico impedisce conferme concorrenti; se arrivano nuove email durante la revisione, la conferma viene respinta finche' non si ricarica la richiesta.
- Gli ID Gmail rendono i tentativi ripetuti idempotenti. Lo stesso Message-ID fra caselle genera un avviso di possibile duplicato; non e' una deduplicazione semantica di inoltri riscritti.
- Una nuova risposta ricompare nel filtro Da verificare, senza cambiare automaticamente le task. Ogni messaggio conserva esito, revisore, data e task collegata; una conversazione puo' generare piu' task. Le conferme incrementano la versione e sono atomiche: tentativi ripetuti o selezioni gia' elaborate vengono respinti.
- Migrazione storico: le vecchie richieste interamente verificate vengono marcate elaborate. Se avevano aggiornamenti non verificati non viene indovinato il confine fra vecchie e nuove email: i messaggi restano da verificare, mantenendo il collegamento alla vecchia task.
- Gli allegati sono consultabili/scaricabili dalla richiesta attraverso Gmail; vengono conservati i metadati, non una copia permanente dei file. Non vengono trasferiti automaticamente su Drive o nella task. Il download richiede che l'email esista ancora e la casella sia collegata.

## Esercizio e sicurezza

### Proposta AI (facoltativa)

La creazione parte come continuativa, senza progetto; il revisore puo scegliere una task di progetto, per cui il progetto resta obbligatorio. Gli assegnatari usano il selettore con avatar.
Gli elenchi operativi delle email possono produrre sottoattivita nella bozza AI: il revisore puo modificarle, aggiungerle o rimuoverle. Solo la conferma crea madre e figlie nella stessa transazione, usando il flusso ordinario delle sottoattivita. Le figlie ereditano cliente, servizio, tipo, priorita e assegnatari; non ricevono scadenze inventate. Collegare a una task esistente continua ad aggiungere solo un commento.

Con la connessione OpenAI del Centro configurata, il revisore vede **Prepara con AI**. Usa la chiave cifrata e il modello gia' configurati per Agenzia AI, solo nel backend. Il riconoscimento cliente rimane deterministico e separato dall'AI.

- Vengono inviati a OpenAI esclusivamente oggetto e testo dei messaggi selezionati, nell'ordine di ricezione. Nessun altro messaggio del database, elenco clienti, password, allegato o dato della casella viene aggiunto. Eventuali dati personali gia' nel corpo selezionato fanno parte del testo inviato.
- L'AI restituisce titolo, descrizione in testo semplice e dubbi da verificare. Non ha strumenti, accesso web o facolta' di scrittura. Le istruzioni presenti nelle email sono trattate come dati, non comandi.
- Saluti, firme, citazioni ripetute e disclaimer vengono esclusi dalla sintesi secondo le istruzioni al modello. Non e' una garanzia di correttezza semantica: confrontare sempre la bozza con gli originali visibili. I punti non chiari e gli allegati non letti vengono segnalati.
- Il browser non esegue HTML generato. Titolo e descrizione restano modificabili e soltanto **Approva e crea task** crea la task. Preparare non marca messaggi come elaborati. L'aggiunta a task esistenti continua a usare il testo originale come commento.
- Rigenerare chiede conferma prima di sostituire titolo/descrizione; non cambia cliente, progetto o altri campi gia' selezionati. Risposte incomplete, rifiuti ed errori lasciano intatta la bozza corrente. Resta disponibile la preparazione senza AI.
- Responses API con `store: false`, nessun retry automatico. Questo parametro non sostituisce le politiche di conservazione del fornitore. Limite tecnico di 60.000 byte per la selezione, senza troncamento silenzioso; 3.000 token massimi di output e sei tentativi al minuto per revisore. Una sola preparazione simultanea per richiesta.
- I costi stimati sono conteggiati nel budget mensile AI gia' configurato. La tabella `gmail_ai_usage` conserva solo ID, costo stimato e data, senza contenuti email. Una prenotazione preventiva viene rettificata sui token restituiti dal fornitore; se la risposta non arriva resta prudenzialmente conteggiata. Le tariffe stimate sono quelle configurate per Agenzia AI, non un resoconto di fatturazione OpenAI.

Riferimento formato strutturato: https://developers.openai.com/api/docs/guides/structured-outputs

Comando: `php artisan centro:gmail-requests-sync`, invocato dallo scheduler Laravel ogni minuto con protezione da sovrapposizioni e controllo per casella. Per funzionare richiede il normale cron `schedule:run`.

I token sono cifrati con APP_KEY. OAuth usa state monouso con scadenza e verifica dell'account. Le email sono mostrate come testo, mai HTML eseguibile; nessun contenuto remoto viene caricato. Gli allegati sono serviti come download autenticato, non inline. Non vengono inviate email, modificati messaggi Gmail o creati task in background. Il contenuto importato e' conservato nel database del Centro: includerlo nelle politiche aziendali di accesso, backup e conservazione.

Errori temporanei conservano quanto importato e consentono di riprovare senza ricrearlo. Stato ed eventuale errore sono visibili nel pannello della casella. I dati di test usano Gmail simulato: la prova reale richiede il consenso di almeno un manager.

Riferimenti: https://developers.google.com/workspace/gmail/api/auth/scopes e https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/list
