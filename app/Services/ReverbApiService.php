<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;

class ReverbApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $region;
    protected $marketplaceId;
    protected $awsAccessKey;
    protected $awsSecretKey;
    protected $endpoint;

    public function __construct()
    {
        $this->clientId = config('services.amazon_sp.client_id');
        $this->clientSecret = config('services.amazon_sp.client_secret');
        $this->refreshToken = config('services.amazon_sp.refresh_token');
        $this->region = config('services.amazon_sp.region');
        $this->marketplaceId = config('services.amazon_sp.marketplace_id');
        $this->awsAccessKey = config('services.amazon_sp.aws_access_key');
        $this->awsSecretKey = config('services.amazon_sp.aws_secret_key');
        $this->endpoint = 'https://sellingpartnerapi-na.amazon.com';
    }
    

 public function getInventory()
{
    $inventory = [];
    $url = 'https://api.reverb.com/api/my/listings'; // Start URL

    try {
        while ($url) {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                Log::error('Failed to fetch inventory page.', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return []; // or break; depending on whether you want partial data
            }

            $data = $response->json();

            // Process listings
            if (isset($data['listings']) && is_array($data['listings'])) {
                foreach ($data['listings'] as $item) {
                    if (isset($item['sku'], $item['inventory'])) {
                        $inventory[] = [
                            'sku' => $item['sku'],
                            'quantity' => $item['inventory'],
                        ];
                    }
                }
            }

            // Check for next page
            if (isset($data['_links']['next']['href'])) {
                $url = $data['_links']['next']['href'];
                // Clean URL: Reverb sometimes adds trailing spaces in href
                $url = trim($url);
            } else {
                $url = null; // No more pages
            }
        }
       
        foreach ($inventory as $sku => $data) {
            $sku = $data['sku'] ?? null;
                    $quantity = $data['quantity'];
                if (!$sku) {
                    Log::warning('Missing SKU in parsed Amazon data', $item);
                    continue;
                }
                
            ProductStockMapping::where('sku', $sku)->update(['inventory_reverb' => (int) $quantity]);
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $sku],
            //     ['inventory_reverb'=>$quantity,]
            // );
        }
        return $inventory;

    } catch (\Throwable $e) {
        Log::error('Exception during paginated inventory fetch: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return [];
    }
}

}
