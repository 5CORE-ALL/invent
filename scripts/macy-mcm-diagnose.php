<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

echo "=== Macy MCM diagnostic ===\n\n";
echo 'MCM key set: '.($key !== '' ? 'yes ('.strlen($key).' chars)' : 'NO')."\n";
echo 'MCM base URL: '.$base."\n";
echo 'MACY_SHOP_ID: '.(config('services.macy.shop_id') ?: '(not set)')."\n\n";

if ($key === '') {
    echo "Set MACY_MCM_API_KEY in .env first.\n";
    exit(1);
}

$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$endpoints = [
    'V01 version (health)' => "{$base}/api/version",
    'PC01 platform config' => "{$base}/api/platform/configuration",
    'PM11 attributes (minimal)' => "{$base}/api/products/attributes",
    'PM11 + all_operator_attributes' => "{$base}/api/products/attributes?all_operator_attributes=true",
    'A01 account' => "{$base}/api/account",
    'Shops list' => "{$base}/api/shops",
    'Offers (max=1)' => "{$base}/api/offers?max=1",
];

foreach ($endpoints as $label => $url) {
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(30)->get($url);
    echo str_pad($label, 34).' HTTP '.$r->status();
    if ($r->successful()) {
        $j = $r->json();
        if (str_contains($label, 'Shops') && is_array($j)) {
            $shops = $j['shops'] ?? $j;
            if (is_array($shops) && isset($shops[0])) {
                $s = $shops[0];
                echo ' | shop_id='.($s['shop_id'] ?? $s['id'] ?? '?').' name='.($s['shop_name'] ?? $s['name'] ?? '?');
            }
        }
        if (str_contains($label, 'version') && is_array($j)) {
            echo ' | '.mb_substr(json_encode($j), 0, 80);
        }
    } else {
        echo ' | '.mb_substr(str_replace(["\n", "\r"], ' ', $r->body()), 0, 100);
    }
    echo "\n";
}

echo "\nV01 without Authorization header:\n";
$v01 = Http::withoutVerifying()->acceptJson()->timeout(15)->get("{$base}/api/version");
echo '  HTTP '.$v01->status()."\n";

echo "\nPM11 without Authorization header:\n";
$pm11NoAuth = Http::withoutVerifying()->acceptJson()->timeout(15)
    ->get("{$base}/api/products/attributes?all_operator_attributes=true");
echo '  HTTP '.$pm11NoAuth->status().' | '.mb_substr($pm11NoAuth->body(), 0, 80)."\n";

echo "\nPM11 with INVALID fake key:\n";
$pm11Fake = Http::withoutVerifying()->withHeaders(['Authorization' => '00000000-0000-0000-0000-000000000000', 'Accept' => 'application/json'])
    ->get("{$base}/api/products/attributes?all_operator_attributes=true");
echo '  HTTP '.$pm11Fake->status().' | '.mb_substr($pm11Fake->body(), 0, 80)."\n";

// Wrong host check — Connect company ID as key would fail everywhere
echo "\nIf ALL endpoints return 403:\n";
echo "  - Key may be from wrong portal (Mirakl Connect vs macysus-prod)\n";
echo "  - Sub-user may lack API permissions (try master account)\n";
echo "  - Key may need regeneration on macysus-prod.mirakl.net/user/api-key\n";
