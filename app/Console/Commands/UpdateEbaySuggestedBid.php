<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Exception;

class UpdateEbaySuggestedBid extends Command
{
    protected $signature = 'ebay:update-suggestedbid {--dry-run : Run without making actual API calls}';
    protected $description = 'Bulk update eBay ad bids using suggested_bid percentages';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle() {
        try {
            $dryRun = $this->option('dry-run');
            
            if ($dryRun) {
                $this->warn('=== DRY RUN MODE - No actual changes will be made ===');
            }
            
            $this->info('Starting bulk eBay ad bid update...');

            $accessToken = null;
            if (!$dryRun) {
                $accessToken = $this->getEbayAccessToken();
                if (!$accessToken) {
                    $this->error('Failed to obtain eBay access token.');
                    return 1;
                }
            }

            // Process ProductMaster records in chunks to prevent "Too many connections" error
            $chunkSize = 1000;
            $totalRecords = ProductMaster::whereNull('deleted_at')->count();
            
            if ($totalRecords === 0) {
                $this->info('No product masters found.');
                return 0;
            }
            
            $this->info("Processing {$totalRecords} product masters in chunks of {$chunkSize}...");
            
            $allSkus = collect();
            $processedCount = 0;
            
            // Collect all SKUs first using chunked processing
            ProductMaster::whereNull('deleted_at')
                ->orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->chunk($chunkSize, function ($productMasters) use (&$allSkus, &$processedCount, $totalRecords) {
                    $chunkSkus = $productMasters->pluck("sku")->filter()->unique();
                    $allSkus = $allSkus->merge($chunkSkus);
                    $processedCount += $productMasters->count();
                    $this->info("Processed {$processedCount}/{$totalRecords} product masters...");
                });
            
            $skus = $allSkus->unique()->values()->all();
            
            if (empty($skus)) {
                $this->info('No valid SKUs found in product masters.');
                return 0;
            }
            
            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
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
                return 0;
            }
        
        // Normalize eBay metrics data keys
        $ebayMetricsNormalized = collect();
        foreach ($ebayMetrics as $item) {
            $normalizedKey = $normalizeSku($item->sku);
            $ebayMetricsNormalized[$normalizedKey] = $item;
        }

        // Load campaign listings efficiently
        $this->info('Loading campaign listings...');
        $campaignListings = DB::connection('apicentral')
            ->table('ebay_campaign_ads_listings')
            ->select('listing_id', 'campaign_id', 'bid_percentage', 'suggested_bid')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id')
            ->map(function ($item) {
                return (object) [
                    'listing_id' => $item->listing_id,
                    'campaign_id' => $item->campaign_id,
                    'bid_percentage' => $item->bid_percentage,
                    'suggested_bid' => $item->suggested_bid,
                    'new_bid' => null
                ];
            });

            if ($campaignListings->isEmpty()) {
                $this->info('No campaign listings found.');
                return 0;
            }

            // Get L30 data (clicks and sales) from ebaygeneral report for CVR calculation
            $this->info('Loading eBay general report data...');
            $ebayGeneralL30 = EbayGeneralReport::select('listing_id', 'clicks', 'sales')
                ->where('report_range', 'L30')
                ->get()
                ->keyBy('listing_id');
            
        // Load SCVR → Bid rule from ebay_sbid_rules table (fallback to hardcoded defaults)
        $sbidRuleRow = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        $sbidRuleData = $sbidRuleRow
            ? (json_decode($sbidRuleRow->rule, true) ?: [])
            : [];
        $sbidBands = $sbidRuleData['bands'] ?? $this->defaultBands();
        $l30SoldEsBidMax = (float) ($sbidRuleData['l30_sold_es_bid_max'] ?? 0);
        $l7ViewsThreshold = (float) ($sbidRuleData['l7_views_threshold'] ?? 70);
        $this->info('SBID Rule bands: ' . collect($sbidBands)->map(fn($b) => "SCVR≤{$b['scvr_max']}%→{$b['bid']}")->implode(', '));
        $this->info("SBID ES Bid fallback: L30 sold ≤ {$l30SoldEsBidMax} or L7 views < {$l7ViewsThreshold}");

        // Load DIL → Bid rule (ebay1_dil). Used together with SCVR: if EITHER the SCVR or
        // the DIL value falls in its Pink (catch-all / last) band, the Pink bid is pushed.
        $dilRule = DB::table('ebay_sbid_rules')->where('key', 'ebay1_dil')->first();
        $dilBands = $dilRule
            ? (json_decode($dilRule->rule, true)['bands'] ?? $this->defaultDilBands())
            : $this->defaultDilBands();
        $this->info('DIL Rule bands: ' . collect($dilBands)->map(fn($b) => "DIL≤{$b['dil_max']}%→{$b['bid']}")->implode(', '));

        // Process ProductMaster data in chunks and update campaign listings
        $this->info('Processing bid updates based on SCVR (eBay L30 / Views) thresholds...');
        $updatedListings = 0;
        
        ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->chunk($chunkSize, function ($productMasters) use (
                $shopifyData, 
                $ebayMetricsNormalized, 
                $campaignListings,
                $sbidBands,
                $dilBands,
                $l30SoldEsBidMax,
                $l7ViewsThreshold,
                $ebayGeneralL30, 
                &$updatedListings,
                $normalizeSku
            ) {
                foreach ($productMasters as $pm) {
                    $normalizedSku = $normalizeSku($pm->sku);
                    $shopify = $shopifyData[$normalizedSku] ?? null;
                    $ebayMetric = $ebayMetricsNormalized[$normalizedSku] ?? null;

                    if ($ebayMetric && $ebayMetric->item_id && $campaignListings->has($ebayMetric->item_id)) {
                        $listing = $campaignListings[$ebayMetric->item_id];
                        
                        // SCVR-based PMT S BID rule
                        $soldL30  = (float) ($ebayMetric->ebay_l30 ?? 0);
                        $views    = (float) ($ebayMetric->views ?? 0);
                        $l7Views  = (float) ($ebayMetric->l7_views ?? 0);
                        $esbid    = (float) ($listing->suggested_bid ?? 0);
                        $scvr     = $views > 0 ? ($soldL30 / $views) * 100 : 0;

                        // DIL = (L30 sold / inventory) * 100, from Shopify data
                        $inv = (float) ($shopify->inv ?? 0);
                        $qty = (float) ($shopify->quantity ?? 0);
                        $dil = $inv > 0 ? ($qty / $inv) * 100 : 0;

                        // ES Bid fallback (configurable) or SCVR/DIL Pink / SCVR bands.
                        if ($soldL30 <= $l30SoldEsBidMax || $l7Views < $l7ViewsThreshold) {
                            $newBid = $esbid;
                        } else {
                            $newBid = $this->resolveCombinedBid($scvr, $sbidBands, $dil, $dilBands, [
                                'ebay_price' => (float) ($ebayMetric->ebay_price ?? 0),
                                'ebay_l30'   => $soldL30,
                                'views'      => $views,
                            ]);
                        }

                        $listing->new_bid = $newBid;
                        $listing->sku = $pm->sku;

                        $scvrPink = $this->isPinkBand($scvr, $sbidBands);
                        $dilPink  = $this->isPinkBand($dil, $dilBands);
                        $pinkTag  = ($scvrPink || $dilPink)
                            ? ' | PINK(' . ($scvrPink ? 'SCVR' : '') . ($scvrPink && $dilPink ? '+' : '') . ($dilPink ? 'DIL' : '') . ')'
                            : '';

                        if ($newBid <= 0) {
                            // No ES Bid available when L30 sold = 0, or no SCVR/DIL signal otherwise.
                            $this->warn("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | SCVR: " . round($scvr, 2) . "% (sold={$soldL30}, views={$views}) DIL: " . round($dil, 2) . "% → No SBID (skipped)");
                        } else {
                            $this->info("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | SCVR: " . round($scvr, 2) . "% | DIL: " . round($dil, 2) . "% | SBID: {$newBid}{$pinkTag}");
                            $updatedListings++;
                        }
                    }
                }
            });
        
        $this->info("Updated bids for {$updatedListings} listings.");

        $groupedByCampaign = collect($campaignListings)->groupBy('campaign_id');

        if ($groupedByCampaign->isEmpty()) {
            $this->info('No campaign listings to update.');
            return 0;
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
            return 0;
        }

        $client = new Client([
            'base_uri' => config('services.ebay.base_url'),
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

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

            try {
                $this->info("Campaign {$campaignId}: Sending " . count($requests) . " bid updates to eBay API...");
                $response = $client->post(
                    "sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                    ['json' => ['requests' => $requests]]
                );
                
                $responseBody = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                
                $this->info("Campaign {$campaignId}: API Response Status: {$statusCode}");
                if ($statusCode === 200 || $statusCode === 207) {
                    $this->info("Campaign {$campaignId}: Successfully updated " . count($requests) . " listings.");
                } else {
                    $this->warn("Campaign {$campaignId}: Response: " . substr($responseBody, 0, 200));
                }
                
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
                $this->error("Campaign {$campaignId}: Client error (Status: {$statusCode}).");
                
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
                $this->error("Campaign {$campaignId}: Server error (Status: {$statusCode}).");
                
            } catch (\Exception $e) {
                $this->error("Campaign {$campaignId}: General error - " . $e->getMessage());
            }
        }

            $this->info('eBay ad bid update finished.');
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
        if ($scvr <= 0) {
            return 0.0;
        }
        $ctx['scvr'] = $scvr;
        foreach ($bands as $band) {
            if ($scvr <= (float)($band['scvr_max'] ?? 9999)) {
                return $this->resolveBandBid($band, $ctx);
            }
        }
        // Fallback: last band
        $last = end($bands);
        return $last ? $this->resolveBandBid($last, $ctx) : 2.1;
    }

    /**
     * Resolve a band's bid. If the band carries a dynamic sub-rule, the bid is
     * chosen from its sub-bands using the configured metric value; otherwise the
     * band's flat bid is used.
     *
     * sub = ['metric' => 'ebay_price'|'scvr'|'ebay_l30'|'views',
     *        'bands'  => [['max' => float, 'bid' => float], ...]]
     */
    private function resolveBandBid(array $band, array $ctx): float
    {
        $sub = $band['sub'] ?? null;
        if (is_array($sub) && !empty($sub['metric']) && !empty($sub['bands']) && is_array($sub['bands'])) {
            $val = (float)($ctx[$sub['metric']] ?? 0);
            foreach ($sub['bands'] as $sb) {
                if ($val <= (float)($sb['max'] ?? 9999)) {
                    return (float)($sb['bid'] ?? $band['bid'] ?? 2.1);
                }
            }
            $lastSub = end($sub['bands']);
            return (float)($lastSub['bid'] ?? $band['bid'] ?? 2.1);
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
