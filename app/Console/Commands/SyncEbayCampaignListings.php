<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Fetches ALL eBay campaign ads directly from eBay Marketing API
 * and inserts every listing into ebay_campaign_ads (inventory_db).
 *
 * NO product_master matching — just raw insert of all ad listings.
 * Runs daily at 11:30 IST before ebay:update-suggestedbid at 12:00 IST.
 */
class SyncEbayCampaignListings extends Command
{
    protected $signature   = 'ebay:sync-campaign-listings {--dry-run : Show what would be inserted without writing} {--eligible-only : Skip campaign fetch and only sync eligible (non-campaign) listings} {--bids-only : Skip campaign fetch and eligible sync; only refresh suggested_bid (ES bid) for existing listings}';
    protected $description = 'Sync ALL eBay campaign ad listings into inventory_db.ebay_campaign_ads (no product_master filter)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $eligibleOnly = $this->option('eligible-only');
        $bidsOnly = $this->option('bids-only');

        if ($dryRun) {
            $this->warn('=== DRY RUN — nothing written ===');
        }
        if ($eligibleOnly) {
            $this->warn('=== ELIGIBLE-ONLY — skipping campaign fetch (Step 1) and suggested-bid refresh (Step 3) ===');
        }
        if ($bidsOnly) {
            $this->warn('=== BIDS-ONLY — skipping campaign fetch (Step 1) and eligible sync (Step 2); only refreshing suggested bids (Step 3) ===');
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🔄 eBay Campaign Ads Sync → inventory_db.ebay_campaign_ads');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $token = $this->getToken();
        if (!$token) {
            $this->error('Failed to get eBay access token.');
            return 1;
        }
        $this->info('✓ Token obtained');

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        // ── Step 1: Fetch ALL campaigns and their ads (skipped in --eligible-only / --bids-only) ──
        if (!$eligibleOnly && !$bidsOnly) {
        // Fetch ALL campaigns (paginated)
        $this->info('Fetching all campaigns...');
        $campaigns = $this->fetchAllCampaigns($token);
        $this->info('Found ' . count($campaigns) . ' campaigns.');
        $this->line('');

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

                // Insert ALL — no product_master matching needed
                try {
                    $exists = DB::table('ebay_campaign_ads')
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
                        DB::table('ebay_campaign_ads')
                            ->where('listing_id', $listingId)
                            ->where('campaign_id', (string)$campaignId)
                            ->update($row);
                        $updated++;
                    } else {
                        $row['created_at'] = now();
                        DB::table('ebay_campaign_ads')->insert($row);
                        $inserted++;
                    }
                } catch (\Exception $e) {
                    $this->error("      ❌ DB: " . $e->getMessage());
                    $skipped++;
                }
            }
        }
        } // end Step 1 (!$eligibleOnly)

        // ── Step 2: Fetch eligible non-campaign listings from ebay_metrics ──
        if (!$dryRun && !$bidsOnly) {
            $this->line('');
            $this->info('🔍 Fetching eligible listings not yet in any campaign...');

            // Get all listing_ids already in our table
            $existingIds = DB::table('ebay_campaign_ads')
                ->pluck('listing_id')
                ->map(fn($id) => (string)$id)
                ->toArray();

            // Get all ebay_metrics listings NOT in campaign table
            $notInCampaign = \App\Models\EbayMetric::whereNotNull('item_id')
                ->get()
                ->filter(fn($m) => !in_array((string)$m->item_id, $existingIds));

            $this->info('Found ' . $notInCampaign->count() . ' listings not in any campaign.');

            $eligibleChunks = $notInCampaign->chunk(20);
            $eligibleInserted = 0;

            foreach ($eligibleChunks as $chunk) {
                $ids = $chunk->pluck('item_id')->map(fn($id) => (string)$id)->toArray();

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

                        // Get suggested_bid
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

                        // Get metric info for this listing
                        $metric = $chunk->first(fn($m) => (string)$m->item_id === $lid);
                        $sku    = $metric ? $metric->sku : null;
                        $price  = $metric ? $metric->ebay_price : null;

                        $this->line("  → listing_id={$lid} | sku={$sku} | promote={$promoteStatus} | es_bid={$suggestedBid}%");

                        DB::table('ebay_campaign_ads')->updateOrInsert(
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
        }

        // ── Step 3: Fetch suggested_bid for all listings (batch 20 per API call) ──
        if (!$dryRun && !$eligibleOnly) {
            $this->line('');
            $this->info('🔍 Fetching suggested bids from Recommendation API (batches of 20)...');

            $allListingIds = DB::table('ebay_campaign_ads')
                ->whereNotNull('listing_id')
                ->pluck('listing_id')
                ->unique()
                ->values()
                ->toArray();

            $chunks    = array_chunk($allListingIds, 20);
            $bidCount  = 0;

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
                        $lid          = $rec['listingId'] ?? null;
                        $bidPercs     = $rec['marketing']['ad']['bidPercentages'] ?? [];
                        $suggestedBid = null;

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

                        if ($lid && $suggestedBid !== null) {
                            DB::table('ebay_campaign_ads')
                                ->where('listing_id', (string)$lid)
                                ->update(['suggested_bid' => $suggestedBid, 'updated_at' => now()]);
                            $bidCount++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn('  ⚠ Recommendation batch error: ' . $e->getMessage());
                }

                // Small delay to avoid rate limiting
                usleep(200000); // 0.2 seconds
            }

            $this->info("✅ Suggested bids updated: {$bidCount} listings");
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

    private function getToken(): ?string
    {
        if (Cache::has('ebay_access_token')) {
            return Cache::get('ebay_access_token');
        }

        $clientId     = config('services.ebay.app_id');
        $clientSecret = config('services.ebay.cert_id');
        $refreshToken = config('services.ebay.refresh_token');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            $this->error('Missing eBay credentials in config/services.php');
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.ebay.com/identity/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                // sell.marketing → ad_campaign/ad endpoints (Step 1);
                // sell.inventory → Recommendation API findListingRecommendations
                // (Steps 2 & 3). Without sell.inventory those calls return 403.
                'scope'         => 'https://api.ebay.com/oauth/api_scope/sell.marketing https://api.ebay.com/oauth/api_scope/sell.inventory',
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode("{$clientId}:{$clientSecret}"),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return null;

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) return null;

        Cache::put('ebay_access_token', $data['access_token'], ($data['expires_in'] ?? 7200) - 60);
        return $data['access_token'];
    }
}
