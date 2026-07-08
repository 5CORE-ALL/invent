<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? null;

echo "=== TopDawg integration check ===\n\n";

$token = config('services.topdawg.token');
echo 'TOPDAWG_API_TOKEN: '.($token ? 'SET ('.strlen((string) $token).' chars)' : 'MISSING')."\n";
echo 'Base URL: '.config('services.topdawg.base_url')."\n\n";

if (Schema::hasTable('topdawg_products')) {
    echo 'topdawg_products rows: '.DB::table('topdawg_products')->count()."\n";
    if ($sku) {
        $row = DB::table('topdawg_products')
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->first();
        echo "SKU lookup ({$sku}): ".($row ? json_encode($row) : 'NOT FOUND')."\n";
    } else {
        $sample = DB::table('topdawg_products')->whereNotNull('tdid')->where('tdid', '!=', '')->limit(3)->get(['sku', 'tdid']);
        echo "Sample products:\n";
        foreach ($sample as $r) {
            echo '  '.json_encode($r)."\n";
        }
    }
} else {
    echo "topdawg_products: table missing\n";
}

if (Schema::hasTable('topdawg_metrics')) {
    echo 'topdawg_metrics rows: '.DB::table('topdawg_metrics')->count()."\n";
    if ($sku) {
        $m = DB::table('topdawg_metrics')->where('sku', $sku)->first();
        if ($m) {
            echo 'topdawg_metrics: '.json_encode($m)."\n";
        }
    }
}

if (! $token) {
    echo "\nBLOCKED: Add TOPDAWG_API_TOKEN to .env\n";
    exit(1);
}

if (! $sku) {
    $sku = DB::table('topdawg_products')->whereNotNull('sku')->value('sku');
    if (! $sku) {
        echo "\nPass a SKU: php scripts/debug-topdawg.php YOUR-SKU\n";
        exit(0);
    }
    echo "\nUsing sample SKU: {$sku}\n";
}

$svc = app(TopDawgApiService::class);

echo "\n--- API list probe (page 1) ---\n";
try {
    $list = Http::withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->timeout(30)->post(rtrim(config('services.topdawg.base_url'), '/').'/SupplierProduct/list', [
        'per_page' => 5,
        'page' => 1,
    ]);
    echo 'HTTP '.$list->status()."\n";
    echo mb_substr((string) $list->body(), 0, 400)."\n";
} catch (Throwable $e) {
    echo 'List failed: '.$e->getMessage()."\n";
}

echo "\n--- Bullet push dry test ---\n";
$bullets = "Test bullet one\nTest bullet two\nTest bullet three";
try {
    $result = $svc->updateBulletPoints($sku, $bullets);
    echo json_encode($result, JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    echo 'Push exception: '.$e->getMessage()."\n";
}
