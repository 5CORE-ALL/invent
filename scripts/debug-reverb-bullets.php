<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = App\Services\ReverbApiService::getReverbBearerToken();
$skus = array_slice($argv, 1) ?: ['GSTOOL BLK', '2U1O-C-1', 'CM MOB 2M'];
$push = false;
if (($key = array_search('--push', $skus, true)) !== false) {
    $push = true;
    unset($skus[$key]);
    $skus = array_values($skus);
}

foreach ($skus as $sku) {
    $p = App\Models\ReverbProduct::query()
        ->where('sku', $sku)
        ->orWhere('sku', strtoupper($sku))
        ->first();
    $lid = $p?->reverb_listing_id;
    echo "=== {$sku} listing {$lid} ===\n";
    if (! $token || ! $lid) {
        echo "no token or listing\n\n";
        continue;
    }

    $r = Illuminate\Support\Facades\Http::withoutVerifying()
        ->timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ])
        ->get('https://api.reverb.com/api/listings/'.$lid);

    $j = $r->json();
    $listing = $j['listing'] ?? $j;
    $desc = (string) ($listing['description'] ?? '');
    $plain = (string) ($listing['plain_text_description'] ?? '');
    $state = is_scalar($listing['state'] ?? null) ? (string) $listing['state'] : json_encode($listing['state'] ?? 'n/a');
    $inv = is_scalar($listing['inventory'] ?? null) ? (string) $listing['inventory'] : json_encode($listing['inventory'] ?? 'n/a');
    $msg = $j['message'] ?? '';

    echo "state={$state} inv={$inv} message={$msg}\n";
    echo 'has Highlighted Features: '.(str_contains($desc, 'Highlighted Features') ? 'yes' : 'no')."\n";
    echo 'has bracket bullets: '.(preg_match('/【/u', $desc) ? 'yes' : 'no')."\n";
    echo 'desc_start: '.mb_substr($desc, 0, 400)."\n";

    $pm = App\Models\ProductMaster::query()
        ->where('sku', $sku)
        ->orWhere('sku', strtoupper($sku))
        ->first();
    if ($pm) {
        $b1 = trim((string) ($pm->bullet1 ?? ''));
        $label = $b1;
        if (preg_match('/^(.+?)\s+-\s+/u', $b1, $m)) {
            $label = $m[1];
        }
        echo 'PM bullet1: '.mb_substr($b1, 0, 150)."\n";
        echo 'PM bullet1 on live: '.(str_contains($desc, mb_substr($b1, 0, 40)) || str_contains($plain, mb_substr($b1, 0, 40)) ? 'yes' : 'no')."\n";
        if ($label !== $b1) {
            echo 'PM label on live: '.(str_contains($desc, $label) || str_contains($plain, $label) ? 'yes' : 'no')."\n";
        }
    }

    if ($push && $pm) {
        $bulletText = implode("\n", array_filter([
            $pm->bullet1 ?? '',
            $pm->bullet2 ?? '',
            $pm->bullet3 ?? '',
            $pm->bullet4 ?? '',
            $pm->bullet5 ?? '',
        ], fn ($b) => trim((string) $b) !== ''));
        $res = app(App\Services\ReverbApiService::class)->updateBulletPoints($sku, $bulletText);
        echo 'PUSH: '.json_encode($res, JSON_UNESCAPED_UNICODE)."\n";
    }
    echo "\n";

    if ($sku === 'CM MOB 2M' && $desc !== '') {
        $features = [];
        foreach ([$pm->bullet1 ?? '', $pm->bullet2 ?? '', $pm->bullet3 ?? '', $pm->bullet4 ?? '', $pm->bullet5 ?? ''] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $features[] = $line;
            }
        }
        $svc = app(App\Services\ReverbApiService::class);
        $ref = new ReflectionClass($svc);
        $m = $ref->getMethod('replaceReverbHighlightedFeaturesBlock');
        $m->setAccessible(true);
        $out = $m->invoke($svc, $desc, $features);
        echo "--- simulated replace ---\n";
        echo 'out len: '.strlen($out)."\n";
        echo 'out_start: '.mb_substr($out, 0, 300)."\n";
        echo 'HF pos in out: '.strpos($out, 'Highlighted Features')."\n";
        echo 'bracket pos in out: '.strpos($out, '【')."\n";
        $hfPos = strpos($desc, 'Highlighted Features');
        if ($hfPos !== false) {
            echo 'HF context in current: '.mb_substr($desc, max(0, $hfPos - 80), 250)."\n";
        }
    }
}
