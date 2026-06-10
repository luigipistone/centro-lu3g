# Centro LU3G

Migrazione di `luigipistone/web-agency-buddy` da Lovable/React/Supabase a Laravel, Vue/Inertia e MySQL per Plesk.

Questa prima base contiene:

- Laravel con autenticazione Breeze/Inertia/Vue.
- Utenti UUID, profili e ruoli applicativi.
- Migrazioni MySQL per clienti, servizi, progetti, task, notifiche, documenti, abbonamenti, impostazioni, email e backup.
- Dashboard e pagine indice Vue iniziali collegate al database.
- Documentazione Plesk in `docs/deploy-plesk.md`.

Le funzionalita Supabase avanzate e le schermate Lovable originali sono documentate in `docs/source-inventory.md` e vanno convertite progressivamente in controller, job e componenti Vue.

## Sviluppo locale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```
