# Deploy Plesk

Applicazione Laravel + Vue/Inertia per `centro.lu3g.com`.

## Requisiti Plesk verificati

- PHP 8.3: `/opt/plesk/php/8.3/bin/php`
- Composer: `/opt/psa/var/modules/composer/composer.phar`
- Database MySQL/MariaDB: `portale_centro`
- Document root consigliata: `/var/www/vhosts/lu3g.com/centro.lu3g.com/public`

## Variabili `.env`

Non committare `.env`. Sul server impostare:

```dotenv
APP_NAME="Centro LU3G"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://centro.lu3g.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=portale_centro
DB_USERNAME=lu3g_usr
DB_PASSWORD=***

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## Comandi deploy

```bash
cd /var/www/vhosts/lu3g.com/centro.lu3g.com
/opt/plesk/php/8.3/bin/php /opt/psa/var/modules/composer/composer.phar install --no-dev --optimize-autoloader
npm ci
npm run build
/opt/plesk/php/8.3/bin/php artisan key:generate --force
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan db:seed --force
/opt/plesk/php/8.3/bin/php artisan storage:link
/opt/plesk/php/8.3/bin/php artisan config:cache
/opt/plesk/php/8.3/bin/php artisan route:cache
/opt/plesk/php/8.3/bin/php artisan view:cache
```

## Deploy automatico da GitHub Actions

Il deploy di produzione e' gestito dal workflow `.github/workflows/deploy-production.yml`.
Ad ogni push su `main`, GitHub entra via SSH sul server e lancia:

```bash
bash /var/www/vhosts/lu3g.com/centro.lu3g.com/scripts/deploy-production.sh
```

Secret richiesti nella repo GitHub:

```text
PLESK_HOST=<ip-server-plesk>
PLESK_USER=<utente-ssh-plesk>
PLESK_SSH_KEY=<chiave privata dedicata al deploy>
```

Lo script usa il repository Plesk in `/var/www/vhosts/lu3g.com/git/centro-lu3g`.
Installa le dipendenze PHP e frontend e compila gli asset Vite in una cartella
temporanea fuori dal sito attivo.
Solo dopo una compilazione riuscita mette brevemente Laravel in manutenzione,
aggiorna il codice e le dipendenze PHP, pubblica gli asset gia' pronti, lancia
le migrazioni e rigenera le cache. Poi riapre il sito. In caso di errore prova
comunque a disattivare la manutenzione e rimuove la cartella temporanea.
Il runtime Node usato in produzione e' `/opt/plesk/node/20/bin`.

## Stato migrazione

Questa repo contiene la base Laravel/Vue/MySQL e lo schema dati principale derivato da Supabase. Le schermate React/Lovable originali devono essere riscritte in Vue in fasi successive: clienti, progetti, task, calendario, documenti, notifiche, backup, invio email, PDF/XML fatture.
