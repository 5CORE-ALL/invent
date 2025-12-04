<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

// Get token
$response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
]);

if (!$response->successful()) {
    echo "Failed to get token\n";
    exit(1);
}

$token = $response->json()['access_token'];
echo "Token obtained successfully\n\n";

// Search for the SKU
$pageToken = null;
$found = false;
$page = 1;

do {
    echo "Searching page $page...\n";
    
    // Try with channel filter for Macy's
    $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code=macys';
    if ($pageToken) {
        $url .= '&page_token=' . urlencode($pageToken);
    }
    
    $response = Http::withToken($token)->get($url);
    
    if (!$response->successful()) {
        echo "API call failed\n";
        break;
    }
    
    $json = $response->json();
    $products = $json['data'] ?? [];
    $pageToken = $json['next_page_token'] ?? null;
    
    foreach ($products as $product) {
        $sku = $product['id'] ?? null;
        
        if ($sku === 'BTUBE UNDR ST 150 LGT') {
            $found = true;
            echo "\n✅ FOUND SKU: BTUBE UNDR ST 150 LGT\n";
            echo "===========================================\n\n";
            
            // Extract price from multiple sources
            $price = $product['discount_prices'][0]['price']['amount'] ?? 
                     $product['standard_prices'][0]['price']['amount'] ?? 
                     $product['price']['amount'] ?? 
                     $product['prices'][0]['amount'] ?? 
                     $product['offer_price']['amount'] ?? null;
            
            echo "Price: $" . ($price ?? 'N/A') . "\n";
            echo "Channel: " . ($product['channel_code'] ?? 'N/A') . "\n\n";
            
            echo "Full product data:\n";
            echo json_encode($product, JSON_PRETTY_PRINT) . "\n";
            break 2;
        }
    }
    
    $page++;
} while ($pageToken && !$found);

if (!$found) {
    echo "\n❌ SKU 'BTUBE UNDR ST 150 LGT' not found in Mirakl products\n";
}
