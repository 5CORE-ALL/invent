<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = new App\Services\TopDawgApiService();
$r = new ReflectionMethod($s, 'resolveProductCode');
$r->setAccessible(true);

foreach (['GSTOOL BLK', 'GFF TP BLK', 'NOT-ON-TD'] as $sku) {
    echo "{$sku} => ".$r->invoke($s, $sku)."\n";
}
