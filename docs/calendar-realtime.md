# Calendario in tempo reale

Reverb mantiene una connessione WebSocket privata con i calendari aperti.
Il server invia un'invalidazione senza dati personali dopo le scritture
confermate su task, assegnazioni, follower, dipendenze, commenti e relativi
clienti/progetti/servizi. Un solo avviso per richiesta o job. Sono comprese
le scritture provenienti dalle integrazioni Google Calendar e Teamleader.

Il browser rilegge /calendar/live-data con sessione, permessi e visibilita
del calendario originale. Non ricarica la pagina, non cambia filtri,
posizione di scorrimento o bozze nel drawer. Aggiornamenti durante drag o
salvataggio vengono differiti; risposte HTTP obsolete vengono scartate.
Riconnessione, ritorno alla scheda e ritorno online recuperano lo stato completo.
Non e un sistema di blocco delle modifiche concorrenti alla stessa task.

## Attivazione produzione

Il codice e disattivato per default: occorrono servizio persistente e proxy
WebSocket HTTPS. Da root, dopo il deploy:

```sh
bash /var/www/vhosts/lu3g.com/centro.lu3g.com/scripts/setup-calendar-realtime.sh
```

Lo script preserva e salva la configurazione nginx esistente, aggiunge solo
/app/, ascolta sulla porta interna 8085, genera credenziali senza stamparle
e installa centro-reverb.service. Non apre una porta pubblica aggiuntiva.
Controllare prima che la porta 8085 sia libera. Non esporre /apps/ al pubblico.

Il deploy esegue reverb:restart; systemd riavvia il processo con il nuovo codice.
Log: journalctl -u centro-reverb. Errori di invio: storage/logs/laravel.log.
Un guasto al canale non impedisce il salvataggio delle task. Al ripristino il
browser recupera i dati alla riconnessione; non esiste una coda durabile di eventi.

## Verifica

Aprire due sessioni autorizzate: creare, modificare date e stato, trascinare
una task su piu giorni, eliminarla. Verificare che il secondo calendario
si aggiorni mantenendo i propri filtri e scroll. Provare anche riconnessione,
drawer con testo non salvato, utente senza permesso e sessione scaduta.

Disattivazione: CALENDAR_REALTIME_ENABLED=false, artisan config:cache;
riaprire il calendario. Nessun dato viene migrato o cancellato.
