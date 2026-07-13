<?php

require __DIR__.'/../vendor/autoload.php';

use phpseclib3\Net\SSH2;

$host = '31.59.184.74';
$user = 'root';
$pass = getenv('SSH_PASS') ?: '';
$remoteRoot = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';

if ($pass === '') {
    fwrite(STDERR, "Set SSH_PASS\n");
    exit(1);
}

$ssh = new SSH2($host);
$ssh->setTimeout(600);
if (! $ssh->login($user, $pass)) {
    fwrite(STDERR, "SSH login failed.\n");
    exit(1);
}

echo "Connected to {$host}\n";

// Build with explicit LF only (Windows CRLF breaks remote bash).
$commands = implode("\n", [
    "cd {$remoteRoot}",
    'git fetch origin main',
    'git reset --hard origin/main',
    'php artisan migrate --force --path=database/migrations/2026_07_11_233000_create_reverb_marketplace_manager_tables.php',
    'php artisan optimize:clear',
    'php artisan reverb:backfill-order-ids',
    'chmod +x scripts/cron-marketplace-manager-worker.sh scripts/cron-aliexpress-worker.sh scripts/cron-alibaba-worker.sh scripts/prod-deploy-reverb-mm.sh',
    'bash scripts/cron-marketplace-manager-worker.sh || true',
    'echo HEAD=$(git log -1 --oneline)',
    'php artisan schedule:list 2>/dev/null | grep -E "aliexpress|alibaba|reverb:manager" || true',
    'php -r \'$app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo "channels=".implode(",", App\\Services\\MarketplaceManager\\MarketplaceManagerRegistry::slugs()).PHP_EOL; echo "queue=".App\\Services\\MarketplaceManager\\MarketplaceManagerRegistry::QUEUE.PHP_EOL; echo "reverb_configured=".(app(App\\Services\\Support\\MarketplaceApiConfigService::class)->isConfigured("reverb")?"yes":"no").PHP_EOL; echo "reverb_metric=".(Illuminate\\Support\\Facades\\Schema::hasTable("reverb_metric")?"yes":"no").PHP_EOL;\'',
    'echo DONE',
]);

echo $ssh->exec($commands);
echo "\nDeploy finished.\n";
