<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$push = in_array('--push', $argv, true);

$token = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
])->json()['access_token'] ?? null;

if (! $token) {
    echo "Auth failed\n";
    exit(1);
}

$channelId = config('services.macy.company_id');
$headers = [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
    'channel_id' => $channelId,
];

echo "SKU: {$sku}\n";
echo "channel_id: {$channelId}\n\n";

$direct = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
    ->get('https://miraklconnect.com/api/products/'.rawurlencode($sku));

echo "=== Direct GET /api/products/{sku} ===\n";
echo 'HTTP '.$direct->status()."\n";

$directJson = $direct->json();
$directAttrs = $directJson['attributes'] ?? $directJson['data']['attributes'] ?? [];

dumpMacyBulletAttrs('direct', $directAttrs);

$list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
    ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);

$found = null;
foreach (($list->json('data') ?? []) as $product) {
    if (strcasecmp((string) ($product['id'] ?? ''), $sku) === 0) {
        $found = $product;
        break;
    }
}

echo "\n=== List GET ?channel_code=macys ===\n";
echo 'HTTP '.$list->status()."\n";
if ($found === null) {
    echo "Product NOT found in first 1000 list page\n";
} else {
    dumpMacyBulletAttrs('list', $found['attributes'] ?? []);
    $desc = '';
    foreach (($found['descriptions'] ?? []) as $row) {
        if (is_array($row) && trim((string) ($row['value'] ?? '')) !== '') {
            $desc = trim((string) $row['value']);
            break;
        }
    }
    echo "\nList description preview:\n  ".mb_substr($desc, 0, 200)."\n";
}

$pm = ProductMaster::where('SKU', $sku)->orWhere('sku', $sku)->first();
$pmLines = [];
foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
    $v = trim((string) ($pm->{$col} ?? ''));
    if ($v !== '') {
        $pmLines[] = $v;
    }
}

echo "\n=== PM bullets ===\n";
foreach ($pmLines as $i => $line) {
    echo '  '.($i + 1).'. '.mb_substr($line, 0, 100)."\n";
}

if ($push) {
    echo "\n=== Live push via BulletPointMasterController ===\n";
    $controller = app(BulletPointMasterController::class);
    $text = implode("\n", $pmLines);
    $res = $controller->update(new Request([
        'sku' => $sku,
        'updates' => [['marketplace' => 'macy', 'bullet_points' => $text]],
    ]));
    $r = $res->getData(true)['results']['macy'] ?? [];
    echo (($r['success'] ?? false) ? 'OK' : 'FAIL').': '.($r['message'] ?? '')."\n";
}

function dumpMacyBulletAttrs(string $label, mixed $attrs): void
{
    if (! is_array($attrs)) {
        echo "{$label}: no attributes array\n";

        return;
    }

    if (isset($attrs[0]) && is_array($attrs[0]) && isset($attrs[0]['id'])) {
        foreach ($attrs as $attr) {
            $id = strtolower((string) ($attr['id'] ?? ''));
            if (str_contains($id, 'bullet') || str_contains($id, 'feature') || str_contains($id, 'about')
                || str_contains($id, 'description') || $id === 'bulletpoints') {
                echo "  {$attr['id']}: ".mb_substr((string) ($attr['value'] ?? ''), 0, 120)."\n";
            }
        }

        return;
    }

    foreach ($attrs as $key => $value) {
        $id = strtolower((string) $key);
        if (str_contains($id, 'bullet') || str_contains($id, 'feature') || str_contains($id, 'about')
            || str_contains($id, 'description')) {
            echo "  {$key}: ".mb_substr((string) $value, 0, 120)."\n";
        }
    }
}
