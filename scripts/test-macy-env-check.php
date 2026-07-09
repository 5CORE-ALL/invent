<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cid = (string) config('services.macy.client_id');
$sec = (string) config('services.macy.client_secret');
$company = (string) config('services.macy.company_id');

echo 'client_id: '.($cid === '' ? 'MISSING' : 'set ('.strlen($cid).' chars)')."\n";
echo 'client_secret: '.($sec === '' ? 'MISSING' : 'set ('.strlen($sec).' chars)')."\n";
echo 'company_id: '.($company === '' ? 'MISSING' : 'set ('.strlen($company).' chars)')."\n";
echo 'mcm_api_key: '.(config('services.macy.mcm_api_key') ? 'set' : 'not set')."\n";
