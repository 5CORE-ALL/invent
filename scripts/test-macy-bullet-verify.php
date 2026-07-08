<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;

$sku = $argv[1] ?? 'GSTOOL BLK';
$service = app(MacysApiService::class);

$saved = (string) DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points');
$savedLines = array_slice(array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $saved) ?: []))), 0, 5);

echo "SKU: {$sku}\n";
echo "Saved bullets (local): ".count($savedLines)." line(s)\n";
foreach ($savedLines as $i => $line) {
    echo '  '.($i + 1).'. '.mb_substr($line, 0, 80).(mb_strlen($line) > 80 ? '…' : '')."\n";
}

$result = $service->updateBulletPoints($sku, $saved);
echo "\nPush check: ".(($result['success'] ?? false) ? 'SUCCESS' : 'FAILED')."\n";
echo 'Message: '.($result['message'] ?? '')."\n";
echo 'Connect verified: '.(($result['connect_verified'] ?? false) ? 'yes' : 'no')."\n";
