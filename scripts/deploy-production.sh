#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/vhosts/lu3g.com/centro.lu3g.com"
GIT_DIR="/var/www/vhosts/lu3g.com/git/centro-lu3g"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="/opt/plesk/php/8.3/bin/php"
COMPOSER_PHAR="/opt/psa/var/modules/composer/composer.phar"
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

    log "Running database migrations"
    "$PHP_BIN" artisan migrate --force

    log "Refreshing Laravel caches"
    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache

    log "Current production commit: $(git -C "$GIT_DIR" rev-parse --short "$BRANCH")"
}

if [ "${CENTRO_LU3G_DEPLOY_LOCKED:-0}" != "1" ]; then
    export CENTRO_LU3G_DEPLOY_LOCKED=1
    exec flock -n "$LOCK_FILE" "$0" "$@"
fi

deploy
