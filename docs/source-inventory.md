# Inventario sorgente Lovable

Repo sorgente: `luigipistone/web-agency-buddy`.

## Stack originale

- Vite
- React
- TypeScript
- shadcn/ui
- React Query
- Supabase Auth
- Supabase Postgres/RLS/functions/storage

## Aree applicative individuate

- Dashboard
- Clienti e contatti
- Servizi cliente
- Progetti e follower
- Task, assegnatari, follower, commenti, storico, ricorrenze
- Calendario
- Utenti, profili, ruoli
- Note utente
- Notifiche
- Billing: documenti, righe, numerazione, pagamenti, abbonamenti, impostazioni aziendali
- Email documenti
- Backup/restore
- PDF documenti
- XML fattura elettronica

## Edge Functions Supabase da convertire in Laravel

- `create-user`
- `update-user-password`
- `generate-document-pdf`
- `generate-fattura-xml`
- `send-document-email`
- `process-subscriptions`
- `run-backup`
- `restore-backup`
