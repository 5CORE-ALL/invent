#!/bin/bash
set -e
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
git fetch origin main
git reset --hard origin/main
php artisan migrate --force --path=database/migrations/2026_07_11_220000_create_alibaba_marketplace_manager_tables.php
php artisan optimize:clear
chmod +x scripts/cron-marketplace-manager-worker.sh
chmod +x scripts/cron-alibaba-worker.sh
bash scripts/cron-marketplace-manager-worker.sh || true
echo "HEAD=$(git log -1 --oneline)"
echo "=== schedule ==="
php artisan schedule:list 2>/dev/null | grep alibaba || true
echo "=== smoke ==="
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "channels=".implode(",", App\Services\MarketplaceManager\MarketplaceManagerRegistry::slugs()).PHP_EOL; echo "configured=".(app(App\Services\Support\MarketplaceApiConfigService::class)->isConfigured("alibaba")?"yes":"no").PHP_EOL; echo "listings=".App\Models\AlibabaMetric::query()->whereNotNull("sku")->count().PHP_EOL; echo "orders_table=".(Illuminate\Support\Facades\Schema::hasTable("alibaba_order_metrics")?"yes":"no").PHP_EOL; echo "pricing_table=".(Illuminate\Support\Facades\Schema::hasTable("alibaba_pricing_prices")?"yes":"no").PHP_EOL;'
echo "=== inventory dry-run ==="
php artisan alibaba:sync-inventory-from-shopify --dry-run || true
echo "=== orders fetch ==="
php artisan alibaba:sync-orders --from=2026-07-11 || true
echo DONE
