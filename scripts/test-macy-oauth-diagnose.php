<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cid = (string) config('services.macy.client_id');
$sec = (string) config('services.macy.client_secret');
$company = (string) config('services.macy.company_id');

echo "client_id length: ".strlen($cid)."\n";
echo "client_secret length: ".strlen($sec)."\n";
echo "company_id length: ".strlen($company)."\n";
echo "client_id has whitespace: ".(preg_match('/\s/', $cid) ? 'yes' : 'no')."\n";
echo "secret has whitespace: ".(preg_match('/\s/', $sec) ? 'yes' : 'no')."\n";
echo "client_id prefix: ".substr($cid, 0, 4)."… suffix: …".substr($cid, -4)."\n";
echo "company_id: {$company}\n";

// Raw .env parse (no cache)
$envPath = base_path('.env');
$raw = file_exists($envPath) ? file_get_contents($envPath) : '';
preg_match('/^MACY_CLIENT_ID=(.*)$/m', $raw, $m1);
preg_match('/^MACY_CLIENT_SECRET=(.*)$/m', $raw, $m2);
$rawCid = trim($m1[1] ?? '', "\"' \t\r\n");
$rawSec = trim($m2[1] ?? '', "\"' \t\r\n");
echo "raw vs config client_id match: ".($rawCid === $cid ? 'yes' : 'NO')."\n";
echo "raw vs config secret match: ".($rawSec === $sec ? 'yes' : 'NO')."\n";

use Illuminate\Support\Facades\Http;

$resp = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => $cid,
    'client_secret' => $sec,
    'audience' => $company,
]);

echo "OAuth HTTP: ".$resp->status()."\n";
echo $resp->body()."\n";
