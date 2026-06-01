<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * eBay 2 — same pattern as `ebay:sync-campaign-listings` but for the second
 * eBay account (EBAY2_APP_ID / EBAY2_CERT_ID / EBAY2_REFRESH_TOKEN).
 *
 * Fetches ALL eBay 2 campaign ads directly from eBay Marketing API +
 * suggested-bid/promote-with-ad info from Recommendation API, and inserts
 * every listing into `ebay2_campaign_ads` (default connection).
 *
 * NO product_master matching — just raw insert of all ad listings.
 */
class SyncEbay2CampaignListings extends Command
{
    protected $signature   = 'ebay2:sync-campaign-listings {--dry-run : Show what would be inserted without writing}';
    protected $description = 'Sync ALL eBay 2 campaign ad listings into ebay2_campaign_ads (no product_master filter)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('=== DRY RUN — nothing written ===');
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🔄 eBay 2 Campaign Ads Sync → ebay2_campaign_ads');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $token = $this->getToken();
        if (!$token) {
            $this->error('Failed to get eBay 2 access token.');
            return 1;
        }
        $this->info('✓ eBay 2 token obtained');

        // Fetch ALL campaigns (paginated)
        $this->info('Fetching all eBay 2 campaigns...');
        $campaigns = $this->fetchAllCampaigns($token);
        $this->info('Found ' . count($campaigns) . ' campaigns.');
        $this->line('');

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($campaigns as $campaign) {
            $campaignId     = $campaign['campaignId']     ?? null;
            $campaignName   = $campaign['campaignName']   ?? '';
            $campaignStatus = $campaign['campaignStatus'] ?? '';
            $funding        = $campaign['fundingStrategy']['fundingModel'] ?? null;
            $campaignBid    = $campaign['fundingStrategy']['bidPercentage'] ?? null;

            if (!$campaignId) continue;

            $this->line("📋 {$campaignName} | {$funding} | {$campaignStatus}");

            // Fetch ALL ads in this campaign (paginated)
            $ads = $this->fetchAllAds($token, $campaignId);

            if (empty($ads)) {
                $this->line('   → No ads.');
                continue;
            }

            $this->line('   → ' . count($ads) . ' ads');

            foreach ($ads as $ad) {
                $listingId     = (string)($ad['listingId']    ?? '');
                $adId          = (string)($ad['adId']          ?? '');
                $bidPercentage = $ad['bidPercentage'] ?? $campaignBid;

                if (!$listingId) continue;

                $this->line("      listing_id={$listingId} | adId={$adId} | bid={$bidPercentage}%");

                if ($dryRun) continue;

                try {
                    $exists = DB::table('ebay2_campaign_ads')
                        ->where('listing_id', $listingId)
                        ->where('campaign_id', (string)$campaignId)
                        ->exists();

                    $row = [
                        'campaign_id'      => (string)$campaignId,
                        'campaign_name'    => $campaignName,
                        'funding_strategy' => $funding,
                        'campaign_status'  => $campaignStatus,
                        'ad_id'            => $adId ?: null,
                        'listing_id'       => $listingId,
                        'bid_percentage'   => $bidPercentage !== null ? round((float)$bidPercentage, 2) : null,
                        'updated_at'       => now(),
                    ];

                    if ($exists) {
                        DB::table('ebay2_campaign_ads')
                            ->where('listing_id', $listingId)
                            ->where('campaign_id', (string)$campaignId)
                            ->update($row);
                        $updated++;
                    } else {
                        $row['created_at'] = now();
                        DB::table('ebay2_campaign_ads')->insert($row);
                        $inserted++;
                    }
                } catch (\Exception $e) {
                    $this->error("      ❌ DB: " . $e->getMessage());
                    $skipped++;
                }
            }
        }

        // ── Step 2: Fetch eligible non-campaign listings from ebay2_metrics ──
        if (!$dryRun) {
            $this->line('');
            $this->info('🔍 Fetching eligible eBay 2 listings not yet in any campaign...');

            try {
                // Get all listing_ids already in our table
                $existingIds = DB::table('ebay2_campaign_ads')
                    ->pluck('listing_id')
                    ->map(fn($id) => (string)$id)
                    ->toArray();

                // Get all ebay2_metrics listings NOT in campaign table
                // Connection: apicentral (matches existing ebay2 codebase usage)
                $metricsRaw = DB::connection('apicentral')->table('ebay2_metrics')
                    ->whereNotNull('item_id')
                    ->select('item_id', 'sku', 'ebay_price')
                    ->get();

                $notInCampaign = $metricsRaw->filter(fn($m) => !in_array((string)$m->item_id, $existingIds, true))->values();
                $this->info('Found ' . $notInCampaign->count() . ' listings not in any eBay 2 campaign.');

                $eligibleChunks   = $notInCampaign->chunk(20);
                $eligibleInserted = 0;

                foreach ($eligibleChunks as $chunk) {
                    $ids = $chunk->pluck('item_id')->map(fn($id) => (string)$id)->values()->toArray();

                    try {
                        $resp = Http::withToken($token)
                            ->withHeaders([
                                'X-EBAY-C-MARKETPLACE-ID' => 'EBAY-US',
                                'Content-Type'             => 'application/json',
                            ])
                            ->post('https://api.ebay.com/sell/recommendation/v1/find?filter=recommendationTypes:{AD}&limit=20',
                                ['listingIds' => $ids]);

                        foreach ($resp->json()['listingRecommendations'] ?? [] as $rec) {
                            $lid           = (string)($rec['listingId'] ?? '');
                            $promoteStatus = $rec['marketing']['ad']['promoteWithAd'] ?? null;
                            $bidPercs      = $rec['marketing']['ad']['bidPercentages'] ?? [];

                            if (!$lid) continue;

                            // Get suggested_bid (ITEM basis preferred, then TRENDING)
                            $suggestedBid = null;
                            foreach ($bidPercs as $b) {
                                if (($b['basis'] ?? '') === 'ITEM' && isset($b['value'])) {
                                    $suggestedBid = (float)$b['value']; break;
                                }
                            }
                            if ($suggestedBid === null) {
                                foreach ($bidPercs as $b) {
                                    if (($b['basis'] ?? '') === 'TRENDING' && isset($b['value'])) {
                                        $suggestedBid = (float)$b['value']; break;
                                    }
                                }
                            }

                            $metric = $chunk->first(fn($m) => (string)$m->item_id === $lid);
                            $sku    = $metric ? $metric->sku : null;
                            $price  = $metric ? $metric->ebay_price : null;

                            $this->line("  → listing_id={$lid} | sku={$sku} | promote={$promoteStatus} | es_bid={$suggestedBid}%");

                            DB::table('ebay2_campaign_ads')->updateOrInsert(
                                ['listing_id' => $lid, 'campaign_id' => null],
                                [
                                    'campaign_id'      => null,
                                    'campaign_name'    => null,
                                    'funding_strategy' => null,
                                    'campaign_status'  => null,
                                    'ad_id'            => null,
                                    'listing_id'       => $lid,
                                    'sku'              => $sku,
                                    'bid_percentage'   => null,
                                    'suggested_bid'    => $suggestedBid,
                                    'price'            => $price,
                                    'promote_with_ad'  => $promoteStatus,
                                    'updated_at'       => now(),
                                    'created_at'       => now(),
                                ]
                            );
                            $eligibleInserted++;
                        }
                    } catch (\Exception $e) {
                        $this->warn('  ⚠ Eligible fetch error: ' . $e->getMessage());
                    }
                    usleep(200000);
                }

                $this->info("✅ Eligible listings inserted/updated: {$eligibleInserted}");
            } catch (\Exception $e) {
                $this->warn('⚠ Skipping eligible-listings step (apicentral.ebay2_metrics not accessible): ' . $e->getMessage());
            }
        }

        // ── Step 3: Fetch suggested_bid for all listings (batch 20 per API call) ──
        if (!$dryRun) {
            $this->line('');
            $this->info('🔍 Fetching suggested bids from Recommendation API (batches of 20)...');

            $allListingIds = DB::table('ebay2_campaign_ads')
                ->whereNotNull('listing_id')
                ->pluck('listing_id')
                ->unique()
                ->values()
                ->toArray();

            $chunks   = array_chunk($allListingIds, 20);
            $bidCount = 0;

            foreach ($chunks as $chunk) {
                try {
                    $resp = Http::withToken($token)
                        ->withHeaders([
                            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY-US',
                            'Content-Type'             => 'application/json',
                        ])
                        ->post('https://api.ebay.com/sell/recommendation/v1/find?filter=recommendationTypes:{AD}&limit=20',
                            ['listingIds' => $chunk]);

                    $recommendations = $resp->json()['listingRecommendations'] ?? [];

                    foreach ($recommendations as $rec) {
                        $lid           = $rec['listingId'] ?? null;
                        $promoteStatus = $rec['marketing']['ad']['promoteWithAd'] ?? null;
                        $bidPercs      = $rec['marketing']['ad']['bidPercentages'] ?? [];
                        $suggestedBid  = null;

                        // Priority 1: ITEM basis (most accurate)
                        foreach ($bidPercs as $b) {
                            if (($b['basis'] ?? '') === 'ITEM' && isset($b['value'])) {
                                $suggestedBid = (float)$b['value'];
                                break;
                            }
                        }
                        // Priority 2: TRENDING (category average)
                        if ($suggestedBid === null) {
                            foreach ($bidPercs as $b) {
                                if (($b['basis'] ?? '') === 'TRENDING' && isset($b['value'])) {
                                    $suggestedBid = (float)$b['value'];
                                    break;
                                }
                            }
                        }
                        // Fallback: first available
                        if ($suggestedBid === null && isset($bidPercs[0]['value'])) {
                            $suggestedBid = (float)$bidPercs[0]['value'];
                        }

                        if ($lid) {
                            $update = ['updated_at' => now()];
                            if ($suggestedBid !== null) $update['suggested_bid']   = $suggestedBid;
                            if ($promoteStatus)         $update['promote_with_ad'] = $promoteStatus;

                            if (count($update) > 1) {
                                DB::table('ebay2_campaign_ads')
                                    ->where('listing_id', (string)$lid)
                                    ->update($update);
                                $bidCount++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn('  ⚠ Recommendation batch error: ' . $e->getMessage());
                }

                usleep(200000); // 0.2 seconds — rate-limit friendly
            }

            $this->info("✅ Suggested bids / promote status updated: {$bidCount} listings");
        }

        // ── Step 4: Backfill promote_with_ad for any in-campaign row missing it ──
        // The Recommendation API doesn't always return promoteWithAd for in-campaign
        // listings. But "in a campaign" by definition means an ad already exists, so
        // default these to AD_ALREADY_CREATED (matches the eBay 1 data shape).
        if (!$dryRun) {
            $backfilled = DB::table('ebay2_campaign_ads')
                ->whereNotNull('campaign_id')
                ->where(function ($q) {
                    $q->whereNull('promote_with_ad')->orWhere('promote_with_ad', '');
                })
                ->update(['promote_with_ad' => 'AD_ALREADY_CREATED', 'updated_at' => now()]);
            if ($backfilled > 0) {
                $this->info("✅ Backfilled promote_with_ad=AD_ALREADY_CREATED on {$backfilled} in-campaign rows.");
            }
        }

        $this->line('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        if ($dryRun) {
            $this->info('DRY RUN complete — no data written.');
        } else {
            $this->info("✅ Inserted: {$inserted} | Updated: {$updated} | Errors: {$skipped}");
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return 0;
    }

    private function fetchAllCampaigns(string $token): array
    {
        $all    = [];
        $offset = 0;
        $limit  = 200;

        do {
            $resp  = Http::withToken($token)
                ->get('https://api.ebay.com/sell/marketing/v1/ad_campaign', [
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);
            $data  = $resp->json();
            $batch = $data['campaigns'] ?? [];
            $total = (int)($data['total'] ?? 0);
            $all   = array_merge($all, $batch);
            $offset += $limit;
        } while (count($all) < $total && !empty($batch));

        return $all;
    }

    private function fetchAllAds(string $token, string $campaignId): array
    {
        $all    = [];
        $offset = 0;
        $limit  = 200;

        do {
            $resp  = Http::withToken($token)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);
            $data  = $resp->json();
            $batch = $data['ads'] ?? [];
            $total = (int)($data['total'] ?? 0);
            $all   = array_merge($all, $batch);
            $offset += $limit;
        } while (count($all) < $total && !empty($batch));

        return $all;
    }

    /**
     * Get eBay 2 access token using EBAY2_* refresh_token credentials.
     * Cached separately from eBay 1 token under `ebay2_access_token`.
     */
    private function getToken(): ?string
    {
        if (Cache::has('ebay2_access_token')) {
            return Cache::get('ebay2_access_token');
        }

        $clientId     = config('services.ebay2.app_id');
        $clientSecret = config('services.ebay2.cert_id');
        $refreshToken = config('services.ebay2.refresh_token');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            $this->error('Missing eBay 2 credentials in config/services.php (EBAY2_APP_ID / EBAY2_CERT_ID / EBAY2_REFRESH_TOKEN).');
            return null;
        }

        // IMPORTANT: Do NOT send a `scope` parameter on refresh.
        // When `scope` is included, eBay returns a token with reduced privileges
        // and the Recommendation API replies 403 ("Insufficient permissions").
        // Omitting `scope` makes the new access token inherit ALL scopes that
        // were granted to the refresh_token at consent time — which is what
        // the Recommendation + Marketing APIs both need.
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.ebay.com/identity/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode("{$clientId}:{$clientSecret}"),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->error("eBay 2 token endpoint returned HTTP {$httpCode}: " . substr((string)$response, 0, 300));
            return null;
        }

        $data = json_decode((string)$response, true);
        if (!isset($data['access_token'])) {
            $this->error('eBay 2 token endpoint did not return access_token: ' . substr((string)$response, 0, 300));
            return null;
        }

        Cache::put('ebay2_access_token', $data['access_token'], ($data['expires_in'] ?? 7200) - 60);
        return $data['access_token'];
    }
}
