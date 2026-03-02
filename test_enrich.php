<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AmazonListingRaw;
use App\Services\AmazonSpApiService;

echo "🚀 Starting test enrichment (50 records)...\n\n";
$service = new AmazonSpApiService();
$records = AmazonListingRaw::whereNull('item_name')
    ->whereNotNull('asin1')
    ->limit(50)
    ->get();

$success = 0;
$failed = 0;

foreach ($records as $index => $record) {
    echo "[" . ($index+1) . "/50] Processing: {$record->seller_sku} (ASIN: {$record->asin1})\n";
    
    try {
        $updates = $service->enrichListingData($record->asin1, $record->seller_sku);
        
        if (!empty($updates)) {
            $fillable = (new AmazonListingRaw)->getFillable();
            $filtered = array_intersect_key($updates, array_flip($fillable));
            $record->update($filtered);
            $success++;
            echo "  ✅ Updated " . count($filtered) . " fields\n";
            
            // Show sample fields
            $fields = array_keys($filtered);
            echo "     Fields: " . implode(', ', array_slice($fields, 0, 5)) . "...\n";
        } else {
            echo "  ⚠️ No updates\n";
        }
    } catch (\Exception $e) {
        $failed++;
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    sleep(1); // Rate limit respect
}

echo "\n📊 TEST RESULTS:\n";
echo "✅ Success: $success records\n";
echo "❌ Failed: $failed records\n";
echo "🚀 Progress: 50/695 records processed\n";
