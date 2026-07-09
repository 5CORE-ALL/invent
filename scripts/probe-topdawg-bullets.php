<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$token = config('services.topdawg.token');
$base = rtrim((string) config('services.topdawg.base_url'), '/');
$headers = [
    'Authorization' => 'Bearer '.$token,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
];

$searches = ['GFF TP BLK', 'SP 12120', '12120 4OHM'];
foreach ($searches as $search) {
    $resp = Http::withHeaders($headers)->timeout(60)->post($base.'/SupplierProduct/list', [
        'per_page' => 100,
        'page' => 1,
        'search' => $search,
    ]);
    $items = $resp->json()['results'] ?? [];
    echo "Search [{$search}]: HTTP {$resp->status()}, hits=".count($items)."\n";
    foreach (array_slice($items, 0, 3) as $item) {
        echo '  '.json_encode([
            'product_code' => $item['product_code'] ?? null,
            'tdid' => $item['tdid'] ?? null,
            'product_name' => mb_substr((string) ($item['product_name'] ?? ''), 0, 60),
        ])."\n";
    }
}

$found = null;
for ($page = 1; $page <= 10 && ! $found; $page++) {
    $resp = Http::withHeaders($headers)->timeout(60)->post($base.'/SupplierProduct/list', [
        'per_page' => 100,
        'page' => $page,
    ]);
    foreach ($resp->json()['results'] ?? [] as $item) {
        if (($item['product_code'] ?? '') === 'GFF TP BLK') {
            $found = $item;
            break 2;
        }
    }
}

if ($found) {
    echo "\nGFF TP BLK — all API keys:\n";
    echo '  '.implode(', ', array_keys($found))."\n\n";
    echo "Bullet/description-related values:\n";
    foreach ($found as $key => $value) {
        if (! preg_match('/bullet|feature|benefit|description|detail|spec/i', (string) $key)) {
            continue;
        }
        $preview = is_string($value) ? mb_substr($value, 0, 300) : json_encode($value);
        echo "  {$key}: {$preview}\n";
    }
} else {
    echo "\nGFF TP BLK not found in first 10 pages of list.\n";
}
