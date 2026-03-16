<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AmazonListingRaw;
use App\Services\AmazonSpApiService;

echo "Testing with one record...\n";
$service = new AmazonSpApiService();
$record = AmazonListingRaw::whereNull('item_name')->first();

if ($record) {
    echo "Processing: {$record->seller_sku}\n";
    $updates = $service->enrichListingData($record->asin1, $record->seller_sku);
    
    if (!empty($updates)) {
        $fillable = (new AmazonListingRaw)->getFillable();
        $filtered = array_intersect_key($updates, array_flip($fillable));
        $record->update($filtered);
        echo "✅ Updated " . count($filtered) . " fields\n";
    }
}
