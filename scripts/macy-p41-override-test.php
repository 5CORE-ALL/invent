<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$tryOverride = in_array('--override', $argv, true);
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

$bullets = DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points');
$lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $bullets) ?: [])));

$headers = ['shopSku'];
$values = [$sku];
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

echo "Bullet-only P41 test for {$sku}\n";
echo "override=".($tryOverride ? 'true' : 'false')."\n\n";

$multipart = [
    ['name' => 'file', 'contents' => $csv, 'filename' => 'bullet-only-test.csv', 'headers' => ['Content-Type' => 'text/csv; charset=UTF-8']],
    ['name' => 'operator_format', 'contents' => 'true'],
];
if ($tryOverride) {
    $multipart[] = ['name' => 'update_options', 'contents' => json_encode(['allow_locked_values_override' => true])];
    $multipart[] = ['name' => 'update_options[allow_locked_values_override]', 'contents' => 'true'];
}

$client = new GuzzleHttp\Client(['verify' => false, 'timeout' => 120]);
$r = $client->post("{$base}/api/products/imports", [
    'headers' => ['Authorization' => $key, 'Accept' => 'application/json'],
    'multipart' => $multipart,
]);
$json = json_decode((string) $r->getBody(), true);
$importId = (int) ($json['import_id'] ?? 0);
echo "HTTP {$r->getStatusCode()} import_id={$importId}\n";
echo json_encode($json, JSON_PRETTY_PRINT)."\n\n";

for ($i = 0; $i < 20; $i++) {
    sleep(2);
    $p42 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/products/imports/{$importId}");
    $body = $p42->json();
    $status = (string) ($body['import_status'] ?? '');
    echo "poll {$i}: {$status} err=".($body['transform_lines_in_error'] ?? '?')."\n";
    if (isset($body['update_options'])) {
        echo '  update_options: '.json_encode($body['update_options'])."\n";
    }
    if (in_array($status, ['SENT', 'COMPLETE', 'FAILED', 'CANCELLED', 'TRANSFORMATION_FAILED'], true)) {
        break;
    }
}
