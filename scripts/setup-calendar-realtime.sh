#!/usr/bin/env bash
set -euo pipefail
[[ "$(id -u)" == 0 ]] || { echo "Eseguire da root."; exit 1; }
APP=/var/www/vhosts/lu3g.com/centro.lu3g.com
PHP=/opt/plesk/php/8.3/bin/php
CONF=/var/www/vhosts/system/centro.lu3g.com/conf/vhost_nginx.conf
test -f "$APP/artisan"
test -f "$APP/vendor/laravel/reverb/src/ReverbServiceProvider.php"
RUN_USER="$(stat -c %U "$APP/.env")"
test "$RUN_USER" != root
id "$RUN_USER" >/dev/null
if ss -ltn 'sport = :8085' | grep -q LISTEN && ! systemctl is-active --quiet centro-reverb; then
    echo "La porta 8085 e gia occupata. Nessuna modifica applicata."; exit 1
fi
BACKUP="$CONF.before-realtime-$(date +%Y%m%d%H%M%S)"
if [[ -f "$CONF" ]]; then cp -a "$CONF" "$BACKUP"; else touch "$CONF"; cp -a "$CONF" "$BACKUP"; fi
if ! grep -q '# centro-calendar-realtime' "$CONF"; then
    if grep -Eq 'location[[:space:]]+.*?/app/' "$CONF"; then
        echo "Esiste gia una regola /app/: verificare prima di continuare."; exit 1
    fi
    cat >> "$CONF" <<'NGINX'

# centro-calendar-realtime
location ^~ /app/ {
    proxy_pass http://127.0.0.1:8085;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 120s;
    proxy_send_timeout 120s;
    proxy_buffering off;
}
NGINX
fi
if ! /usr/local/psa/admin/sbin/httpdmng --reconfigure-domain centro.lu3g.com; then
    cp -a "$BACKUP" "$CONF"
    /usr/local/psa/admin/sbin/httpdmng --reconfigure-domain centro.lu3g.com
    exit 1
fi
cd "$APP"
# Preserve existing credentials across repeat runs. Secrets never enter stdout.
runuser -u "$RUN_USER" -- "$PHP" <<'PHP'
<?php
$path = '.env';
$env = file_get_contents($path);
$values = [
    'REVERB_APP_ID' => 'centro-calendar',
    'REVERB_APP_KEY' => bin2hex(random_bytes(16)),
    'REVERB_APP_SECRET' => bin2hex(random_bytes(32)),
    'REVERB_SERVER_HOST' => '127.0.0.1',
    'REVERB_SERVER_PORT' => '8085',
    'REVERB_PUBLIC_HOST' => 'centro.lu3g.com',
    'REVERB_PUBLIC_PORT' => '443',
    'REVERB_PUBLIC_TLS' => 'true',
];
foreach ($values as $key => $value) {
    $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';
    if (!preg_match($pattern, $env, $match)) {
        $env .= "\n".$key.'='.$value;
    } elseif (trim($match[1], " \t\r\n\"'") === '') {
        $env = preg_replace($pattern, $key.'='.$value, $env);
    }
}
file_put_contents($path, rtrim($env)."\n", LOCK_EX);
PHP
cat > /etc/systemd/system/centro-reverb.service <<SERVICE
[Unit]
Description=Centro calendar realtime
After=network.target
[Service]
Type=simple
User=$RUN_USER
WorkingDirectory=$APP
ExecStart=$PHP artisan reverb:start --host=127.0.0.1 --port=8085 --no-interaction
Restart=always
RestartSec=3
LimitNOFILE=10000
NoNewPrivileges=true
PrivateTmp=true
[Install]
WantedBy=multi-user.target
SERVICE
runuser -u "$RUN_USER" -- "$PHP" artisan config:cache
systemctl daemon-reload
systemctl enable --now centro-reverb
systemctl restart centro-reverb
sleep 3
systemctl is-active --quiet centro-reverb
runuser -u "$RUN_USER" -- "$PHP" <<'PHP'
<?php
$path = '.env';
$env = file_get_contents($path);
$key = 'CALENDAR_REALTIME_ENABLED';
$env = preg_match('/^'.$key.'=/m', $env)
    ? preg_replace('/^'.$key.'=.*$/m', $key.'=true', $env)
    : rtrim($env)."\n".$key."=true\n";
file_put_contents($path, $env, LOCK_EX);
PHP
runuser -u "$RUN_USER" -- "$PHP" artisan config:cache
systemctl restart centro-reverb
echo "Realtime attivato. Riaprire il calendario nei browser."
