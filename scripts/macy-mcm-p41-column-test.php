<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$withCategory = in_array('--with-category', $argv, true);
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

$bullets = DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points');
$lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $bullets) ?: [])));
$category = DB::table('macys_price_data')->where('sku', $sku)->value('category_code');

$headers = ['shopSku'];
$values = [$sku];
if ($withCategory && $category) {
    $headers[] = 'categoryCode';
    $values[] = $category;
}
for ($i = 1; $i <= 5; $i++) {
    $headers[] = "fnb{$i}";
    $values[] = mb_substr($lines[$i - 1] ?? '', 0, 254);
}

$handle = fopen('php://temp', 'r+');
fputcsv($handle, $headers);
fputcsv($handle, $values);
rewind($handle);
$csv = "\xEF\xBB\xBF".stream_get_contents($handle);
fclose($handle);

echo ($withCategory ? 'WITH' : 'WITHOUT')." categoryCode\n";
echo $csv."\n";

$client = new GuzzleHttp\Client(['verify' => false, 'timeout' => 120]);
$r = $client->post("{$base}/api/products/imports", [
    'headers' => ['Authorization' => $key, 'Accept' => 'application/json'],
    'multipart' => [
        ['name' => 'file', 'contents' => $csv, 'filename' => 'test.csv', 'headers' => ['Content-Type' => 'text/csv; charset=UTF-8']],
        ['name' => 'operator_format', 'contents' => 'true'],
    ],
]);
$json = json_decode((string) $r->getBody(), true);
$importId = (int) ($json['import_id'] ?? 0);
echo "P41 import_id={$importId}\n";

for ($i = 0; $i < 30; $i++) {
    sleep(2);
    $p42 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/products/imports/{$importId}");
    $status = (string) ($p42->json('import_status') ?? '');
    $te = (int) ($p42->json('transform_lines_in_error') ?? 0);
    $ts = (int) ($p42->json('transform_lines_in_success') ?? 0);
    echo "  poll {$i}: {$status} success={$ts} error={$te}\n";
    if (in_array($status, ['COMPLETE', 'FAILED', 'CANCELLED', 'TRANSFORMATION_FAILED'], true)) {
        if ($te > 0) {
            $p47 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
                ->get("{$base}/api/products/imports/{$importId}/transformation_error_report");
            echo $p47->body()."\n";
        }
        break;
    }
}
