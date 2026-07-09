<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\TiktokShopDataView;
use App\Services\MarketplaceTitlePushService;
use App\Services\Support\AllMarketplaceChannelRegistry;
use App\Services\Support\MarketplaceCharacterLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'GSTOOL BLK';
$registry = app(AllMarketplaceChannelRegistry::class);
$push = app(MarketplaceTitlePushService::class);

$product = ProductMaster::where('sku', $sku)->orWhere('SKU', $sku)->first();
if (! $product) {
    echo "SKU not in product_master: {$sku}\n";
    exit(1);
}

echo "=== Title push edge-case preflight: {$sku} ===\n\n";

foreach ($registry->titleMeta() as $registryKey => $meta) {
    $mp = $meta['push'];
    $type = $meta['type'];
    $title = match ($type) {
        '80' => trim((string) ($product->title80 ?? '')),
        '60' => trim((string) ($product->title60 ?? '')),
        '100' => trim((string) ($product->title100 ?? '')),
        default => trim((string) ($product->title150 ?? '')),
    };
    $truncated = MarketplaceCharacterLimits::truncateTitle($title, $mp, $type);
    $limit = MarketplaceCharacterLimits::titleLimit($mp, $type);

    $issues = [];
    if ($title === '') {
        $issues[] = 'EMPTY_TITLE';
    }
    if ($title !== '' && mb_strlen($title) > $limit) {
        $issues[] = 'WILL_TRUNCATE ('.mb_strlen($title)."→{$limit})";
    }

    if (in_array($mp, ['ebay', 'ebay1', 'ebay2', 'ebay3'], true)) {
        $model = match ($mp) {
            'ebay2' => Ebay2Metric::class,
            'ebay3' => Ebay3Metric::class,
            default => EbayMetric::class,
        };
        $metric = $model::query()
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();
        if (! $metric || ! $metric->item_id) {
            $issues[] = 'EBAY_NO_ITEM_ID (title push has NO API fallback)';
        }
    }

    if ($mp === 'aliexpress' && Schema::hasTable('aliexpress_metric')) {
        $pid = DB::table('aliexpress_metric')
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->value('product_id');
        if (! $pid) {
            $issues[] = 'NO_ALIEXPRESS_PRODUCT_ID';
        }
    }

    if (in_array($mp, ['tiktok', 'tiktok2'], true)) {
        $row = TiktokShopDataView::query()
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();
        $pid = null;
        if ($row && $row->value) {
            $value = is_array($row->value) ? $row->value : json_decode($row->value, true);
            $pid = is_array($value) ? ($value['product_id'] ?? $value['productId'] ?? $value['id'] ?? null) : null;
        }
        if (! $pid) {
            $issues[] = 'NO_TIKTOK_PRODUCT_ID';
        }
        if ($mp === 'tiktok2') {
            $issues[] = 'TIKTOK2_SHARES_TIKTOK1_LOOKUP';
        }
    }

    if ($mp === 'doba' && Schema::hasTable('doba_metrics')) {
        $item = DB::table('doba_metrics')->where('sku', $sku)->value('item_id');
        if (! $item) {
            $issues[] = 'NO_DOBA_ITEM_ID';
        }
    }

    if ($mp === 'shopify_b5c') {
        $issues[] = 'ROUTES_TO_PLS_STORE (not Business 5Core Shopify)';
    }

    if ($mp === 'temu2') {
        $issues[] = 'NEEDS_TEMU2_GOODS_ID';
    }

    if ($mp === 'alibaba' && ! config('services.alibaba.access_token')) {
        $issues[] = 'NO_ALIBABA_TOKEN';
    }

  $status = $issues === [] ? 'OK' : implode('; ', $issues);
    echo str_pad($registryKey, 18)." type={$type} chars=".mb_strlen($title)." | {$status}\n";
}

echo "\nNote: Live push not executed — preflight only.\n";
