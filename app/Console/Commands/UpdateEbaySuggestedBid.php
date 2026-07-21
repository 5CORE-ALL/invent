<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateEbaySuggestedBid extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'ebay:update-suggestedbid
        {--dry-run : Run without making actual API calls}
        {--chunk= : Override chunk size (default from cron-monitor config)}';
    protected $description = 'Bulk update eBay ad bids using suggested_bid percentages';

    protected string $monitorJobName = 'eBay Suggested Bid';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeUpdate($m),
            $this->monitorJobName
        );
    }

    protected function executeUpdate(CronExecutionContext $monitor): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $chunkSize = $this->monitoredChunkSize();
            
            if ($dryRun) {
                $this->warn('=== DRY RUN MODE - No actual changes will be made ===');
            }
            
            $this->info('Starting bulk eBay ad bid update...');

            $accessToken = null;
            if (!$dryRun) {
                $accessToken = $this->getEbayAccessToken();
                if (!$accessToken) {
                    $this->error('Failed to obtain eBay access token.');
                    return self::FAILURE;
                }
                $monitor->markApiConnected();
            }

            // Process ProductMaster records in chunks to prevent "Too many connections" error
            $totalRecords = ProductMaster::whereNull('deleted_at')->count();
            
            if ($totalRecords === 0) {
                $this->info('No product masters found.');
                $monitor->setExpected(0);
                return self::SUCCESS;
            }

            $monitor->setExpected($totalRecords);
            
            $this->info("Processing {$totalRecords} product masters in chunks of {$chunkSize}...");
            
            $allSkus = collect();
            $processedCount = 0;
            
            // Collect all SKUs first using chunked processing
            ProductMaster::whereNull('deleted_at')
                ->orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->chunk($chunkSize, function ($productMasters) use (&$allSkus, &$processedCount, $totalRecords, $monitor) {
                    $chunkSkus = $productMasters->pluck("sku")->filter()->unique();
                    $allSkus = $allSkus->merge($chunkSkus);
                    $processedCount += $productMasters->count();
                    $monitor->incrementProcessed($productMasters->count());
                    $monitor->checkpoint(['phase' => 'product_masters', 'processed' => $processedCount], $processedCount);
                    $this->info("Processed {$processedCount}/{$totalRecords} product masters...");
                });
            
            $skus = $allSkus->unique()->values()->all();
            
            if (empty($skus)) {
                $this->info('No valid SKUs found in product masters.');
                return self::SUCCESS;
            }
            
            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                $monitor->classifyAndRecord($e);
                return self::FAILURE;
            }

            // SKU normalization function
            $normalizeSku = function ($sku) {
                $sku = trim($sku);
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return strtoupper($sku);
            };

            $this->info('Loading Shopify and eBay metrics data...');
            $shopifyData = [];
            $ebayMetrics = collect();
            
            if (!empty($skus)) {
                // Normalize ShopifySku data keys
                $shopifyRaw = ShopifySku::whereIn("sku", $skus)->get();
                $shopifyData = collect();
                foreach ($shopifyRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $shopifyData[$normalizedKey] = $item;
                }
                
                $ebayMetrics = EbayMetric::whereIn("sku", $skus)->get();
            }
            DB::connection()->disconnect();
            
            if ($ebayMetrics->isEmpty()) {
                $this->info('No eBay metrics found for the SKUs.');
                $monitor->setFetched(0);
                return self::SUCCESS;
            }

            $monitor->setFetched($ebayMetrics->count());
        
        // Normalize eBay metrics data keys
        $ebayMetricsNormalized = collect();
        foreach ($ebayMetrics as $item) {
            $normalizedKey = $normalizeSku($item->sku);
            $ebayMetricsNormalized[$normalizedKey] = $item;
        }

        // Load from inventory ebay_campaign_ads (same source as /ebay/campaign-ads).
        // apicentral.ebay_campaign_ads_listings is stale and misses listings synced by
        // ebay:sync-campaign-listings — those never got pushed (e.g. GSS AL BLK).
        $this->info('Loading campaign listings from ebay_campaign_ads...');
        $campaignListings = DB::table('ebay_campaign_ads')
            ->select('listing_id', 'campaign_id', 'bid_percentage', 'suggested_bid', 'updated_at')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('listing_id')
            ->keyBy('listing_id')
            ->map(function ($item) {
                return (object) [
                    'listing_id' => $item->listing_id,
                    'campaign_id' => $item->campaign_id,
                    'bid_percentage' => $item->bid_percentage,
                    'suggested_bid' => $item->suggested_bid,
                    'new_bid' => null,
                ];
            });

            if ($campaignListings->isEmpty()) {
                $this->info('No campaign listings found.');
                return self::SUCCESS;
            }

            // Get L30 data (clicks and sales) from ebaygeneral report for CVR calculation
            $this->info('Loading eBay general report data...');
            $ebayGeneralL30 = EbayGeneralReport::select('listing_id', 'clicks', 'sales')
                ->where('report_range', 'L30')
                ->get()
                ->keyBy('listing_id');
            
        // Load Sbid Rule slabs (ebay1_sbid_slabs) — CVR / Dil / Esold / Views L30 → S Bid.
        // Rules are evaluated top to bottom; the first rule whose filled ranges all match wins.
        $slabRow = DB::table('ebay_sbid_rules')->where('key', 'ebay1_sbid_slabs')->first();
        $sbidSlabs = $slabRow ? (json_decode($slabRow->rule, true)['rules'] ?? []) : [];
        $this->info('SBID slab rules loaded: ' . count($sbidSlabs) . ' (CVR / Dil / Esold / Views L30 → S Bid)');

        // Sbid (Views) — daily ±%/day adjustment of the base S Bid by L7 View colour
        // band, clamped to Min/Max caps (same rule the UI shows). The colour band
        // needs the average l7_views across the listings this command processes.
        $sbidViewsSettings = \App\Support\SbidViewsRule::settings();
        $l7Sum = 0.0; $l7Count = 0;
        foreach ($ebayMetricsNormalized as $m) {
            if ($m && $m->item_id && $campaignListings->has($m->item_id)) {
                $l7Sum += (float) ($m->l7_views ?? 0);
                $l7Count++;
            }
        }
        $avgL7Views = $l7Count > 0 ? ($l7Sum / $l7Count) : 0.0;
        $this->info("Sbid (Views): avg L7 across {$l7Count} processed listing(s) = " . round($avgL7Views, 2)
            . " | caps [{$sbidViewsSettings['min_cap']}, {$sbidViewsSettings['max_cap']}]"
            . " | no-dec when E L30 ≤ {$sbidViewsSettings['no_dec_max_el30']}"
            . " | pink {$sbidViewsSettings['pink_dir']} {$sbidViewsSettings['pink_step']}"
            . " | green {$sbidViewsSettings['green_dir']} {$sbidViewsSettings['green_step']}"
            . " | red {$sbidViewsSettings['red_dir']} {$sbidViewsSettings['red_step']}");

        // Process ProductMaster data in chunks and update campaign listings
        $this->info('Processing bid updates based on Sbid Rule slabs...');
        $updatedListings = 0;
        $bidProcessedCount = 0;
        
        ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->chunk($chunkSize, function ($productMasters) use (
                $shopifyData, 
                $ebayMetricsNormalized, 
                $campaignListings,
                $sbidSlabs,
                $sbidViewsSettings,
                $avgL7Views,
                $ebayGeneralL30, 
                &$updatedListings,
                &$bidProcessedCount,
                $normalizeSku,
                $monitor
            ) {
                foreach ($productMasters as $pm) {
                    $normalizedSku = $normalizeSku($pm->sku);
                    $shopify = $shopifyData[$normalizedSku] ?? null;
                    $ebayMetric = $ebayMetricsNormalized[$normalizedSku] ?? null;

                    if ($ebayMetric && $ebayMetric->item_id && $campaignListings->has($ebayMetric->item_id)) {
                        $listing = $campaignListings[$ebayMetric->item_id];

                        $soldL30  = (float) ($ebayMetric->ebay_l30 ?? 0);   // Esold
                        $views    = (float) ($ebayMetric->views ?? 0);      // Views L30
                        $scvr     = $views > 0 ? ($soldL30 / $views) * 100 : 0; // CVR

                        // DIL = (L30 sold / inventory) * 100, from Shopify data
                        $inv = (float) ($shopify->inv ?? 0);
                        $qty = (float) ($shopify->quantity ?? 0);
                        $dil = $inv > 0 ? ($qty / $inv) * 100 : 0;

                        // Sbid (Views): adjust the CURRENT C Bid (bid_percentage) by the
                        // row's L7 View colour band (direction + step), clamped to the
                        // Min/Max caps. Green = no change (keep current C Bid). No current
                        // C Bid → skip (nothing to adjust). If E L30 sold ≤ no_dec_max_el30,
                        // Decrease steps are skipped.
                        $baseBid = (float) ($listing->bid_percentage ?? 0);
                        $l7views = (float) ($ebayMetric->l7_views ?? 0);
                        $newBid  = \App\Support\SbidViewsRule::apply(
                            $baseBid,
                            $l7views,
                            $avgL7Views,
                            $sbidViewsSettings,
                            $soldL30
                        );

                        $listing->new_bid = $newBid;
                        $listing->sku = $pm->sku;

                        if ($newBid <= 0) {
                            $this->warn("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | Views: {$views} | L7: {$l7views} | E L30: {$soldL30} | C Bid: {$baseBid} → No current C Bid (skipped)");
                        } else {
                            $this->info("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | Views: {$views} | L7: {$l7views} | E L30: {$soldL30} | C Bid: {$baseBid} | SBID (Views): {$newBid}");
                            $updatedListings++;
                        }
                    }
                }
                $bidProcessedCount += $productMasters->count();
                $monitor->incrementProcessed($productMasters->count());
                $monitor->checkpoint(['phase' => 'bid_calculation', 'processed' => $bidProcessedCount], $bidProcessedCount);
            });
        
        $this->info("Updated bids for {$updatedListings} listings.");

        $groupedByCampaign = collect($campaignListings)->groupBy('campaign_id');

        if ($groupedByCampaign->isEmpty()) {
            $this->info('No campaign listings to update.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("\n=== DRY RUN SUMMARY ===");
            $totalRequests = 0;
            foreach ($groupedByCampaign as $campaignId => $listings) {
                $requests = [];
                $seenListingIds = [];

                foreach ($listings as $listing) {
                    if (isset($listing->new_bid) && $listing->new_bid > 0) {
                        if (isset($seenListingIds[$listing->listing_id])) {
                            $this->warn("Duplicate listing_id {$listing->listing_id} found. SKU: " . ($listing->sku ?? 'unknown') . " | Previous bid: {$seenListingIds[$listing->listing_id]}, New bid: {$listing->new_bid}");
                        }
                        $seenListingIds[$listing->listing_id] = $listing->new_bid;
                        
                        $requests[] = [
                            'listingId' => $listing->listing_id,
                            'bidPercentage' => (string) $listing->new_bid
                        ];
                        $sku = $listing->sku ?? 'unknown';
                        $this->info("[DRY RUN] Would send to eBay - SKU: {$sku} | Listing ID: {$listing->listing_id} | Bid Percentage: {$listing->new_bid}");
                    }
                }

                if (!empty($requests)) {
                    $totalRequests += count($requests);
                    $this->info("[DRY RUN] Campaign {$campaignId}: Would send " . count($requests) . " bid updates to eBay API");
                }
            }
            $this->info("\n[DRY RUN] Total: {$totalRequests} bid updates would be sent across " . $groupedByCampaign->count() . " campaign(s)");
            $this->warn("\n=== DRY RUN COMPLETE - No actual changes were made ===");
            $monitor->mergeMeta(['dry_run' => true]);
            $monitor->markApiConnected();
            $monitor->setExpected($totalRequests);
            $monitor->setFetched($totalRequests);
            $monitor->setSkipped($totalRequests);
            return self::SUCCESS;
        }

        $monitor->markApiConnected();
        $monitor->setExpected($updatedListings);
        $monitor->setFetched($updatedListings);

        $client = new Client([
            'base_uri' => config('services.ebay.base_url'),
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

        $apiChunkSize = $this->monitoredChunkSize();
        $apiProcessed = 0;

        foreach ($groupedByCampaign as $campaignId => $listings) {
            $requests = [];
            $seenListingIds = []; // Track to avoid duplicates

            foreach ($listings as $listing) {
                if (isset($listing->new_bid) && $listing->new_bid > 0) {
                    // Avoid duplicate listing_ids in same campaign
                    if (isset($seenListingIds[$listing->listing_id])) {
                        $this->warn("Duplicate listing_id {$listing->listing_id} found. SKU: " . ($listing->sku ?? 'unknown') . " | Previous bid: {$seenListingIds[$listing->listing_id]}, New bid: {$listing->new_bid}");
                        // Use the latest bid value
                    }
                    $seenListingIds[$listing->listing_id] = $listing->new_bid;
                    
                    $requests[] = [
                        'listingId' => $listing->listing_id,
                        'bidPercentage' => (string) $listing->new_bid
                    ];
                    $sku = $listing->sku ?? 'unknown';
                    $this->info("Sending to eBay - SKU: {$sku} | Listing ID: {$listing->listing_id} | Bid Percentage: {$listing->new_bid}");
                }
            }

            if (empty($requests)) {
                continue;
            }

            foreach (array_chunk($requests, $apiChunkSize) as $chunkIndex => $requestChunk) {
                try {
                    $this->info("Campaign {$campaignId}: Sending " . count($requestChunk) . " bid update(s) to eBay API (chunk " . ($chunkIndex + 1) . ")...");
                    $apiStart = microtime(true);
                    $monitor->incrementApiCalls();
                    $response = $client->post(
                        "sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                        ['json' => ['requests' => $requestChunk]]
                    );
                    $monitor->incrementApiLatency((int) ((microtime(true) - $apiStart) * 1000));

                    $responseBody = $response->getBody()->getContents();
                    $statusCode = $response->getStatusCode();

                    $this->info("Campaign {$campaignId}: API Response Status: {$statusCode}");
                    if ($statusCode === 200 || $statusCode === 207) {
                        $this->info("Campaign {$campaignId}: Successfully updated " . count($requestChunk) . " listing(s).");
                        $monitor->incrementUpdated(count($requestChunk));
                        // Keep local C Bid in sync with what we just pushed to eBay.
                        foreach ($requestChunk as $req) {
                            DB::table('ebay_campaign_ads')
                                ->where('listing_id', (string) ($req['listingId'] ?? ''))
                                ->where('campaign_id', (string) $campaignId)
                                ->update([
                                    'bid_percentage' => round((float) ($req['bidPercentage'] ?? 0), 2),
                                    'updated_at' => now(),
                                ]);
                        }
                    } else {
                        $this->warn("Campaign {$campaignId}: Response: " . substr($responseBody, 0, 200));
                        foreach ($requestChunk as $req) {
                            $monitor->recordFailure(
                                sku: (string) ($req['listingId'] ?? ''),
                                marketplace: 'ebay',
                                reason: 'Unexpected API status: ' . $statusCode,
                                apiResponse: substr($responseBody, 0, 500),
                                httpStatus: $statusCode
                            );
                        }
                    }

                    $apiProcessed += count($requestChunk);
                    $monitor->checkpoint([
                        'phase' => 'api_push',
                        'campaign_id' => $campaignId,
                        'chunk' => $chunkIndex,
                        'processed' => $apiProcessed,
                    ], $apiProcessed);

                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
                    $this->error("Campaign {$campaignId}: Client error (Status: {$statusCode}).");
                    foreach ($requestChunk as $req) {
                        $monitor->recordFailure(
                            sku: (string) ($req['listingId'] ?? ''),
                            marketplace: 'ebay',
                            reason: $e->getMessage(),
                            httpStatus: $statusCode
                        );
                    }

                } catch (\GuzzleHttp\Exception\ServerException $e) {
                    $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
                    $this->error("Campaign {$campaignId}: Server error (Status: {$statusCode}).");
                    foreach ($requestChunk as $req) {
                        $monitor->recordFailure(
                            sku: (string) ($req['listingId'] ?? ''),
                            marketplace: 'ebay',
                            reason: $e->getMessage(),
                            httpStatus: $statusCode
                        );
                    }

                } catch (\Exception $e) {
                    $this->error("Campaign {$campaignId}: General error - " . $e->getMessage());
                    foreach ($requestChunk as $req) {
                        $monitor->recordFailure(
                            sku: (string) ($req['listingId'] ?? ''),
                            marketplace: 'ebay',
                            reason: $e->getMessage()
                        );
                    }
                }
            }
        }

            $this->info('eBay ad bid update finished.');
            return $monitor->failedRecords > 0 ? self::FAILURE : self::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            $monitor->classifyAndRecord($e);
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Command failed with error: ' . $e->getMessage());
            $monitor->classifyAndRecord($e);
            return self::FAILURE;
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function getEbayAccessToken()
    {
        try {
            if (Cache::has('ebay_access_token')) {
                return Cache::get('ebay_access_token');
            }

            $clientId = config('services.ebay.app_id');
            $clientSecret = config('services.ebay.cert_id');
            $refreshToken = config('services.ebay.refresh_token');
            
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

    /**
     * Get bid from dynamic SCVR bands rule.
     * Bands sorted ascending by scvr_max — first band where scvr <= scvr_max wins.
     *
     * Returns 0.0 when SCVR (CVR) is 0 — no L30 sales means we have no signal,
     * so no SBID is pushed for that listing. Callers must treat 0 as "skip".
     */
    /**
     * Combined SCVR + DIL bid.
     * If EITHER the SCVR value or the DIL value lands in its Pink (catch-all / last)
     * band, the Pink bid is pushed (e.g. 2.1). This applies even if BOTH are Pink.
     * Otherwise the normal SCVR rule decides (and still skips when SCVR = 0).
     */
    /**
     * Resolve S Bid from the Sbid Rule slabs. Each slab carries optional min/max
     * ranges on CVR, Dil, Esold and Views L30 plus an sbid value. The first slab
     * whose filled ranges all contain the row's values wins. Returns 0 when no
     * slab matches (caller treats 0 as "skip").
     */
    private function resolveSlabBid(float $cvr, float $dil, float $esold, float $views, array $slabs): float
    {
        foreach ($slabs as $s) {
            if ($this->slabInRange($cvr,   $s['cvr_min']   ?? null, $s['cvr_max']   ?? null)
                && $this->slabInRange($dil,   $s['dil_min']   ?? null, $s['dil_max']   ?? null)
                && $this->slabInRange($esold, $s['esold_min'] ?? null, $s['esold_max'] ?? null)
                && $this->slabInRange($views, $s['views_min'] ?? null, $s['views_max'] ?? null)) {
                return (float) ($s['sbid'] ?? 0);
            }
        }
        return 0.0;
    }

    private function slabInRange(float $val, $min, $max): bool
    {
        if ($min !== null && $min !== '' && $val < (float) $min) return false;
        if ($max !== null && $max !== '' && $val > (float) $max) return false;
        return true;
    }

    private function resolveCombinedBid(float $scvr, array $sbidBands, float $dil, array $dilBands, array $ctx = []): float
    {
        $scvrPink = $this->isPinkBand($scvr, $sbidBands);
        $dilPink  = $this->isPinkBand($dil, $dilBands);

        if ($dilPink) {
            return $this->pinkBid($dilBands);
        }
        if ($scvrPink) {
            return $this->pinkBid($sbidBands);
        }

        return $this->getBidFromRule($scvr, $sbidBands, $ctx);
    }

    /**
     * True when $value falls in the last (catch-all / Pink) band.
     * Bands are stored sorted ascending by their threshold, so the last band is Pink.
     */
    private function isPinkBand(float $value, array $bands): bool
    {
        $n = count($bands);
        if ($n === 0) {
            return false;
        }
        foreach ($bands as $i => $band) {
            $max = (float) ($band['scvr_max'] ?? $band['dil_max'] ?? 9999);
            if ($value <= $max) {
                return $i === $n - 1;
            }
        }
        return true; // matched none → catch-all (last band)
    }

    /** Bid of the last (Pink / catch-all) band. */
    private function pinkBid(array $bands): float
    {
        $last = end($bands);
        return (float) ($last['bid'] ?? 2.1);
    }

    private function defaultDilBands(): array
    {
        return [
            ['dil_max' => 16.66, 'bid' => 9.1, 'label' => 'Red',    'color' => '#a00211'],
            ['dil_max' => 25,    'bid' => 7.1, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['dil_max' => 50,    'bid' => 4.1, 'label' => 'Green',  'color' => '#28a745'],
            ['dil_max' => 9999,  'bid' => 2.1, 'label' => 'Pink',   'color' => '#e83e8c'],
        ];
    }

    private function getBidFromRule(float $scvr, array $bands, array $ctx = []): float
    {
        $ctx['scvr'] = $scvr;
        // First band whose [scvr_min, scvr_max] range contains the SCVR wins.
        foreach ($bands as $band) {
            $min = (float)($band['scvr_min'] ?? 0);
            $max = (float)($band['scvr_max'] ?? 9999);
            if ($scvr >= $min && $scvr <= $max) {
                return $this->resolveBandBid($band, $ctx);
            }
        }
        // Fallback: last band
        $last = end($bands);
        return $last ? $this->resolveBandBid($last, $ctx) : 2.1;
    }

    /**
     * Resolve a band's bid: the row's ES Bid when the band is flagged use_es_bid,
     * otherwise the band's flat bid.
     */
    private function resolveBandBid(array $band, array $ctx): float
    {
        // Band flagged to use the row's ES Bid (raw suggested_bid).
        if (!empty($band['use_es_bid'])) {
            return (float)($ctx['es_bid'] ?? 0);
        }
        return (float)($band['bid'] ?? 9.1);
    }

    private function defaultBands(): array
    {
        return [
            ['scvr_max' => 4,    'bid' => 9.1],
            ['scvr_max' => 7,    'bid' => 7.1],
            ['scvr_max' => 13,   'bid' => 4.1],
            ['scvr_max' => 9999, 'bid' => 2.1],
        ];
    }
}
