<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'PL 1002';
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$shopId = (int) config('services.macy.shop_id', 2851);

$local = trim((string) (DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points') ?? ''));
$localLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $local) ?: [])));

$companyId = trim((string) config('services.macy.company_id'));
$token = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => $companyId,
])->json('access_token');

$connectLines = [];
$list = Http::withoutVerifying()->withToken($token)->withHeaders(['Accept' => 'application/json', 'channel_id' => $companyId])
    ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);
foreach (($list->json('data') ?? []) as $product) {
    if (strcasecmp((string) ($product['id'] ?? ''), $sku) !== 0) {
        continue;
    }
    for ($i = 1; $i <= 5; $i++) {
        foreach (($product['attributes'] ?? []) as $attr) {
            if (is_array($attr) && strcasecmp((string) ($attr['id'] ?? ''), "features_and_benefits_bullet_{$i}") === 0) {
                $connectLines[$i - 1] = trim((string) ($attr['value'] ?? ''));
            }
        }
    }
    break;
}

echo "=== Bullet sync check: {$sku} ===\n\n";
echo "Connect (features_and_benefits_bullet_*):\n";
for ($i = 0; $i < 5; $i++) {
    $l = $localLines[$i] ?? '';
    $c = $connectLines[$i] ?? '';
    echo '  Slot '.($i + 1).': '.($l === $c ? 'MATCH' : 'MISMATCH')."\n";
    if ($l !== $c) {
        echo "    LOCAL:   ".mb_substr($l, 0, 100)."\n";
        echo "    CONNECT: ".mb_substr($c, 0, 100)."\n";
    }
}

$headers = ['Authorization' => $key, 'Accept' => 'application/json'];
$mcmLines = [];
foreach (['shop_sku|'.rawurlencode($sku)] as $ref) {
    $r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", [
        'shop_id' => $shopId,
        'product_references' => $ref,
        'max' => 1,
        'all_operator_attributes' => 'true',
    ]);
    $prod = $r->json('products.0') ?? [];
    if ($prod === []) {
        continue;
    }
    foreach (($prod['product_attributes'] ?? []) as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        if (preg_match('/^fnb(\d+)$/i', (string) ($attr['code'] ?? ''), $m)) {
            $mcmLines[(int) $m[1] - 1] = trim((string) ($attr['value'] ?? ''));
        }
    }
}

echo "\nMCM seller product (fnb1-fnb5 via API):\n";
if ($mcmLines === [] && ! isset($r)) {
    echo "  (no seller product returned by MCM API)\n";
} elseif ($mcmLines === []) {
    echo "  (seller product found but no fnb* attributes in API response)\n";
} else {
    for ($i = 0; $i < 5; $i++) {
        $l = $localLines[$i] ?? '';
        $m = $mcmLines[$i] ?? '';
        echo '  fnb'.($i + 1).': '.($m === '' ? '(empty)' : ($l === $m ? 'MATCH' : 'MISMATCH'))."\n";
        if ($m !== '' && $l !== $m) {
            echo "    LOCAL: ".mb_substr($l, 0, 100)."\n";
            echo "    MCM:   ".mb_substr($m, 0, 100)."\n";
        }
    }
}

$cm11 = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/mcm/products/sources/status/export", [
        'shop_id' => $shopId,
        'provider_unique_identifier' => [$sku],
    ]);
echo "\nCM11 status:\n";
foreach ($cm11->json() ?? [] as $item) {
    if (! is_array($item)) {
        continue;
    }
    if (strcasecmp((string) ($item['provider_unique_identifier'] ?? ''), $sku) !== 0) {
        continue;
    }
    echo '  status: '.($item['status'] ?? '?')."\n";
    if (! empty($item['errors'])) {
        echo '  errors: '.json_encode($item['errors'])."\n";
    }
    if (! empty($item['warnings'])) {
        echo '  warnings: '.json_encode($item['warnings'])."\n";
    }
}
