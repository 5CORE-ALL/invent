<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'SP 12120 4OHM GTR';
$push = in_array('--push', $argv, true);

$pm = ProductMaster::where('SKU', $sku)->orWhere('sku', $sku)->first();
$pmLines = [];
foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
    $v = trim((string) ($pm->{$col} ?? ''));
    if ($v !== '') {
        $pmLines[] = $v;
    }
}
$pmText = implode("\n", $pmLines);
$pmHash = sha1($pmText);

echo "PM bullets ({$sku}) sha1: {$pmHash}\n";
foreach ($pmLines as $i => $line) {
    echo '  '.($i + 1).'. '.mb_substr($line, 0, 90)."\n";
}
echo "\n";

$token = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
])->json()['access_token'] ?? null;

$channels = ['macy' => ['code' => 'macys', 'channel_id' => config('services.macy.company_id'), 'push_key' => 'macy'],
    'bestbuy' => ['code' => 'bestbuyusa', 'channel_id' => 'bestbuyusa', 'push_key' => 'bestbuy']];

$needsPush = [];

foreach ($channels as $label => $cfg) {
    $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'channel_id' => $cfg['channel_id']];
    $found = null;
    $list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
        ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => $cfg['code']]);
    foreach (($list->json('data') ?? []) as $product) {
        if (strcasecmp((string) ($product['id'] ?? ''), $sku) === 0) {
            $found = $product;
            break;
        }
    }

    $connectLines = [];
    foreach (($found['attributes'] ?? []) as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        $id = strtolower((string) ($attr['id'] ?? ''));
        if ($id === 'bulletpoints') {
            $connectLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($attr['value'] ?? '')) ?: [])));
        }
    }
    if ($connectLines === [] && $label === 'macy') {
        for ($i = 1; $i <= 5; $i++) {
            foreach (($found['attributes'] ?? []) as $attr) {
                if (is_array($attr) && strtolower((string) ($attr['id'] ?? '')) === "features_and_benefits_bullet_{$i}") {
                    $val = trim((string) ($attr['value'] ?? ''));
                    if ($val !== '') {
                        $connectLines[] = $val;
                    }
                }
            }
        }
    }
    $connectText = implode("\n", $connectLines);
    $connectHash = sha1($connectText);

    echo "=== {$label} ===\n";
    echo "Connect sha1: {$connectHash}\n";
    echo 'Connect lines: '.count($connectLines)."\n";
    if ($pmHash === $connectHash) {
        echo "STATUS: IN SYNC (full text match)\n\n";
    } else {
        echo "STATUS: OUT OF SYNC — PM and Connect differ\n";
        foreach ($connectLines as $i => $line) {
            echo '  connect '.($i + 1).'. '.mb_substr($line, 0, 90)."\n";
        }
        echo "\n";
        $needsPush[] = $cfg['push_key'];
    }
}

if ($needsPush === [] && ! $push) {
    echo "No push needed — Mirakl Connect already matches PM.\n";
    echo "If live Macy/Best Buy site still looks old, check Connect channel sync (not push code).\n";
    exit(0);
}

if (! $push && $needsPush !== []) {
    echo 'Run with --push to push: '.implode(', ', $needsPush)."\n";
    exit(0);
}

if ($push) {
    $controller = app(BulletPointMasterController::class);
    foreach ($needsPush as $mp) {
        echo "--- Pushing {$mp} ---\n";
        $res = $controller->update(new Request([
            'sku' => $sku,
            'updates' => [['marketplace' => $mp, 'bullet_points' => '']],
        ]));
        $r = $res->getData(true)['results'][$mp] ?? [];
        echo (($r['success'] ?? false) ? 'OK' : 'FAIL').': '.($r['message'] ?? '')."\n\n";
    }
}
