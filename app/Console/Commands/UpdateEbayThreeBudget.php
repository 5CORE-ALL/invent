<?php

namespace App\Console\Commands;

use App\Models\Ebay3PriorityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * eBay 3 mirror of {@see UpdateEbayOneBudget} / {@see UpdateEbayTwoBudget} —
 * recalculates each running campaign's daily budget from L30 ACOS and pushes it
 * via `sell/marketing/v1/ad_campaign/{id}/update_campaign_budget`.
 *
 * Source data: `ebay_3_priority_reports` (App\Models\Ebay3PriorityReport)
 * Auth:        EBAY_3_APP_ID / EBAY_3_CERT_ID / EBAY_3_REFRESH_TOKEN
 *              (cached separately under `ebay3_access_token`)
 */
class UpdateEbayThreeBudget extends Command
{
    protected $signature = 'ebay3:update-budget';
    protected $description = 'Update eBay3 campaign budgets based on ACOS rules';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $this->info('Starting eBay3 campaign budget update...');

            $accessToken = $this->getEbayAccessToken();
            if (!$accessToken) {
                $this->error('Failed to obtain eBay 3 access token.');
                return 1;
            }

            $this->info('Loading campaign reports (L30)...');

            $campaignReports = Ebay3PriorityReport::where('report_range', 'L30')
                ->where('campaignStatus', 'RUNNING')
                ->get();

            DB::connection()->disconnect();

            if ($campaignReports->isEmpty()) {
                $this->info('No running campaigns found.');
                return 0;
            }

            $this->info("Found {$campaignReports->count()} campaign(s).");

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

                $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);

                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;

                if ($acos === 0) {
                    $acos = 100;
                }

                $newBudget = $this->calculateBudget($acos);

                $currentBudget = (float) str_replace('USD ', '', $campaign->campaignBudgetAmount ?? 0);

                $campaignName = $campaign->campaign_name ?? 'Unknown';

                if (abs($currentBudget - $newBudget) < 0.01) {
                    $this->info("Campaign: {$campaignName} | ACOS: {$acos}% | Old Budget: \${$currentBudget} | New Budget: \${$newBudget} | Status: Already correct, skipping.");
                    $skippedCount++;
                    continue;
                }

                $this->info("Campaign: {$campaignName} | ACOS: {$acos}% | Old Budget: \${$currentBudget} | New Budget: \${$newBudget} | Status: Updating...");

                try {
                    $updatePayload = [
                        'daily' => [
                            'amount' => [
                                'value'    => number_format($newBudget, 1, '.', ''),
                                'currency' => 'USD',
                            ],
                        ],
                    ];

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

            $this->info('eBay3 campaign budget update finished.');
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
     * Budget tiers driven by ACOS:
     *   ACOS < 4%       → $9
     *   4% ≤ ACOS < 8%  → $6
     *   ACOS ≥ 8%       → $3 (also covers the 100% no-sales sentinel)
     */
    private function calculateBudget($acos)
    {
        if ($acos < 4) {
            return 9;
        }

        if ($acos >= 4 && $acos < 8) {
            return 6;
        }

        return 3;
    }

    /**
     * Get eBay 3 access token using EBAY_3_* refresh-token credentials.
     * Cached under `ebay3_access_token`.
     */
    private function getEbayAccessToken()
    {
        try {
            if (Cache::has('ebay3_access_token')) {
                return Cache::get('ebay3_access_token');
            }

            $clientId     = config('services.ebay3.app_id', env('EBAY_3_APP_ID'));
            $clientSecret = config('services.ebay3.cert_id', env('EBAY_3_CERT_ID'));
            $refreshToken = config('services.ebay3.refresh_token', env('EBAY_3_REFRESH_TOKEN'));

            if (!$clientId || !$clientSecret || !$refreshToken) {
                throw new Exception('Missing eBay 3 API credentials in environment variables (EBAY_3_APP_ID / EBAY_3_CERT_ID / EBAY_3_REFRESH_TOKEN)');
            }

            $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";

            $postFields = http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope'         => 'https://api.ebay.com/oauth/api_scope/sell.marketing',
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_HTTPHEADER     => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "Authorization: Basic " . base64_encode("$clientId:$clientSecret"),
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
                $expiresIn   = $data['expires_in'] ?? 7200;

                Cache::put('ebay3_access_token', $accessToken, $expiresIn - 60);

                return $accessToken;
            }

            throw new Exception("Failed to refresh token: " . json_encode($data));

        } catch (Exception $e) {
            throw $e;
        }
    }
}
