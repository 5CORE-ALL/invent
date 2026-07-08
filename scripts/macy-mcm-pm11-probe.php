<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$key = trim((string) config('services.macy.mcm_api_key', ''));

if ($key === '') {
    echo "Set MACY_MCM_API_KEY in .env first.\n";
    exit(1);
}

$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$query = ['all_operator_attributes' => 'true'];
if (($shopId = config('services.macy.shop_id')) !== null && $shopId !== '') {
    $query['shop_id'] = (int) $shopId;
}

$hierarchy = null;
if (\Illuminate\Support\Facades\Schema::hasTable('macys_price_data')) {
    $row = \Illuminate\Support\Facades\DB::table('macys_price_data')
        ->where('product_sku', $sku)->orWhere('sku', $sku)->first();
    $hierarchy = trim((string) ($row->category_code ?? '')) ?: null;
}
if ($hierarchy !== null) {
    $query['hierarchy'] = $hierarchy;
    echo "SKU: {$sku} | hierarchy: {$hierarchy}\n\n";
} else {
    echo "SKU: {$sku} | hierarchy: (all)\n\n";
}

echo "=== PM11 GET /api/products/attributes ===\n";
$pm11 = Http::withoutVerifying()
    ->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
    ->timeout(60)
    ->get("{$base}/api/products/attributes", $query);

echo 'HTTP '.$pm11->status()."\n";
if (! $pm11->successful()) {
    echo mb_substr($pm11->body(), 0, 500)."\n";
    exit(1);
}

$fb = [];
foreach (($pm11->json('attributes') ?? []) as $attr) {
    if (! is_array($attr)) {
        continue;
    }
    $code = (string) ($attr['code'] ?? '');
    $label = (string) ($attr['label'] ?? '');
    if (stripos($code, 'features_and_benefits') !== false
        || (stripos($label, 'features') !== false && stripos($label, 'benefit') !== false)) {
        $fb[] = ['code' => $code, 'label' => $label, 'max' => collect($attr['type_parameters'] ?? [])
            ->firstWhere('name', 'MAX_LENGTH')['value'] ?? null];
    }
}

if ($fb === []) {
    echo "No Features & Benefits attributes found.\n";
} else {
    foreach ($fb as $row) {
        echo "  {$row['code']} | {$row['label']}".($row['max'] ? " | max={$row['max']}" : '')."\n";
    }
}
