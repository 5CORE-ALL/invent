<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MarketplaceSyncSettings;
use App\Models\ReverbMetric;
use App\Models\AliexpressMetric;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Schema;

echo "=== TABLES ===\n";
echo 'marketplace_sync_settings='.(Schema::hasTable('marketplace_sync_settings') ? 'yes' : 'no')."\n";
echo 'reverb_metric='.(Schema::hasTable('reverb_metric') ? 'yes' : 'no')."\n";
echo 'aliexpress_metric='.(Schema::hasTable('aliexpress_metric') ? 'yes' : 'no')."\n";
echo 'shopify_skus='.(Schema::hasTable('shopify_skus') ? 'yes' : 'no')."\n\n";

if (! Schema::hasTable('marketplace_sync_settings')) {
    echo "No DB access / wrong database.\n";
    exit(1);
}

echo "=== SETTINGS ===\n";
foreach (['reverb', 'aliexpress'] as $mp) {
    $s = MarketplaceSyncSettings::getFor($mp);
    echo $mp
        .' inventory_sync='.json_encode($s['inventory']['inventory_sync'] ?? null)
        .' price_sync='.json_encode($s['pricing']['price_sync'] ?? null)
        .' pct='.json_encode($s['inventory']['quantity_calc_percent'] ?? null)
        .' min='.json_encode($s['inventory']['min_quantity'] ?? null)
        .' max='.json_encode($s['inventory']['max_quantity'] ?? null)
        ."\n";
}

function expectedQty(?int $shopify, array $settings): ?int
{
    if ($shopify === null) {
        return null;
    }
    $pct = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
    $min = max(0, (int) ($settings['inventory']['min_quantity'] ?? 0));
    $max = $settings['inventory']['max_quantity'] ?? null;
    $qty = (int) floor($shopify * ($pct / 100));
    if ($qty < $min) {
        $qty = $min;
    }
    if ($max !== null && $max !== '') {
        $qty = min($qty, (int) $max);
    }

    return max(0, $qty);
}

function shopifyQty(string $sku): ?int
{
    $ss = ShopifySku::firstForProductSku($sku);
    if (! $ss) {
        return null;
    }
    if ($ss->available_to_sell !== null) {
        return (int) $ss->available_to_sell;
    }
    if ($ss->inv !== null) {
        return (int) $ss->inv;
    }

    return null;
}

function compareChannel(string $label, $query, array $settings): void
{
    echo "\n=== {$label} LINKED COMPARE ===\n";
    $rows = $query
        ->whereNotNull('product_id')
        ->where('sku', '!=', '')
        ->whereColumn('sku', '!=', 'product_id')
        ->orderByDesc('updated_at')
        ->limit(200)
        ->get(['sku', 'product_id', 'inventory', 'updated_at']);

    $linked = $rows->count();
    $match = 0;
    $mismatch = 0;
    $noShopify = 0;
    $examples = [];

    foreach ($rows as $m) {
        $sku = (string) $m->sku;
        $mpQty = $m->inventory !== null ? (int) $m->inventory : null;
        $shop = shopifyQty($sku);
        if ($shop === null) {
            $noShopify++;
            if (count($examples) < 12) {
                $examples[] = "{$sku}|mp={$mpQty}|shop=MISSING|exp=n/a|upd={$m->updated_at}";
            }
            continue;
        }
        $exp = expectedQty($shop, $settings);
        if ($mpQty === $exp) {
            $match++;
        } else {
            $mismatch++;
            if (count($examples) < 12) {
                $examples[] = "{$sku}|mp={$mpQty}|shop={$shop}|exp={$exp}|upd={$m->updated_at}";
            }
        }
    }

    $totalLinked = null;
    try {
        $totalLinked = (clone $query)
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->count();
    } catch (Throwable $e) {
        $totalLinked = '?';
    }

    echo "total_linked={$totalLinked} sampled={$linked} match={$match} mismatch={$mismatch} no_shopify={$noShopify}\n";
    echo "examples (mismatch or missing):\n";
    foreach ($examples as $ex) {
        echo $ex."\n";
    }
}

if (Schema::hasTable('reverb_metric')) {
    compareChannel('REVERB', ReverbMetric::query(), MarketplaceSyncSettings::getFor('reverb'));
}
if (Schema::hasTable('aliexpress_metric')) {
    compareChannel('ALIEXPRESS', AliexpressMetric::query(), MarketplaceSyncSettings::getFor('aliexpress'));
}

echo "\nDone.\n";
