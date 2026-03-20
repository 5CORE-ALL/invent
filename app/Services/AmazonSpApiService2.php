<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;

class AmazonSpApiService2
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
    public function getAccessToken()
    {
        $client = new Client();
        $response = $client->post('https://api.amazon.com/auth/o2/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['access_token'];
    }

    private function getAccessTokenV1()
    {
        $res = Http::withoutVerifying()->asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.amazon_sp.refresh_token'),
            'client_id' => config('services.amazon_sp.client_id'),
            'client_secret' => config('services.amazon_sp.client_secret'),
        ]);
        return $res['access_token'] ?? null;
    }


public function getinventory()
{
    $parsedData = [];

    try {
        $accessToken = $this->getAccessToken();
        Log::info('Access Token', [$accessToken]);

        $marketplaceId = $this->marketplaceId;

        // Step 1: Request the report
        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->post("{$this->endpoint}/reports/2021-06-30/reports", [
            'reportType' => 'GET_MERCHANT_LISTINGS_ALL_DATA',
            'marketplaceIds' => [$marketplaceId],
        ]);

        Log::info('Report Request Response', ['body' => $response->body()]);
        $reportId = $response['reportId'] ?? null;
        if (!$reportId) {
            Log::error('Failed to request report.');
            return;
        }

        // Step 2: Poll for report status
        $maxRetries = 20;
        $retryCount = 0;
        do {
            sleep(15);
            $status = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
            ])->get("{$this->endpoint}/reports/2021-06-30/reports/{$reportId}");

            $processingStatus = $status['processingStatus'] ?? 'UNKNOWN';
            Log::info("Waiting... Status: $processingStatus");

            if (++$retryCount > $maxRetries) {
                Log::error('Report generation timed out.');
                return;
            }
        } while ($processingStatus !== 'DONE');

        // Step 3: Get document URL
        $documentId = $status['reportDocumentId'] ?? null;
        if (!$documentId) {
            Log::error('Document ID not found.');
            return;
        }

        $doc = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->get("{$this->endpoint}/reports/2021-06-30/documents/{$documentId}");

        $url = $doc['url'] ?? null;
        $compression = $doc['compressionAlgorithm'] ?? 'GZIP';


        if (!$url) {
            Log::error('Document URL not found.');
            return;
        }

        // Step 3: Download and parse the data
        $csv = file_get_contents($url);
          $csv = strtoupper($compression) === 'GZIP' ? gzdecode($csv) : $csv;
        if (!$csv) {
            Log::error('Failed to decode report content.');
            return;
        }
        
        $lines = explode("\n", $csv);
        $headers = array_map('trim', explode("\t", array_shift($lines)));

        foreach ($lines as $index => $line) {
            $row = str_getcsv($line, "\t");

            if (empty($row) || count($row) !== count($headers)) {
                Log::warning('Skipping malformed row', [
                    'line_index' => $index,
                    'expected_columns' => count($headers),
                    'actual_columns' => count($row),
                    'raw' => $line,
                ]);
                continue;
            }

            $data = array_combine($headers, $row);
            if (($data['fulfillment-channel'] ?? '') !== 'DEFAULT') continue;

            $sku = isset($data['seller-sku']) ? preg_replace('/[^\x20-\x7E]/', '', trim($data['seller-sku'])) : null;
            $quantity = isset($data['quantity']) && is_numeric($data['quantity']) ? (int) $data['quantity'] : 0;

            if ($sku) {
                $parsedData[] = [
                    'sku' => $sku,
                    'quantity' => $quantity,
                ];

                ProductStockMapping::where('sku', $sku)->update([
                    'inventory_amazon' => $quantity,
                ]);
            } else {
                Log::warning('Missing SKU in row', ['data' => $data]);
            }
        }

        Log::info('Amazon inventory sync complete.', ['count' => count($parsedData)]);
        return $parsedData;

    } catch (\Exception $e) {
        Log::error('Error in getAmazonInventory: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}
