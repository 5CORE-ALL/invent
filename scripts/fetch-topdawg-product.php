<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$token = config('services.topdawg.token');
$base = rtrim((string) config('services.topdawg.base_url'), '/');
$headers = [
    'Authorization' => 'Bearer '.$token,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
];

$found = null;
for ($page = 1; $page <= 15 && ! $found; $page++) {
    $resp = Http::withHeaders($headers)->timeout(60)->post($base.'/SupplierProduct/list', [
        'per_page' => 100,
        'page' => $page,
    ]);
    if (! $resp->successful()) {
        echo "List failed page {$page}: HTTP {$resp->status()}\n";
        break;
    }
    foreach ($resp->json()['results'] ?? [] as $item) {
        if (($item['product_code'] ?? '') === $sku) {
            $found = $item;
            echo "Found on page {$page}\n";
            break 2;
        }
    }
}

if (! $found) {
    echo "SKU [{$sku}] not found in TopDawg list (15 pages scanned).\n";
    exit(1);
}

echo "product_code: ".($found['product_code'] ?? '')."\n";
echo "tdid: ".($found['tdid'] ?? '')."\n";
echo "product_name: ".mb_substr((string) ($found['product_name'] ?? ''), 0, 120)."\n\n";

foreach ($found as $key => $value) {
    if (! preg_match('/bullet|feature|benefit|description|detail|spec/i', (string) $key)) {
        continue;
    }
    $preview = is_string($value) ? mb_substr(strip_tags($value), 0, 500) : json_encode($value);
    echo "{$key}:\n  {$preview}\n\n";
}

$metrics = DB::table('topdawg_metrics')->where('sku', $sku)->first();
if ($metrics && $metrics->bullet_points) {
    echo "--- PM saved bullets (topdawg_metrics, updated {$metrics->updated_at}) ---\n";
    echo $metrics->bullet_points."\n";
}
