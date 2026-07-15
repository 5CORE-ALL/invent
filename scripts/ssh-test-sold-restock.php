<?php

require __DIR__.'/../vendor/autoload.php';

use phpseclib3\Net\SSH2;

$pass = getenv('SSH_PASS') ?: '';
if ($pass === '') {
    fwrite(STDERR, "Set SSH_PASS\n");
    exit(1);
}

$ssh = new SSH2('31.59.184.74');
$ssh->setTimeout(180);
if (! $ssh->login('root', $pass)) {
    fwrite(STDERR, "SSH fail\n");
    exit(1);
}

$remote = <<<'PHP'
<?php
require '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/vendor/autoload.php';
$app = require '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReverbMetric;
use App\Models\ShopifySku;
use App\Services\ReverbApiService;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use Illuminate\Support\Facades\Http;

$token = ReverbApiService::getReverbBearerToken();
$store = preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
$shopToken = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');

// Find a few linked sold metrics that have Shopify stock > 0
$samples = [];
$metrics = ReverbMetric::query()
    ->whereNotNull('product_id')
    ->where('sku', '!=', '')
    ->whereColumn('sku', '!=', 'product_id')
    ->orderBy('id')
    ->limit(400)
    ->get(['sku', 'product_id']);

foreach ($metrics as $m) {
    if (count($samples) >= 5) break;
    $sku = (string) $m->sku;
    $pid = (string) $m->product_id;
    $ss = ShopifySku::firstForProductSku($sku);
    $shop = (int) ($ss->available_to_sell ?? $ss->inv ?? 0);
    if ($shop < 1) continue;

    $r = Http::withoutVerifying()->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/hal+json',
        'Accept-Version' => '3.0',
    ])->timeout(25)->get('https://api.reverb.com/api/listings/'.$pid);
    if (!$r->successful()) continue;
    $j = $r->json();
    $state = is_array($j['state'] ?? null) ? ($j['state']['slug'] ?? '') : (string) ($j['state'] ?? '');
    $inv = (int) ($j['inventory'] ?? 0);
    if (strtolower($state) !== 'sold') continue;

    $samples[] = compact('sku', 'pid', 'shop', 'state', 'inv');
    usleep(120000);
}

echo "sold_with_shopify_gt0 samples=".count($samples).PHP_EOL;
foreach ($samples as $s) {
    echo "BEFORE {$s['sku']} pid={$s['pid']} shopify={$s['shop']} reverb_state={$s['state']} reverb_inv={$s['inv']}\n";
}

if ($samples === []) {
    echo "No sold+shopify>=1 found in first 400 metrics\n";
    exit(0);
}

$target = $samples[0];
$sku = $target['sku'];
$pid = $target['pid'];
$qty = max(1, (int) $target['shop']);

echo "TEST syncing {$sku} -> qty {$qty}\n";
$result = app(\App\Services\MarketplaceManager\ReverbInventorySyncService::class)->syncSkusFromShopify([$sku]);
echo "sync_result=".json_encode($result).PHP_EOL;

sleep(1);
$r = Http::withoutVerifying()->withHeaders([
    'Authorization' => 'Bearer '.$token,
    'Accept' => 'application/hal+json',
    'Accept-Version' => '3.0',
])->timeout(25)->get('https://api.reverb.com/api/listings/'.$pid);
$j = $r->json();
$state = is_array($j['state'] ?? null) ? ($j['state']['slug'] ?? '') : (string) ($j['state'] ?? '');
$inv = (int) ($j['inventory'] ?? 0);
echo "AFTER state={$state} inv={$inv} http=".$r->status().PHP_EOL;

echo "rules sold_may=". (MarketplaceLiveInventoryRules::reverbMayUpdateInventory('sold') ? 'yes':'no').PHP_EOL;
PHP;

$b64 = base64_encode($remote);
echo $ssh->exec(implode("\n", [
    'TMP=$(mktemp /tmp/soldchkXXXX.php)',
    "echo {$b64} | base64 -d > \"\$TMP\"",
    'php "$TMP"',
    'rm -f "$TMP"',
]));
