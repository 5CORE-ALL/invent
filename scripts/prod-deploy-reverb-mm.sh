#!/bin/bash
set -e
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
git fetch origin main
git reset --hard origin/main
php artisan migrate --force --path=database/migrations/2026_07_11_233000_create_reverb_marketplace_manager_tables.php
php artisan optimize:clear
chmod +x scripts/cron-marketplace-manager-worker.sh
chmod +x scripts/cron-aliexpress-worker.sh
chmod +x scripts/cron-alibaba-worker.sh
bash scripts/cron-marketplace-manager-worker.sh || true
echo "HEAD=$(git log -1 --oneline)"
echo "=== schedule (mm) ==="
php artisan schedule:list 2>/dev/null | grep -E 'aliexpress|alibaba|reverb:manager|marketplace' || true
echo "=== channels ==="
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "channels=".implode(",", App\Services\MarketplaceManager\MarketplaceManagerRegistry::slugs()).PHP_EOL; echo "queue=".App\Services\MarketplaceManager\MarketplaceManagerRegistry::QUEUE.PHP_EOL; echo "reverb_configured=".(app(App\Services\Support\MarketplaceApiConfigService::class)->isConfigured("reverb")?"yes":"no").PHP_EOL; echo "reverb_metric=".(Illuminate\Support\Facades\Schema::hasTable("reverb_metric")?"yes":"no").PHP_EOL; echo "reverb_order_metrics=".(Illuminate\Support\Facades\Schema::hasTable("reverb_order_metrics")?"yes":"no").PHP_EOL;'
echo DONE
