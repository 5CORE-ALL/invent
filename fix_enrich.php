<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AmazonListingRaw;
use App\Services\AmazonSpApiService;

echo "🚀 Starting fixed enrichment (with JSON handling)...\n\n";

$service = new AmazonSpApiService();
$records = AmazonListingRaw::whereNull('item_name')
    ->whereNotNull('asin1')
    ->limit(50)
    ->get();

$success = 0;
$failed = 0;

foreach ($records as $index => $record) {
    echo "[" . ($index+1) . "/50] Processing: {$record->seller_sku}\n";
    
    try {
        $updates = $service->enrichListingData($record->asin1, $record->seller_sku);
        
        if (!empty($updates)) {
            $fillable = (new AmazonListingRaw)->getFillable();
            $filtered = [];
            
            foreach ($updates as $key => $value) {
                if (!in_array($key, $fillable)) {
                    continue;
                }
                
                // Fix JSON fields
                if (in_array($key, ['included_components', 'item_dimensions', 'bullet_point'])) {
                    if (is_string($value)) {
                        // Try to decode if it's JSON string
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $filtered[$key] = $decoded;
                        } else {
                            // Convert to array if not valid JSON
                            $filtered[$key] = is_array($value) ? $value : [$value];
                        }
                    } else {
                        $filtered[$key] = $value;
                    }
                } else {
                    $filtered[$key] = $value;
                }
            }
            
            $record->update($filtered);
            $success++;
            echo "  ✅ Updated " . count($filtered) . " fields\n";
            
            // Show fixed fields
            if (isset($filtered['included_components'])) {
                echo "     included_components: " . json_encode($filtered['included_components']) . "\n";
            }
        }
    } catch (\Exception $e) {
        $failed++;
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    sleep(1);
}

echo "\n📊 RESULTS:\n";
echo "✅ Success: $success\n";
echo "❌ Failed: $failed\n";
