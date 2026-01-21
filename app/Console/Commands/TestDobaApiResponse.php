<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestDobaApiResponse extends Command
{
    protected $signature = 'test:doba-api {sku=SP 12120 4OHMS}';
    protected $description = 'Fetch and display Doba API response for a specific SKU';

    public function handle()
    {
        $targetSku = $this->argument('sku');
        
        // Check if API credentials are configured
        if (empty(env('DOBA_APP_KEY')) || empty(env('DOBA_PRIVATE_KEY'))) {
            $this->error("DOBA_APP_KEY or DOBA_PRIVATE_KEY not configured in .env file");
            return 1;
        }
        
        $this->info("========================================");
        $this->info("Fetching Doba API Response for SKU: {$targetSku}");
        $this->info("========================================");
        $this->newLine();

        $found = false;
        $page = 1;

        do {
            $this->info("Checking page {$page}...");
            
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);
            
            $response = Http::withoutVerifying()->withHeaders([
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ])->get('https://openapi.doba.com/api/goods/detail', [
                'pageNumber' => $page,
                'pageSize' => 100
            ]);
        
            if (!$response->ok()) {
                $this->error("API Failed: " . $response->body());
                return 1;
            }

            $responseData = $response->json();
            $data = $responseData['businessData']['data']['dsGoodsDetailResultVOS'] ?? [];
            
            if (empty($data)) {
                $this->info("No more data at page {$page}");
                break;
            }
            
            // Search for the target SKU
            foreach ($data as $product) {
                foreach ($product['skus'] as $sku) {
                    if ($sku['skuCode'] === $targetSku) {
                        $this->newLine();
                        $this->info("========================================");
                        $this->info("FOUND SKU: {$targetSku}");
                        $this->info("========================================");
                        $this->newLine();
                        
                        $this->line("Full Product Data:");
                        $this->line("==================");
                        $this->line(json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        $this->newLine(2);
                        
                        $this->info("========================================");
                        $this->line("Specific SKU Data:");
                        $this->line("==================");
                        $this->line(json_encode($sku, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        $this->newLine(2);
                        
                        $this->info("========================================");
                        $this->line("Fields Used in DobaMetric:");
                        $this->line("==================");
                        $item = $sku['stocks'][0] ?? null;
                        if ($item) {
                            $this->line("sku: " . $sku['skuCode']);
                            $this->line("item_id: " . ($item['itemNo'] ?? 'N/A'));
                            $this->line("anticipated_income: " . ($item['anticipatedIncome'] ?? 'N/A'));
                            $this->line("self_pick_price: " . ($item['selfPickAnticipatedIncome'] ?? 'N/A'));
                            $this->line("msrp: " . ($sku['msrp'] ?? 'N/A'));
                            $this->line("map: " . ($sku['map'] ?? 'N/A'));
                        } else {
                            $this->warn("No stock data available");
                        }
                        
                        $found = true;
                        break 2;
                    }
                }
            }
            
            $page++;
        } while (count($data) === 100 && !$found);

        if (!$found) {
            $this->warn("\nSKU '{$targetSku}' not found in the API response.");
        }

        $this->newLine();
        $this->info("========================================");
        $this->info("Test Complete");
        $this->info("========================================");
        
        return 0;
    }

    private function generateSignature($content)
    {
        $privateKeyFormatted = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap(env('DOBA_PRIVATE_KEY'), 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $private_key = openssl_pkey_get_private($privateKeyFormatted);
        if (!$private_key) {
            throw new Exception("Invalid private key.");
        }
        openssl_sign($content, $signature, $private_key, OPENSSL_ALGO_SHA256);
        
        $sign = base64_encode($signature); 
        return $sign;
    }

    private function getContent($timestamp)
    {
        $appKey = env('DOBA_APP_KEY');
        $contentForSign = "appKey={$appKey}&signType=rsa2&timestamp={$timestamp}";
        return $contentForSign;
    }

    private function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return intval((float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000));
    }
}
