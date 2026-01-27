<?php
// Quick test script to check status values
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductMaster;

echo "Testing Status Values from ProductMaster...\n\n";

$products = ProductMaster::all();
$statuses = [];

foreach ($products as $product) {
    $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
    
    if (is_array($values) && isset($values['status']) && !empty($values['status'])) {
        $statuses[] = $values['status'];
    }
}

$uniqueStatuses = array_values(array_unique($statuses));
sort($uniqueStatuses);

echo "Found " . count($uniqueStatuses) . " unique status values:\n";
foreach ($uniqueStatuses as $status) {
    echo "  - " . $status . "\n";
}

echo "\nJSON Response:\n";
echo json_encode([
    'success' => true,
    'data' => $uniqueStatuses,
    'total' => count($uniqueStatuses)
], JSON_PRETTY_PRINT);
?>
