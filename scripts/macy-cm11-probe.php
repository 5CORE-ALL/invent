<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

foreach ([
    ['shop_id' => 2851],
    ['shop_id' => 2851, 'status' => 'LIVE'],
    ['shop_id' => 2851, 'status' => 'NOT_LIVE'],
    ['shop_id' => 2851, 'provider_unique_identifier' => ['MS DBL G WH 2 PCS']],
    ['shop_id' => 2851, 'unique_identifier' => ['shop_sku|MS DBL G WH 2 PCS']],
    ['shop_id' => 2851, 'unique_identifier' => ['UPC|810199692883']],
] as $params) {
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(120)
        ->get("{$base}/api/mcm/products/sources/status/export", $params);
    echo "=== CM11 ".json_encode($params)." HTTP {$r->status()} ===\n";
    $body = $r->json();
    if (is_array($body)) {
        $items = $body['data'] ?? $body['products'] ?? $body;
        if (is_array($items)) {
            foreach (array_slice($items, 0, 3) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $uid = json_encode($item['unique_identifiers'] ?? $item['provider_unique_identifier'] ?? null);
                if (stripos($uid, 'MS DBL') === false && stripos($uid, '810199692883') === false) {
                    continue;
                }
                echo json_encode($item, JSON_PRETTY_PRINT)."\n";
            }
        }
        echo 'keys: '.implode(', ', array_keys($body))."\n";
        echo mb_substr(json_encode($body), 0, 2000)."\n";
    } else {
        echo mb_substr($r->body(), 0, 1000)."\n";
    }
    echo "\n";
}
