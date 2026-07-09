<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';

$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);
$m = $ref->getMethod('fetchMacyMiraklProduct');
$m->setAccessible(true);
$product = $m->invoke($svc, $sku);

if ($product === []) {
    echo "Connect product not found for [{$sku}]\n";
    exit(1);
}

echo 'id: '.($product['id'] ?? '?')."\n";
echo 'keys: '.implode(', ', array_keys($product))."\n";
echo 'category: '.json_encode($product['category'] ?? null)."\n";
echo 'brand: '.json_encode($product['brand'] ?? null)."\n";
echo 'gtins: '.json_encode($product['gtins'] ?? null)."\n";
echo 'titles: '.json_encode($product['titles'] ?? null)."\n\n";

foreach (($product['attributes'] ?? []) as $attr) {
    if (! is_array($attr)) {
        continue;
    }
    $id = (string) ($attr['id'] ?? '');
    $val = $attr['value'] ?? '';
    $preview = is_string($val) ? mb_substr($val, 0, 120) : json_encode($val);
    echo "{$id}: {$preview}\n";
}
