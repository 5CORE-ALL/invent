<?php

$root = getenv('INVENT_ROOT') ?: (is_file('/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/artisan')
    ? '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com'
    : dirname(__DIR__));
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AliexpressMetric;
use App\Services\AliExpressApiService;

$api = app(AliExpressApiService::class);
$pageSize = 50;
$summary = [
    'db_aliexpress_metric_count' => AliexpressMetric::query()->count(),
    'db_with_product_id' => AliexpressMetric::query()->whereNotNull('product_id')->where('product_id', '!=', '')->count(),
    'pages' => [],
];

for ($page = 1; $page <= 5; $page++) {
    $result = $api->getInventory($page, $pageSize);
    if (empty($result['success'])) {
        $summary['pages'][] = ['page' => $page, 'error' => $result['message'] ?? 'failed'];
        break;
    }
    $data = $result['data'] ?? [];
    $products = $data['products'] ?? [];
    $withSku = 0;
    $withoutSku = 0;
    $upsertable = 0;
    foreach ($products as $item) {
        if (! is_array($item)) {
            continue;
        }
        $rows = $api->extractSkuRowsFromListItem($item, fetchDetail: false);
        $hadRealSku = false;
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $pid = (string) ($row['product_id'] ?? '');
            if ($sku !== '' && $sku !== $pid) {
                $hadRealSku = true;
                $upsertable++;
            }
        }
        if (! $hadRealSku) {
            $rows = $api->extractSkuRowsFromListItem($item, fetchDetail: true);
            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $pid = (string) ($row['product_id'] ?? '');
                if ($sku !== '' && $sku !== $pid) {
                    $hadRealSku = true;
                    $upsertable++;
                }
            }
        }
        if ($hadRealSku) {
            $withSku++;
        } else {
            $withoutSku++;
        }
    }

    $summary['pages'][] = [
        'page' => $page,
        'product_count' => count($products),
        'total_count' => $data['total_count'] ?? null,
        'total_page' => $data['total_page'] ?? null,
        'current_page' => $data['current_page'] ?? null,
        'products_with_real_sku' => $withSku,
        'products_without_real_sku' => $withoutSku,
        'upsertable_sku_rows' => $upsertable,
    ];

    if (count($products) === 0) {
        break;
    }
    if (count($products) < $pageSize) {
        break;
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
