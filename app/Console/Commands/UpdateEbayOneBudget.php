<?php

namespace App\Console\Commands;

use App\Models\EbayPriorityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Exception;

class UpdateEbayOneBudget extends Command
{
    protected $signature = 'ebay1:update-budget';
    protected $description = 'Update eBay1 campaign budgets based on ACOS rules';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $this->info('Starting eBay1 campaign budget update...');

            $accessToken = $this->getEbayAccessToken();
            if (!$accessToken) {
                $this->error('Failed to obtain eBay access token.');
                return 1;
            }

            // Get L30 campaign reports for ACOS calculation
            $this->info('Loading campaign reports (L30)...');
            
            $campaignReports = EbayPriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->get();
            
            DB::connection()->disconnect();

            if ($campaignReports->isEmpty()) {
                $this->info('No running campaigns found.');
                return 0;
            }

            $this->info("Found {$campaignReports->count()} campaign(s).");

            // Group by campaign_id to get unique campaigns
            $uniqueCampaigns = $campaignReports->groupBy('campaign_id');

            $client = new Client([
                'base_uri' => 'https://api.ebay.com/',
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($uniqueCampaigns as $campaignId => $reports) {
                $campaign = $reports->first();
                
                // Calculate ACOS from L30 data (matching frontend logic from EbayOverUtilizedBgtController)
                $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
                $sales = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);
                
                // ACOS = (adFees / sales) * 100
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
                
                // If acos is 0 (no sales or no ad fees), set it to 100 for display/budget calculation
                // This matches the frontend logic: if($acos === 0) { $row['acos'] = 100; }
                if ($acos === 0) {
                    $acos = 100;
                }
                
                // Determine new budget based on ACOS rules
                $newBudget = $this->calculateBudget($acos);
                
                // Get current budget
                $currentBudget = (float) str_replace('USD ', '', $campaign->campaignBudgetAmount ?? 0);
                
                $campaignName = $campaign->campaign_name ?? 'Unknown';
                
                // Skip if budget is already correct
                if (abs($currentBudget - $newBudget) < 0.01) {
                    $this->info("Campaign: {$campaignName} | ACOS: {$acos}% | Old Budget: \${$currentBudget} | New Budget: \${$newBudget} | Status: Already correct, skipping.");
                    $skippedCount++;
                    continue;
                }

                $this->info("Campaign: {$campaignName} | ACOS: {$acos}% | Old Budget: \${$currentBudget} | New Budget: \${$newBudget} | Status: Updating...");

                try {
                    // Update campaign budget using eBay's dedicated budget update endpoint
                    $updatePayload = [
                        'daily' => [
                            'amount' => [
                                'value' => number_format($newBudget, 1, '.', ''),
                                'currency' => 'USD'
                            ]
                        ]
                    ];
                    
                    // Update campaign budget using POST to update_campaign_budget endpoint
                    $response = $client->post(
                        "sell/marketing/v1/ad_campaign/{$campaignId}/update_campaign_budget",
                        ['json' => $updatePayload]
                    );

                    if ($response->getStatusCode() === 200 || $response->getStatusCode() === 204) {
                        $this->info("  ✅ Updated successfully - Campaign: {$campaignName}, ACOS: {$acos}%, Old Budget: \${$currentBudget}, New Budget: \${$newBudget}");
                        $updatedCount++;
                    } else {
                        $this->warn("  ⚠️  Unexpected status: " . $response->getStatusCode());
                        $errorCount++;
                    }
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    
                    if ($statusCode === 404) {
                        $this->warn("  ⚠️  Campaign {$campaignId} not found (404). Skipping...");
                        $skippedCount++;
                    } else {
                        $this->error("  ❌ Failed (Status {$statusCode})");
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Error: " . $e->getMessage());
                    $errorCount++;
                }
            }

            $this->newLine();
            $this->info("Summary:");
            $this->info("  Updated: {$updatedCount}");
            $this->info("  Skipped: {$skippedCount}");
            $this->info("  Errors: {$errorCount}");
            $this->info("  Total: " . ($updatedCount + $skippedCount + $errorCount));

            $this->info('eBay1 campaign budget update finished.');
            return 0;

        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $this->error('Command failed with error: ' . $e->getMessage());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    /**
     * Calculate budget based on ACOS rules:
     * - If ACOS < 4% then budget = $9
     * - If 4% ≤ ACOS < 8% then budget = $6
     * - If ACOS ≥ 8% or ACOS = 100% (no sales but has spend) then budget = $3
     */
    private function calculateBudget($acos)
    {
        // If ACOS < 4%, set budget to $9
        if ($acos < 4) {
            return 9;
        }
        
        // If 4% ≤ ACOS < 8%, set budget to $6
        if ($acos >= 4 && $acos < 8) {
            return 6;
        }
        
        // If ACOS ≥ 8% (including 100% for no sales), set budget to $3
        return 3;
    }

    private function getEbayAccessToken()
    {
        try {
            if (Cache::has('ebay_access_token')) {
                return Cache::get('ebay_access_token');
            }

            $clientId = env('EBAY_APP_ID');
            $clientSecret = env('EBAY_CERT_ID');
            $refreshToken = env('EBAY_REFRESH_TOKEN');
            
            if (!$clientId || !$clientSecret || !$refreshToken) {
                throw new Exception('Missing eBay API credentials in environment variables');
            }

            $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";

            $postFields = http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => 'https://api.ebay.com/oauth/api_scope/sell.marketing'
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "Authorization: Basic " . base64_encode("$clientId:$clientSecret")
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new Exception('cURL Error: ' . $error);
            }
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception('HTTP Error: ' . $httpCode . ' Response: ' . $response);
            }

            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if (isset($data['access_token'])) {
                $accessToken = $data['access_token'];
                $expiresIn = $data['expires_in'] ?? 7200;

                Cache::put('ebay_access_token', $accessToken, $expiresIn - 60);

                return $accessToken;
            }

            throw new Exception("Failed to refresh token: " . json_encode($data));
            
        } catch (Exception $e) {
            throw $e;
        }
    }
}

