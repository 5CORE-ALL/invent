<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductMaster;
use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;

$sku = $argv[1] ?? 'GSTOOL BLK';

$bullets = DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points');
if (! $bullets) {
    $p = ProductMaster::where('sku', $sku)->first();
    if ($p) {
        $bullets = collect([
            $p->bullet1 ?? '',
            $p->bullet2 ?? '',
            $p->bullet3 ?? '',
            $p->bullet4 ?? '',
            $p->bullet5 ?? '',
        ])->filter(fn ($v) => trim((string) $v) !== '')->implode("\n");
    }
}

if (! $bullets) {
    echo "No bullets found for {$sku}\n";
    exit(1);
}

echo "SKU: {$sku}\n";
echo 'Bullet lines: '.count(array_filter(explode("\n", $bullets)))."\n";
echo 'MACY_MCM_API_KEY set: '.(config('services.macy.mcm_api_key') ? 'yes' : 'no')."\n\n";

$result = app(MacysApiService::class)->updateBulletPoints($sku, (string) $bullets);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
