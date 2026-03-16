<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AmazonListingRaw;
use App\Services\AmazonSpApiService;

echo "🚀 Fixing missing fields...\n\n";

$service = new AmazonSpApiService();

// Get records missing critical fields
$records = AmazonListingRaw::whereNull('country_of_origin')
    ->orWhereNull('size')
    ->orWhereNull('material')
    ->where('seller_sku', 'not like', 'PARENT%')  // Skip parent SKUs
    ->limit(58)
    ->get();

echo "Found " . $records->count() . " records to process\n\n";

$success = 0;
foreach ($records as $index => $record) {
    echo "[" . ($index+1) . "/" . $records->count() . "] Processing: {$record->seller_sku}\n";
    
    try {
        $updates = $service->enrichListingData($record->asin1, $record->seller_sku);
        
        if (!empty($updates)) {
            $fillable = (new AmazonListingRaw)->getFillable();
            $filtered = array_intersect_key($updates, array_flip($fillable));
            
            // Log what's being updated
            $updatedFields = array_keys($filtered);
            
            $record->update($filtered);
            $success++;
            echo "  ✅ Updated: " . implode(', ', $updatedFields) . "\n";
        } else {
            echo "  ⚠️ No updates available\n";
        }
    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    sleep(1); // Rate limit
}

echo "\n📊 Results: $success/" . $records->count() . " records updated\n";
