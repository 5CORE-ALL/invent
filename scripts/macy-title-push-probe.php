<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$title = trim((string) (DB::table('product_master')->where('sku', $sku)->value('title60') ?? ''));
if ($title === '') {
    exit("No title60\n");
}

echo "SKU: {$sku}\nTitle to push: {$title}\n\n";

$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);

// Raw Connect upsert to inspect response
$getToken = $ref->getMethod('getAccessToken');
$getToken->setAccessible(true);
$token = $getToken->invoke($svc);
$companyId = config('services.macy.company_id');
$payload = [
    'products' => [[
        'id' => $sku,
        'titles' => [['value' => mb_substr($title, 0, 150), 'locale' => 'en_US']],
    ]],
];
$r = Http::withoutVerifying()->withToken($token)->withHeaders([
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
    'channel_id' => (string) $companyId,
])->timeout(60)->post('https://miraklconnect.com/api/products', $payload);

echo "POST upsert HTTP {$r->status()}\n";
echo mb_substr($r->body(), 0, 3000)."\n\n";

for ($i = 1; $i <= 6; $i++) {
    sleep(10);
    $fetch = $ref->getMethod('fetchMacyMiraklProduct');
    $fetch->setAccessible(true);
    $p = $fetch->invoke($svc, $sku);
    $live = '';
    foreach ((array) ($p['titles'] ?? []) as $t) {
        if (is_array($t) && trim((string) ($t['value'] ?? '')) !== '') {
            $live = trim((string) $t['value']);
            break;
        }
    }
    $match = strcasecmp($live, $title) === 0 ? 'MATCH' : 'no';
    echo "poll {$i} (".($i * 10)."s): {$match} | ".mb_substr($live, 0, 80)."\n";
    if ($match === 'MATCH') {
        break;
    }
}
