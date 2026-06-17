<?php
require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = config('services.ebay3.app_id');
$secret = config('services.ebay3.cert_id');
$rt = config('services.ebay3.refresh_token');
echo 'EBAY 3 APP ID: '.substr($id, 0, 40).'...'.PHP_EOL;
$resp = \Illuminate\Support\Facades\Http::asForm()
    ->withBasicAuth($id, $secret)
    ->timeout(30)
    ->post('https://api.ebay.com/identity/v1/oauth2/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $rt,
    ]);
if (! $resp->successful()) {
    echo 'TOKEN FAIL: '.$resp->body();
    exit;
}
$tok = $resp->json('access_token');
echo 'TOKEN OK'.PHP_EOL;

// Try sell/account/v1/privilege which returns sellerRegistrationCompleted etc.
$u = \Illuminate\Support\Facades\Http::withToken($tok)
    ->timeout(30)
    ->get('https://api.ebay.com/commerce/identity/v1/user/');
echo 'IDENTITY STATUS: '.$u->status().PHP_EOL;
echo 'USER BODY: '.$u->body().PHP_EOL;

// Also try the legacy GetUser
$xml = '<?xml version="1.0" encoding="utf-8"?><GetUserRequest xmlns="urn:ebay:apis:eBLBaseComponents"><DetailLevel>ReturnAll</DetailLevel></GetUserRequest>';
$gu = \Illuminate\Support\Facades\Http::withHeaders([
    'X-EBAY-API-SITEID' => '0',
    'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
    'X-EBAY-API-CALL-NAME' => 'GetUser',
    'X-EBAY-API-IAF-TOKEN' => $tok,
])->withBody($xml, 'text/xml')->post('https://api.ebay.com/ws/api.dll');
echo 'GETUSER STATUS: '.$gu->status().PHP_EOL;
$body = $gu->body();
if (preg_match('#<UserID[^>]*>([^<]+)</UserID>#', $body, $m)) {
    echo 'UserID: '.$m[1].PHP_EOL;
}
if (preg_match('#<Email[^>]*>([^<]+)</Email>#', $body, $m)) {
    echo 'Email: '.$m[1].PHP_EOL;
}
if (preg_match('#<SiteID[^>]*>([^<]+)</SiteID>#', $body, $m)) {
    echo 'SiteID: '.$m[1].PHP_EOL;
}
if (preg_match('#<eBayGoodStanding[^>]*>([^<]+)</eBayGoodStanding>#', $body, $m)) {
    echo 'GoodStanding: '.$m[1].PHP_EOL;
}
echo PHP_EOL.'-----'.PHP_EOL;
echo 'EBAY 1 (services.ebay) APP ID: '.substr(config('services.ebay.app_id'), 0, 40).'...'.PHP_EOL;
$resp1 = \Illuminate\Support\Facades\Http::asForm()
    ->withBasicAuth(config('services.ebay.app_id'), config('services.ebay.cert_id'))
    ->timeout(30)
    ->post('https://api.ebay.com/identity/v1/oauth2/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => config('services.ebay.refresh_token'),
    ]);
if ($resp1->successful()) {
    $tok1 = $resp1->json('access_token');
    $gu1 = \Illuminate\Support\Facades\Http::withHeaders([
        'X-EBAY-API-SITEID' => '0',
        'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
        'X-EBAY-API-CALL-NAME' => 'GetUser',
        'X-EBAY-API-IAF-TOKEN' => $tok1,
    ])->withBody($xml, 'text/xml')->post('https://api.ebay.com/ws/api.dll');
    $body1 = $gu1->body();
    if (preg_match('#<UserID[^>]*>([^<]+)</UserID>#', $body1, $m)) {
        echo 'eBay 1 UserID: '.$m[1].PHP_EOL;
    }
    if (preg_match('#<Email[^>]*>([^<]+)</Email>#', $body1, $m)) {
        echo 'eBay 1 Email: '.$m[1].PHP_EOL;
    }
}
