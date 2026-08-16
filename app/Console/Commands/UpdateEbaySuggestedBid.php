<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
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

            $this->info('Loading eBay metrics data...');
            $ebayMetrics = collect();
            
            if (!empty($skus)) {
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

        // Load View VS SBID slabs (ebay1_sbid_slabs) — For L7 Views → S Bid.
        // Same 0–100 / 101–200 / … / >1000 rules as /ebay-tabulator-view.
        $slabRow = DB::table('ebay_sbid_rules')->where('key', 'ebay1_sbid_slabs')->first();
        $slabDecoded = $slabRow ? json_decode($slabRow->rule, true) : null;
        $sbidSlabs = is_array($slabDecoded['rules'] ?? null) ? $slabDecoded['rules'] : [];
        $esBidOverride = isset($slabDecoded['es_bid']) && $slabDecoded['es_bid'] !== '' && $slabDecoded['es_bid'] !== null
            ? (float) $slabDecoded['es_bid']
            : 0.0;
        if (! is_array($sbidSlabs) || $this->sbidSlabsNeedViewStepMigrate($sbidSlabs)) {
            $sbidSlabs = $this->defaultSbidSlabRules();
            DB::table('ebay_sbid_rules')->updateOrInsert(
                ['key' => 'ebay1_sbid_slabs'],
                ['rule' => json_encode(['rules' => $sbidSlabs, 'es_bid' => $esBidOverride > 0 ? $esBidOverride : null]), 'updated_at' => now()]
            );
        }
        $this->info('SBID slab rules loaded: ' . count($sbidSlabs) . ' (EL30=0 → ES Bid; else For L7 Views → S Bid)');

        // Process ProductMaster data in chunks and update campaign listings
        $this->info('Processing bid updates based on Sbid Rule slabs...');
        $updatedListings = 0;
        $bidProcessedCount = 0;
        
        ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->chunk($chunkSize, function ($productMasters) use (
                $ebayMetricsNormalized, 
                $campaignListings,
                $sbidSlabs,
                $esBidOverride,
                &$updatedListings,
                &$bidProcessedCount,
                $normalizeSku,
                $monitor
            ) {
                foreach ($productMasters as $pm) {
                    $normalizedSku = $normalizeSku($pm->sku);
                    $ebayMetric = $ebayMetricsNormalized[$normalizedSku] ?? null;

                    if ($ebayMetric && $ebayMetric->item_id && $campaignListings->has($ebayMetric->item_id)) {
                        $listing = $campaignListings[$ebayMetric->item_id];

                        $l7Views = (float) ($ebayMetric->l7_views ?? 0);
                        $el30 = (float) ($ebayMetric->ebay_l30 ?? 0);

                        // EL30 = 0 → ES Bid (editable override, else row suggested_bid).
                        // Otherwise View VS SBID slabs (same as /ebay-tabulator-view S BID column).
                        if ($el30 <= 0) {
                            $newBid = $esBidOverride > 0
                                ? $esBidOverride
                                : (float) ($listing->suggested_bid ?? 0);
                        } else {
                            $newBid = $this->resolveSlabBid($l7Views, $sbidSlabs);
                        }

                        $listing->new_bid = $newBid;
                        $listing->sku = $pm->sku;

                        if ($newBid <= 0) {
                            $why = $el30 <= 0 ? 'EL30=0 and no ES Bid' : 'No matching View VS SBID slab';
                            $this->warn("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | EL30: {$el30} | L7 Views: {$l7Views} → {$why} (skipped)");
                        } else {
                            $via = $el30 <= 0 ? 'ES Bid' : 'slab';
                            $this->info("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | EL30: {$el30} | L7 Views: {$l7Views} | {$via}: {$newBid}");
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
     * Default View VS SBID slabs: 0–100, 101–200, … 901–1000, then >1000.
     * Same as EbayController::defaultSbidSlabRules().
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultSbidSlabRules(): array
    {
        $rules = [];
        $bid = 15;
        for ($i = 0; $i < 10; $i++) {
            $min = $i === 0 ? 0 : ($i * 100) + 1;
            $max = ($i + 1) * 100;
            $rules[] = [
                'label' => $min . '–' . $max,
                'l7_views_min' => $min,
                'l7_views_max' => $max,
                'sbid' => $bid,
            ];
            $bid--;
        }
        $rules[] = [
            'label' => '>1000',
            'l7_views_min' => 1001,
            'l7_views_max' => null,
            'sbid' => $bid,
        ];

        return $rules;
    }

    /** True when stored slabs are not yet the 0–100 / 101–200 / … / >1000 set. */
    private function sbidSlabsNeedViewStepMigrate(array $rules): bool
    {
        if ($rules === [] || count($rules) < 11) {
            return true;
        }
        $first = $rules[0] ?? [];

        return (float) ($first['l7_views_min'] ?? -1) !== 0.0
            || (float) ($first['l7_views_max'] ?? -1) !== 100.0;
    }

    /**
     * Resolve S Bid from View VS SBID slabs (For L7 Views only).
     * The first slab whose L7 Views range contains the row wins.
     * Returns 0 when no slab matches (caller treats 0 as "skip").
     */
    private function resolveSlabBid(float $l7Views, array $slabs): float
    {
        foreach ($slabs as $s) {
            if ($this->slabInRange($l7Views, $s['l7_views_min'] ?? null, $s['l7_views_max'] ?? null)) {
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
