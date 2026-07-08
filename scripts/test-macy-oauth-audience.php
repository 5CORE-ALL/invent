<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$cid = config('services.macy.client_id');
$sec = config('services.macy.client_secret');
$company = config('services.macy.company_id');

echo 'MACY_COMPANY_ID: '.($company ?: '(empty)')."\n";

$without = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => $cid,
    'client_secret' => $sec,
]);
echo 'Without audience (doc says invalid): HTTP '.$without->status()."\n";
if ($without->successful()) {
    $j = $without->json();
    echo '  expires_in='.($j['expires_in'] ?? '?').' company_id='.($j['company_id'] ?? '?')."\n";
} else {
    echo '  '.$without->body()."\n";
}

$with = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => $cid,
    'client_secret' => $sec,
    'audience' => $company,
]);
echo 'With audience (per Mirakl doc): HTTP '.$with->status()."\n";
if ($with->successful()) {
    $j = $with->json();
    echo '  expires_in='.($j['expires_in'] ?? '?').' company_id='.($j['company_id'] ?? '?')."\n";
} else {
    echo '  '.$with->body()."\n";
}
