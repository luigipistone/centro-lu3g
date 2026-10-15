#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/vhosts/lu3g.com/centro.lu3g.com"
GIT_DIR="/var/www/vhosts/lu3g.com/git/centro-lu3g"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="/opt/plesk/php/8.3/bin/php"
COMPOSER_PHAR="/opt/psa/var/modules/composer/composer.phar"
NODE_BIN_DIR="/opt/plesk/node/20/bin"
LOCK_FILE="/tmp/centro-lu3g-deploy.lock"
STAGE_DIR=""
MAINTENANCE_ENABLED=0

log() {
    printf '[centro-lu3g deploy] %s\n' "$*"
}

cleanup() {
    if [ "$MAINTENANCE_ENABLED" -eq 1 ]; then
        log "ERROR: deployment stopped after the switch; production remains in maintenance mode"
    fi
    if [ -n "$STAGE_DIR" ]; then
        rm -rf -- "$STAGE_DIR"
    fi
}

deploy() {
    log "Fetching ${BRANCH}"
    git -C "$GIT_DIR" fetch origin "+refs/heads/${BRANCH}:refs/heads/${BRANCH}"

    if [ ! -x "$NODE_BIN_DIR/node" ] || [ ! -x "$NODE_BIN_DIR/npm" ]; then
        log "Node.js runtime not found in $NODE_BIN_DIR"
        return 1
    fi

    STAGE_DIR="$(mktemp -d "${APP_DIR%/*}/.centro-deploy.XXXXXX")"
    trap cleanup EXIT

    log "Preparing ${BRANCH} outside production"
    git -C "$GIT_DIR" archive "$BRANCH" | tar -x -C "$STAGE_DIR"
    mkdir -p "$STAGE_DIR/previous-assets"
    if [ -d "$APP_DIR/public/build/assets" ]; then
        cp -a "$APP_DIR/public/build/assets/." "$STAGE_DIR/previous-assets/"
    fi

    cd "$STAGE_DIR"
    if [ -f "$COMPOSER_PHAR" ]; then
        log "Installing PHP dependencies for the staged build"
        "$PHP_BIN" "$COMPOSER_PHAR" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    fi

    log "Installing frontend dependencies"
    PATH="$NODE_BIN_DIR:$PATH" npm ci --no-audit --no-fund

    log "Building frontend assets"
    PATH="$NODE_BIN_DIR:$PATH" npm run build
    test -s "$STAGE_DIR/public/build/manifest.json"
    cp -an "$STAGE_DIR/previous-assets/." "$STAGE_DIR/public/build/assets/"

    log "Switching production to ${BRANCH}"
    cd "$APP_DIR"
    "$PHP_BIN" artisan down --retry=5 --refresh=5
    MAINTENANCE_ENABLED=1
    GIT_WORK_TREE="$APP_DIR" git -C "$GIT_DIR" checkout -f "$BRANCH"

    if [ -f "$COMPOSER_PHAR" ]; then
        log "Installing PHP dependencies"
        "$PHP_BIN" "$COMPOSER_PHAR" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    fi

    cp -a "$STAGE_DIR/public/build/." "$APP_DIR/public/build/"

    log "Running database migrations"
    "$PHP_BIN" artisan migrate --force

    log "Refreshing Laravel caches"
    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache

    "$PHP_BIN" artisan up
    MAINTENANCE_ENABLED=0

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
