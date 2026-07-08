<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';

$local = trim((string) (DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points') ?? ''));
$localLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $local) ?: [])));

$companyId = trim((string) config('services.macy.company_id'));
$token = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => $companyId,
])->json('access_token');

$headers = ['Accept' => 'application/json', 'channel_id' => $companyId];
$list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
    ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);

$connectLines = [];
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

$row = DB::table('macy_metrics')->where('sku', $sku)->first();
echo "SKU: {$sku}\n";
echo 'macy_metrics updated_at: '.($row->updated_at ?? 'n/a')."\n\n";

for ($i = 0; $i < 5; $i++) {
    $l = $localLines[$i] ?? '';
    $c = $connectLines[$i] ?? '';
    $exact = $l === $c;
    $ci = strcasecmp($l, $c) === 0;
    echo 'Slot '.($i + 1).': '.($exact ? 'EXACT MATCH' : ($ci ? 'match (case diff)' : 'MISMATCH'))."\n";
    if (! $exact) {
        if ($l !== $c) {
            echo "  LOCAL:   {$l}\n";
            echo "  CONNECT: {$c}\n";
        }
    }
}
