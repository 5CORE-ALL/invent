#!/bin/bash
#==============================================================================
# PRODUCTION DEPLOYMENT SCRIPT - Invent Project
#==============================================================================
# Usage: bash deploy.sh
#
# This script safely deploys the application on a VDS production server.
# It clears all caches, rebuilds them, and restarts queue workers.
#
# IMPORTANT: Run as the web server user (www-data) or with sudo.
#==============================================================================

set -e  # Exit on error

# ─── Configuration ───────────────────────────────────────────────────────────
# Auto-detect paths. Override these if your server layout differs.
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN=$(which php 2>/dev/null || echo "/usr/bin/php")
ARTISAN="${PROJECT_DIR}/artisan"

echo "=============================================="
echo " INVENT - Production Deployment"
echo "=============================================="
echo " Project : ${PROJECT_DIR}"
echo " PHP     : ${PHP_BIN}"
echo " Date    : $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================="

# ─── Step 1: Pre-flight checks ──────────────────────────────────────────────
echo ""
echo "[1/8] Pre-flight checks..."

if [ ! -f "${ARTISAN}" ]; then
    echo "ERROR: artisan not found at ${ARTISAN}"
    exit 1
fi

if [ ! -f "${PROJECT_DIR}/.env" ]; then
    echo "ERROR: .env file not found!"
    exit 1
fi

echo "  ✓ Artisan found"
echo "  ✓ .env file found"

# Resolve web-server user (PHP-FPM). Path like /var/www/USER/data/www/... → USER.
WEB_USER="${DEPLOY_WEB_USER:-}"
if [ -z "${WEB_USER}" ]; then
    if [[ "${PROJECT_DIR}" =~ /var/www/([^/]+)/ ]]; then
        WEB_USER="${BASH_REMATCH[1]}"
    elif id www-data &>/dev/null; then
        WEB_USER="www-data"
    fi
fi

fix_storage_permissions() {
    mkdir -p "${PROJECT_DIR}/storage/logs"
    mkdir -p "${PROJECT_DIR}/storage/framework/cache"
    mkdir -p "${PROJECT_DIR}/storage/framework/sessions"
    mkdir -p "${PROJECT_DIR}/storage/framework/views"
    mkdir -p "${PROJECT_DIR}/bootstrap/cache"
    touch "${PROJECT_DIR}/storage/logs/laravel.log" 2>/dev/null || true
    touch "${PROJECT_DIR}/storage/logs/scheduler.log" 2>/dev/null || true
    touch "${PROJECT_DIR}/storage/logs/cron.log" 2>/dev/null || true
    if [ -n "${WEB_USER}" ] && id "${WEB_USER}" &>/dev/null; then
        chown -R "${WEB_USER}:${WEB_USER}" "${PROJECT_DIR}/storage" "${PROJECT_DIR}/bootstrap/cache"
    fi
    chmod -R ug+rwX "${PROJECT_DIR}/storage" "${PROJECT_DIR}/bootstrap/cache"
}

echo ""
echo "[1b/8] Ensuring storage permissions (before artisan writes)..."
fix_storage_permissions
if [ -n "${WEB_USER}" ]; then
    echo "  ✓ storage/bootstrap/cache writable (owner: ${WEB_USER})"
else
    echo "  ⚠ WEB_USER not detected; set DEPLOY_WEB_USER if permission errors persist"
fi

# ─── Step 2: Maintenance mode ───────────────────────────────────────────────
echo ""
echo "[2/8] Entering maintenance mode..."
${PHP_BIN} ${ARTISAN} down --retry=60 --refresh=15 2>/dev/null || true
echo "  ✓ Maintenance mode ON"

# ─── Step 3: Clear all caches ───────────────────────────────────────────────
echo ""
echo "[3/8] Clearing all caches..."
${PHP_BIN} ${ARTISAN} optimize:clear
echo "  ✓ All caches cleared"

# ─── Step 4: Rebuild optimized caches ───────────────────────────────────────
echo ""
echo "[4/8] Rebuilding optimized caches..."
${PHP_BIN} ${ARTISAN} config:cache
echo "  ✓ Config cached"

${PHP_BIN} ${ARTISAN} route:cache
echo "  ✓ Routes cached"

${PHP_BIN} ${ARTISAN} view:cache
echo "  ✓ Views cached"

# ─── Step 5: Run migrations (if any) ────────────────────────────────────────
echo ""
echo "[5/8] Running database migrations..."
${PHP_BIN} ${ARTISAN} migrate --force
echo "  ✓ Migrations complete"

# ─── Step 6: Restart queue workers ──────────────────────────────────────────
echo ""
echo "[6/8] Restarting queue workers..."
${PHP_BIN} ${ARTISAN} queue:restart
echo "  ✓ Queue restart signal sent"

# Permanent watchdog: keeps all dedicated queue workers alive (explicit --queue only).
if [ -x "${PROJECT_DIR}/scripts/cron-queue-watchdog-daemon.sh" ]; then
    bash "${PROJECT_DIR}/scripts/cron-queue-watchdog-daemon.sh"
    echo "  ✓ Dedicated queue watchdog daemon ensured"
elif [ -x "${PROJECT_DIR}/scripts/cron-google-maps-extractor-watchdog.sh" ]; then
    bash "${PROJECT_DIR}/scripts/cron-google-maps-extractor-watchdog.sh"
    echo "  ✓ Dedicated queue watchdog daemon ensured (legacy script)"
else
    ${PHP_BIN} ${ARTISAN} queue:ensure-watchdog-daemon >/dev/null 2>&1 || true
    echo "  ✓ Dedicated queue watchdog daemon invoked (artisan)"
fi

# Per-queue worker scripts (fallback if watchdog is not running yet).
for worker_script in \
    "${PROJECT_DIR}/scripts/cron-google-maps-extractor-worker.sh" \
    "${PROJECT_DIR}/scripts/cron-shopify-image-pull-worker.sh" \
    "${PROJECT_DIR}/scripts/cron-shopify-bullet-pull-worker.sh" \
    "${PROJECT_DIR}/scripts/cron-shopify-video-pull-worker.sh" \
    "${PROJECT_DIR}/scripts/cron-image-master-push-worker.sh" \
    "${PROJECT_DIR}/scripts/cron-video-master-push-worker.sh"
do
    if [ -x "${worker_script}" ]; then
        bash "${worker_script}"
        echo "  ✓ Invoked $(basename "${worker_script}")"
    fi
done

# ─── Step 7: Permissions ────────────────────────────────────────────────────
echo ""
echo "[7/8] Fixing permissions..."
fix_storage_permissions
echo "  ✓ Permissions set"

# ─── Step 8: Exit maintenance mode ──────────────────────────────────────────
echo ""
echo "[8/8] Exiting maintenance mode..."
${PHP_BIN} ${ARTISAN} up
echo "  ✓ Application is LIVE"

echo ""
echo "=============================================="
echo " DEPLOYMENT COMPLETE"
echo "=============================================="
echo ""
echo " Next steps:"
echo "   1. Verify cron: crontab -l"
echo "   2. Verify supervisor: supervisorctl status"
echo "   3. Google Maps extractor worker log:"
echo "      tail -f ${PROJECT_DIR}/storage/logs/google-maps-extractor-worker.log"
echo "   4. Test site: curl -s -o /dev/null -w '%{http_code}' http://localhost"
echo "   5. Check logs: tail -f ${PROJECT_DIR}/storage/logs/scheduler.log"
echo ""
