<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';

$local = trim((string) (DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points') ?? ''));
if ($local === '') {
    $pm = ProductMaster::where('sku', $sku)->orWhere('SKU', $sku)->first();
    if ($pm) {
        $local = collect(['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'])
            ->map(fn ($c) => trim((string) ($pm->{$c} ?? '')))
            ->filter(fn ($v) => $v !== '')
            ->implode("\n");
    }
}

$localLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $local) ?: [])));

echo "=== Local (macy_metrics / product_master): {$sku} ===\n";
foreach ($localLines as $i => $line) {
    echo ($i + 1).'. '.mb_substr($line, 0, 120).(mb_strlen($line) > 120 ? '…' : '')."\n";
}
echo 'local hash: '.sha1(implode("\n", array_slice($localLines, 0, 5)))."\n\n";

$companyId = trim((string) config('services.macy.company_id'));
$tokenResp = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => $companyId,
]);
$token = $tokenResp->json('access_token');
if (! $token) {
    echo "OAuth failed\n";
    exit(1);
}

$headers = ['Accept' => 'application/json', 'channel_id' => $companyId];
$list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
    ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);

$connectLines = [];
foreach (($list->json('data') ?? []) as $product) {
    if (strcasecmp((string) ($product['id'] ?? ''), $sku) !== 0) {
        continue;
    }
    for ($i = 1; $i <= 5; $i++) {
        $val = '';
        foreach (($product['attributes'] ?? []) as $attr) {
            if (is_array($attr) && strcasecmp((string) ($attr['id'] ?? ''), "features_and_benefits_bullet_{$i}") === 0) {
                $val = trim((string) ($attr['value'] ?? ''));
            }
        }
        if ($val !== '') {
            $connectLines[] = $val;
        }
    }
    break;
}

echo "=== Live Mirakl Connect (macys): {$sku} ===\n";
if ($connectLines === []) {
    echo "(product not found or no F&B bullets)\n";
} else {
    foreach ($connectLines as $i => $line) {
        echo ($i + 1).'. '.mb_substr($line, 0, 120).(mb_strlen($line) > 120 ? '…' : '')."\n";
    }
}
echo 'connect hash: '.sha1(implode("\n", array_slice($connectLines, 0, 5)))."\n\n";

$mismatches = [];
for ($i = 0; $i < 5; $i++) {
    $l = mb_substr($localLines[$i] ?? '', 0, 254);
    $c = mb_substr($connectLines[$i] ?? '', 0, 254);
    if ($l !== '' && strcasecmp($l, $c) !== 0) {
        $mismatches[] = $i + 1;
    }
}

if ($mismatches === [] && $localLines !== [] && $connectLines !== []) {
    echo "MATCH: Local and Connect bullets are the same.\n";
} elseif ($mismatches !== []) {
    echo 'MISMATCH on slot(s): '.implode(', ', $mismatches)."\n";
    foreach ($mismatches as $slot) {
        $idx = $slot - 1;
        echo "\n--- Slot {$slot} LOCAL ---\n".($localLines[$idx] ?? '(empty)')."\n";
        echo "--- Slot {$slot} CONNECT ---\n".($connectLines[$idx] ?? '(empty)')."\n";
    }
} else {
    echo "Could not compare (missing local or connect data).\n";
}
