#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/vhosts/lu3g.com/centro.lu3g.com"
GIT_DIR="/var/www/vhosts/lu3g.com/git/centro-lu3g"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="/opt/plesk/php/8.3/bin/php"
COMPOSER_PHAR="/opt/psa/var/modules/composer/composer.phar"
NODE_BIN_DIR="/opt/plesk/node/20/bin"
LOCK_FILE="/tmp/centro-lu3g-deploy.lock"

log() {
    printf '[centro-lu3g deploy] %s\n' "$*"
}

deploy() {
    log "Fetching ${BRANCH}"
    git -C "$GIT_DIR" fetch origin "+refs/heads/${BRANCH}:refs/heads/${BRANCH}"

    log "Checking out ${BRANCH} into production"
    GIT_WORK_TREE="$APP_DIR" git -C "$GIT_DIR" checkout -f "$BRANCH"

    cd "$APP_DIR"

    if [ -f "$COMPOSER_PHAR" ]; then
        log "Installing PHP dependencies"
        "$PHP_BIN" "$COMPOSER_PHAR" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    fi

    if [ -x "$NODE_BIN_DIR/node" ] && [ -x "$NODE_BIN_DIR/npm" ]; then
        log "Installing frontend dependencies"
        PATH="$NODE_BIN_DIR:$PATH" npm ci --no-audit --no-fund

        log "Building frontend assets"
        PATH="$NODE_BIN_DIR:$PATH" npm run build
    else
        log "Node.js runtime not found in $NODE_BIN_DIR"
        return 1
    fi

    log "Running database migrations"
    "$PHP_BIN" artisan migrate --force

    log "Refreshing Laravel caches"
    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache

    local scheduler_entry="* * * * * cd $APP_DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1 # centro-lu3g-scheduler"
    if command -v crontab >/dev/null 2>&1; then
        local existing_crontab
        existing_crontab="$(crontab -l 2>/dev/null || true)"
        if ! grep -Fq 'artisan schedule:run' <<< "$existing_crontab"; then
            if printf '%s\n%s\n' "$existing_crontab" "$scheduler_entry" | crontab -; then
                log 'Laravel scheduler configured in crontab'
            else
                log 'WARNING: unable to configure the scheduler; add schedule:run in Plesk Scheduled Tasks'
            fi
        fi
    else
        log 'WARNING: crontab unavailable; add schedule:run in Plesk Scheduled Tasks'
    fi

    log "Current production commit: $(git -C "$GIT_DIR" rev-parse --short "$BRANCH")"
}

if [ "${CENTRO_LU3G_DEPLOY_LOCKED:-0}" != "1" ]; then
    export CENTRO_LU3G_DEPLOY_LOCKED=1
    exec flock -n "$LOCK_FILE" "$0" "$@"
fi

deploy
