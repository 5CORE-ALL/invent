<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$url = "{$base}/api/products/attributes?all_operator_attributes=true";

echo "=== Macy MCM auth probe (PM11) ===\n\n";

echo "1) Mirakl Connect OAuth (MACY_CLIENT_ID + MACY_CLIENT_SECRET → auth.mirakl.net)\n";
$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
]);
echo '   Token HTTP '.$oauth->status()."\n";
$token = $oauth->json('access_token');
if ($token) {
    $bearer = Http::withoutVerifying()->withToken($token)->acceptJson()->timeout(30)->get($url);
    echo '   PM11 with Bearer token: HTTP '.$bearer->status()."\n";
    if (! $bearer->successful()) {
        echo '   → '.mb_substr($bearer->body(), 0, 120)."\n";
    }
} else {
    echo "   Token fetch failed\n";
}

echo "\n2) Macy MCM Shop API Key (PM11 doc: Authorization: YOUR_API_KEY — no Bearer)\n";
$mcmKey = trim((string) config('services.macy.mcm_api_key', ''));
if ($mcmKey === '') {
    echo "   Skipped — MACY_MCM_API_KEY not set in .env\n";
} else {
    $keyResp = Http::withoutVerifying()
        ->withHeaders(['Authorization' => $mcmKey, 'Accept' => 'application/json'])
        ->timeout(30)
        ->get($url);
    echo '   PM11 with Authorization header (raw key): HTTP '.$keyResp->status()."\n";
    if (! $keyResp->successful()) {
        echo '   → '.mb_substr($keyResp->body(), 0, 120)."\n";
    } else {
        $fb = 0;
        foreach (($keyResp->json('attributes') ?? []) as $attr) {
            $code = (string) ($attr['code'] ?? '');
            if (stripos($code, 'features_and_benefits') !== false) {
                $fb++;
            }
        }
        echo "   → OK (found {$fb} features_and_benefits_* attributes)\n";
    }
}

echo "\nNote: api.macys.com/oauth2 is Macy direct retail API — not used for Mirakl MCM (macysus-prod.mirakl.net).\n";
