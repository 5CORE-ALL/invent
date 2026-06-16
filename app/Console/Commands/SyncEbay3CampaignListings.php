<?php

namespace App\Console\Commands;

use App\Models\Ebay3Metric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * eBay 3 — same pattern as `ebay:sync-campaign-listings` /
 * `ebay2:sync-campaign-listings` but for the third eBay account
 * (EBAY_3_APP_ID / EBAY_3_CERT_ID / EBAY_3_REFRESH_TOKEN).
 *
 * Fetches ALL eBay 3 campaign ads directly from eBay Marketing API +
 * suggested-bid/promote-with-ad info from Recommendation API, and inserts
 * every listing into `ebay3_campaign_ads` (default connection).
 *
 * NO product_master matching — just raw insert of all ad listings.
 */
class SyncEbay3CampaignListings extends Command
{
    protected $signature   = 'ebay3:sync-campaign-listings {--dry-run : Show what would be inserted without writing}';
    protected $description = 'Sync ALL eBay 3 campaign ad listings into ebay3_campaign_ads (no product_master filter)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('=== DRY RUN — nothing written ===');
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🔄 eBay 3 Campaign Ads Sync → ebay3_campaign_ads');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $token = $this->getToken();
        if (!$token) {
            $this->error('Failed to get eBay 3 access token.');
            return 1;
        }
        $this->info('✓ eBay 3 token obtained');

        $this->info('Fetching all eBay 3 campaigns...');
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

            $ads = $this->fetchAllAds($token, $campaignId);

            if (empty($ads)) {
                $this->line('   → No ads.');
                continue;
            }

            $this->line('   → ' . count($ads) . ' ads');

            foreach ($ads as $ad) {
                $listingId     = (string)($ad['listingId']     ?? '');
                $adId          = (string)($ad['adId']          ?? '');
                $bidPercentage = $ad['bidPercentage'] ?? $campaignBid;

                if (!$listingId) continue;

                $this->line("      listing_id={$listingId} | adId={$adId} | bid={$bidPercentage}%");

                if ($dryRun) continue;

                try {
                    $exists = DB::table('ebay3_campaign_ads')
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
                        DB::table('ebay3_campaign_ads')
                            ->where('listing_id', $listingId)
                            ->where('campaign_id', (string)$campaignId)
                            ->update($row);
                        $updated++;
                    } else {
                        $row['created_at'] = now();
                        DB::table('ebay3_campaign_ads')->insert($row);
                        $inserted++;
                    }
                } catch (\Exception $e) {
                    $this->error("      ❌ DB: " . $e->getMessage());
                    $skipped++;
                }
            }
        }

        // ── Step 2: Fetch eligible non-campaign listings from ebay_3_metrics ──
        if (!$dryRun) {
            $this->line('');
            $this->info('🔍 Fetching eligible eBay 3 listings not yet in any campaign...');

            try {
                $existingIds = DB::table('ebay3_campaign_ads')
                    ->pluck('listing_id')
                    ->map(fn($id) => (string)$id)
                    ->toArray();

                // Default-connection ebay_3_metrics (App\Models\Ebay3Metric).
                $metricsRaw = Ebay3Metric::query()
                    ->whereNotNull('item_id')
                    ->select('item_id', 'sku', 'ebay_price')
                    ->get();

                $notInCampaign = $metricsRaw->filter(fn($m) => !in_array((string)$m->item_id, $existingIds, true))->values();
                $this->info('Found ' . $notInCampaign->count() . ' listings not in any eBay 3 campaign.');

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

                            DB::table('ebay3_campaign_ads')->updateOrInsert(
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
                $this->warn('⚠ Skipping eligible-listings step (ebay_3_metrics not accessible): ' . $e->getMessage());
            }
        }

        // ── Step 3: Fetch suggested_bid for all listings (batch 20 per API call) ──
        if (!$dryRun) {
            $this->line('');
            $this->info('🔍 Fetching suggested bids from Recommendation API (batches of 20)...');

            $allListingIds = DB::table('ebay3_campaign_ads')
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

                        // Priority 1: ITEM basis
                        foreach ($bidPercs as $b) {
                            if (($b['basis'] ?? '') === 'ITEM' && isset($b['value'])) {
                                $suggestedBid = (float)$b['value'];
                                break;
                            }
                        }
                        // Priority 2: TRENDING
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
                                DB::table('ebay3_campaign_ads')
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
        if (!$dryRun) {
            $backfilled = DB::table('ebay3_campaign_ads')
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
     * Get eBay 3 access token using EBAY_3_* refresh_token credentials.
     * Cached separately under `ebay3_access_token`.
     */
    private function getToken(): ?string
    {
        if (Cache::has('ebay3_access_token')) {
            return Cache::get('ebay3_access_token');
        }

        $clientId     = config('services.ebay3.app_id', env('EBAY_3_APP_ID'));
        $clientSecret = config('services.ebay3.cert_id', env('EBAY_3_CERT_ID'));
        $refreshToken = config('services.ebay3.refresh_token', env('EBAY_3_REFRESH_TOKEN'));

        if (!$clientId || !$clientSecret || !$refreshToken) {
            $this->error('Missing eBay 3 credentials (EBAY_3_APP_ID / EBAY_3_CERT_ID / EBAY_3_REFRESH_TOKEN).');
            return null;
        }

        // IMPORTANT: omit `scope` so the new token inherits the scopes originally
        // granted to the refresh token (Marketing + Recommendation both need this).
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
            $this->error("eBay 3 token endpoint returned HTTP {$httpCode}: " . substr((string)$response, 0, 300));
            return null;
        }

        $data = json_decode((string)$response, true);
        if (!isset($data['access_token'])) {
            $this->error('eBay 3 token endpoint did not return access_token: ' . substr((string)$response, 0, 300));
            return null;
        }

        Cache::put('ebay3_access_token', $data['access_token'], ($data['expires_in'] ?? 7200) - 60);
        return $data['access_token'];
    }
}
