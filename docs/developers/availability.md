## Pianificazione giornaliera

`GET /api/v1/availability?date=2026-11-09`

Richiede lo scope `availability:read` e l'ambito persone. Restituisce solo gli account attivi non ospiti autorizzati, con paginazione `page` e `per_page` da 1 a 100.

## Campi

| Campo | Significato |
| --- | --- |
| user_id | Persona autorizzata |
| date | Giorno richiesto, Europe/Rome |
| planned_minutes | Minuti lavorativi previsti, prima delle assenze |
| status | scheduled, absent, partially_absent oppure not_scheduled |
| work_mode | office, remote, travel oppure null |
| absence_intervals | Intervalli start/end HH:mm dei permessi parziali approvati |

## Regole

Festività, giorni non lavorativi e orari individuali determinano i minuti previsti. Un'assenza completa prevale sullo smart working. Una trasferta prevale sullo smart working ricorrente; un permesso parziale conserva la modalità di lavoro pianificata. Le causali che non riducono la presenza non sono conteggiate come assenza.

Gli intervalli possono sovrapporsi: non sommarli senza prima unirli. `scheduled` significa pianificato, non effettivamente presente. Motivazioni e informazioni mediche non vengono esposte.
