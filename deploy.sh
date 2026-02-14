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

# ─── Step 7: Permissions ────────────────────────────────────────────────────
echo ""
echo "[7/8] Fixing permissions..."

# Ensure storage and cache directories exist
mkdir -p "${PROJECT_DIR}/storage/logs"
mkdir -p "${PROJECT_DIR}/storage/framework/cache"
mkdir -p "${PROJECT_DIR}/storage/framework/sessions"
mkdir -p "${PROJECT_DIR}/storage/framework/views"
mkdir -p "${PROJECT_DIR}/bootstrap/cache"

# Touch scheduler log so appendOutputTo doesn't fail
touch "${PROJECT_DIR}/storage/logs/scheduler.log"
touch "${PROJECT_DIR}/storage/logs/cron.log"

# Set permissions (adjust user if not www-data)
WEB_USER="www-data"
if id "${WEB_USER}" &>/dev/null; then
    chown -R ${WEB_USER}:${WEB_USER} "${PROJECT_DIR}/storage" 2>/dev/null || true
    chown -R ${WEB_USER}:${WEB_USER} "${PROJECT_DIR}/bootstrap/cache" 2>/dev/null || true
fi

chmod -R 775 "${PROJECT_DIR}/storage"
chmod -R 775 "${PROJECT_DIR}/bootstrap/cache"

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
echo "   3. Test site: curl -s -o /dev/null -w '%{http_code}' http://localhost"
echo "   4. Check logs: tail -f ${PROJECT_DIR}/storage/logs/scheduler.log"
echo ""
