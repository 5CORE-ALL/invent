<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';

$pm = DB::table('product_master')->where('sku', $sku)->first();
$desc = trim((string) ($pm->description_600 ?? ''));
if ($desc === '') {
    $desc = trim((string) ($pm->description_1500 ?? $pm->description_1000 ?? $pm->description_800 ?? $pm->product_description ?? ''));
}
if ($desc === '') {
    $desc = trim((string) (DB::table('macy_metrics')->where('sku', $sku)->value('description_master') ?? ''));
}
$svc = app(MacysApiService::class);
if ($desc === '') {
    $live = $svc->fetchDescriptionHtml($sku);
    if ($live['success'] ?? false) {
        $desc = trim((string) ($live['html'] ?? ''));
        echo "NOTE: No PM description — using live Connect description as push payload.\n";
    }
}
if ($desc === '') {
    exit("No description for {$sku}\n");
}

echo "=== Hybrid description push: {$sku} ===\n";
echo 'description chars: '.mb_strlen($desc)."\n";
echo 'preview: '.mb_substr(strip_tags($desc), 0, 120)."...\n\n";

$svc = app(MacysApiService::class);

// Connect BEFORE
$before = $svc->fetchDescriptionHtml($sku);
$beforeHtml = ($before['success'] ?? false) ? trim((string) ($before['html'] ?? '')) : '';
echo 'Connect BEFORE chars: '.mb_strlen($beforeHtml)."\n";
echo 'Match PM before push: '.(strcasecmp(mb_substr($beforeHtml, 0, 80), mb_substr($desc, 0, 80)) === 0 ? 'YES' : 'NO')."\n\n";

$result = $svc->updateDescription($sku, $desc);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";

$importId = (int) ($result['import_id'] ?? 0);
if ($importId > 0) {
    echo "=== P42 import #{$importId} ===\n";
    passthru('php '.escapeshellarg(__DIR__.'/macy-mcm-import-status.php').' '.escapeshellarg((string) $importId));
    echo "\n";
}

echo "=== Connect AFTER (poll) ===\n";
for ($i = 1; $i <= 6; $i++) {
    sleep(10);
    $after = $svc->fetchDescriptionHtml($sku);
    $afterHtml = ($after['success'] ?? false) ? trim((string) ($after['html'] ?? '')) : '';
    $match = $afterHtml !== '' && strcasecmp(mb_substr($afterHtml, 0, 80), mb_substr($desc, 0, 80)) === 0;
    echo "poll {$i}: ".($match ? 'MATCH' : 'pending').' ('.mb_strlen($afterHtml)." chars)\n";
    if ($match) {
        break;
    }
}

echo "\n=== MCM productLongDescription ===\n";
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$upc = trim((string) (DB::table('macys_price_data')->where('sku', $sku)->value('upc') ?? ''));
if ($upc !== '') {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/products", [
            'shop_id' => (int) config('services.macy.shop_id', 2851),
            'product_references' => 'UPC|'.rawurlencode($upc),
            'max' => 1,
            'all_operator_attributes' => 'true',
        ]);
    $mcmDesc = '';
    foreach (($r->json('products.0.product_attributes') ?? []) as $attr) {
        if (is_array($attr) && strcasecmp((string) ($attr['code'] ?? ''), 'productLongDescription') === 0) {
            $mcmDesc = trim((string) ($attr['value'] ?? ''));
            break;
        }
    }
    echo 'MCM chars: '.mb_strlen($mcmDesc)."\n";
    echo 'MCM match PM: '.($mcmDesc !== '' && strcasecmp(mb_substr($mcmDesc, 0, 80), mb_substr($desc, 0, 80)) === 0 ? 'YES' : 'NO/pending')."\n";
} else {
    echo "(no UPC for MCM lookup)\n";
}
