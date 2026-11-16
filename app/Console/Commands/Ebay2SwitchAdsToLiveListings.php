<?php

namespace App\Console\Commands;

use App\Models\Ebay2Metric;
use App\Services\Ebay2ApiService;
use App\Support\Marketplace\EbayListingEnded;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * eBay 2 — when a SKU is relisted under a new item id, the Promoted Listings (PMT / COST_PER_SALE)
 * ad on the old item id disappears on eBay but the campaign stays RUNNING. `ebay2_campaign_ads` keeps a
 * "ghost" row for the old item id, so the new live listing never gets an ad.
 *
 * For every ghost row this command:
 *   1. confirms with eBay that the ad on the old listing no longer exists,
 *   2. finds the live listing for the SAME SKU (ebay_2_metrics, exact SKU only),
 *   3. creates the ad on that listing in the SAME campaign with the same bid %,
 *   4. replaces the ghost row with the new ad row.
 *
 * Safe to run often: it only touches rows whose listing is not live, and only after eBay confirmed
 * the ad is gone. COST_PER_CLICK (KW) campaigns are not handled (they need keywords / ad groups).
 */
class Ebay2SwitchAdsToLiveListings extends Command
{
    protected $signature = 'ebay2:switch-ads-to-live-listings
        {--dry-run : Show what would change without calling eBay create/writing the DB}
        {--limit=60 : Max ghost ads to process per run}
        {--prune : Also delete confirmed ghost rows that have no live listing for their SKU}';

    protected $description = 'Move eBay 2 PMT ads from ended/old item ids to the SKU\'s current live listing (same campaign, same bid)';

    private const TABLE = 'ebay2_campaign_ads';

    /** eBay lookups per run (each ghost costs 1-2 GETs). */
    private const MAX_EBAY_CHECKS = 450;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $prune = (bool) $this->option('prune');

        $liveByItem = $this->liveItemIds();
        $ghosts = $this->ghostRows($liveByItem);

        $this->info(($dryRun ? '[DRY RUN] ' : '').'eBay 2 ad switch: '.count($ghosts).' ad row(s) point at a listing that is not live.');
        if ($ghosts === []) {
            return self::SUCCESS;
        }

        try {
            $token = (new Ebay2ApiService())->generateBearerToken();
        } catch (\Throwable $e) {
            $this->error('Token error: '.$e->getMessage());

            return self::FAILURE;
        }

        $stats = ['switched' => 0, 'already_ok' => 0, 'pruned' => 0, 'no_live_listing' => 0, 'no_bid' => 0, 'ebay_still_has_ad' => 0, 'failed' => 0];
        $done = 0;
        $checks = 0;

        foreach ($ghosts as $row) {
            if ($done >= $limit) {
                $this->line('Limit reached ('.$limit.'); the rest runs on the next schedule.');
                break;
            }

            $campaignId = (string) $row->campaign_id;
            $oldId = trim((string) $row->listing_id);
            $sku = $this->resolveSku($row);
            $tag = "{$row->campaign_name} | old={$oldId} | sku=".($sku !== '' ? $sku : '-');

            // Local lookup first: a SKU with no live listing needs no eBay call at all (unless --prune).
            $newId = $sku !== '' ? $this->liveItemIdForSku($sku, $oldId, $liveByItem) : '';
            if ($newId === '' && ! $prune) {
                $stats['no_live_listing']++;
                continue;
            }

            // Ads that really still exist on eBay (e.g. a temporarily inactive listing) are re-checked only every few hours.
            $hasAdKey = "ebay2_switch_ads:has_ad:{$campaignId}:{$oldId}";
            if (Cache::has($hasAdKey)) {
                $stats['ebay_still_has_ad']++;
                continue;
            }
            if ($checks >= self::MAX_EBAY_CHECKS) {
                $this->line('eBay check cap reached ('.self::MAX_EBAY_CHECKS.'); the rest runs on the next schedule.');
                break;
            }
            $checks++;

            // 1. eBay is the source of truth: only a campaign that really has no ad on the old listing is a ghost.
            $exists = $this->adExistsOnEbay($token, $campaignId, $oldId);
            if ($exists === null) {
                $this->warn("  ? {$tag} → could not check eBay, skipped");
                $stats['failed']++;
                continue;
            }
            if ($exists) {
                Cache::put($hasAdKey, 1, now()->addHours(6));
                $stats['ebay_still_has_ad']++;
                continue;
            }
            $done++;

            // 2. Current live listing for the same SKU (looked up above). None: only --prune removes the dead row.
            if ($newId === '') {
                $this->line("  ✂ {$tag} → no live listing, ghost row removed");
                if (! $dryRun) {
                    DB::table(self::TABLE)->where('id', $row->id)->delete();
                }
                $stats['pruned']++;
                continue;
            }

            // The new listing already has an ad in this campaign on eBay (e.g. created by hand): just fix the mirror.
            $newExists = $this->adExistsOnEbay($token, $campaignId, $newId);
            if ($newExists === null) {
                $this->warn("  ? {$tag} → could not check eBay for new listing {$newId}, skipped");
                $stats['failed']++;
                continue;
            }

            $bid = $this->bidFor($row, $newId);
            if (! $newExists && $bid <= 0) {
                $this->line("  – {$tag} → new={$newId} but no bid % to reuse");
                $stats['no_bid']++;
                continue;
            }

            if ($dryRun) {
                $this->line("  ✔ would switch {$tag} → new={$newId}".($newExists ? ' (ad already on eBay)' : " @ {$bid}%"));
                $stats['switched']++;
                continue;
            }

            $adId = null;
            if (! $newExists) {
                $resp = Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                        'listingId' => $newId,
                        'bidPercentage' => (string) $bid,
                    ]);
                if (! ($resp->successful() || $resp->status() === 201)) {
                    $msg = (string) ($resp->json()['errors'][0]['message'] ?? $resp->status());
                    $this->error("  ✗ {$tag} → new={$newId}: {$msg}");
                    $stats['failed']++;
                    continue;
                }
                $adId = $resp->json('adId');
                if (! $adId) {
                    $location = (string) $resp->header('Location');
                    $adId = $location !== '' ? basename($location) : null;
                }
            }

            $this->saveSwitchedRow($row, $newId, $sku, $bid, $adId);
            $this->line("  ✔ {$tag} → new={$newId}".($newExists ? ' (ad already on eBay)' : " @ {$bid}%"));
            $stats['switched']++;
            usleep(150000);
        }

        $this->info('Switched: '.$stats['switched']
            .' | No live listing: '.$stats['no_live_listing']
            .' | Pruned: '.$stats['pruned']
            .' | No bid: '.$stats['no_bid']
            .' | eBay still has ad: '.$stats['ebay_still_has_ad']
            .' | Failed: '.$stats['failed']);

        return self::SUCCESS;
    }

    /**
     * item_id => true for every ebay_2_metrics listing that is not ended / not listed.
     *
     * @return array<string, true>
     */
    private function liveItemIds(): array
    {
        $live = [];
        foreach (Ebay2Metric::query()->whereNotNull('item_id')->get(['item_id', 'listing_status']) as $m) {
            $id = trim((string) $m->item_id);
            if ($id === '') {
                continue;
            }
            $status = $m->listing_status;
            if (EbayListingEnded::isEnded($status) || EbayListingEnded::isNotListed($status)) {
                continue;
            }
            $live[$id] = true;
        }

        return $live;
    }

    /**
     * PMT (COST_PER_SALE) rows that sit in a campaign but whose listing is not a live listing.
     *
     * @param  array<string, true>  $live
     * @return list<object>
     */
    private function ghostRows(array $live): array
    {
        $rows = DB::table(self::TABLE)
            ->where('funding_strategy', 'COST_PER_SALE')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->whereNotNull('listing_id')
            ->whereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) IN ('RUNNING', 'PAUSED', 'SYSTEM_PAUSED')")
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if (! isset($live[trim((string) $row->listing_id)])) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** SKU of the ad: its own sku, else the PARENT campaign name ("PARENT X" / "PARENT X KW"). */
    private function resolveSku(object $row): string
    {
        $sku = trim((string) ($row->sku ?? ''));
        if ($sku !== '') {
            return $sku;
        }
        $name = trim((string) preg_replace('/\s+KW$/i', '', trim((string) ($row->campaign_name ?? ''))));

        return stripos($name, 'PARENT ') === 0 ? $name : '';
    }

    /**
     * Live item id for this exact SKU (OPEN BOX / pack variants are different products, so no fuzzy match).
     *
     * @param  array<string, true>  $live
     */
    private function liveItemIdForSku(string $sku, string $oldId, array $live): string
    {
        $metric = EbayListingEnded::preferredRow(Ebay2Metric::class, $sku);
        $id = trim((string) ($metric?->item_id ?? ''));

        return ($id !== '' && $id !== $oldId && isset($live[$id])) ? $id : '';
    }

    /** Old ad's bid %, else the suggested bid already stored for the new listing. */
    private function bidFor(object $row, string $newId): float
    {
        $bid = (float) ($row->bid_percentage ?? 0);
        if ($bid <= 0) {
            $bid = (float) ($row->suggested_bid ?? 0);
        }
        if ($bid <= 0) {
            $bid = (float) (DB::table(self::TABLE)->where('listing_id', $newId)->max('suggested_bid') ?? 0);
        }

        return $bid >= 2.0 && $bid <= 100.0 ? round($bid, 1) : 0.0;
    }

    /** true / false, or null when eBay could not be asked (never delete or create on null). */
    private function adExistsOnEbay(string $token, string $campaignId, string $listingId): ?bool
    {
        try {
            $resp = Http::withToken($token)
                ->timeout(60)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                    'listing_ids' => $listingId,
                ]);
        } catch (\Throwable) {
            return null;
        }
        if (! $resp->successful()) {
            return null;
        }
        $ads = $resp->json('ads');
        if (! is_array($ads)) {
            return null;
        }

        return count($ads) > 0;
    }

    /** Replace the ghost row with the live listing's row in the same campaign. */
    private function saveSwitchedRow(object $ghost, string $newId, string $sku, float $bid, ?string $adId): void
    {
        DB::transaction(function () use ($ghost, $newId, $sku, $bid, $adId) {
            $payload = [
                'campaign_id' => (string) $ghost->campaign_id,
                'campaign_name' => $ghost->campaign_name,
                'funding_strategy' => 'COST_PER_SALE',
                'campaign_status' => $ghost->campaign_status ?: 'RUNNING',
                'promote_with_ad' => 'AD_ALREADY_CREATED',
                'updated_at' => now(),
            ];
            if ($bid > 0) {
                $payload['bid_percentage'] = $bid;
            }
            if ($adId) {
                $payload['ad_id'] = $adId;
            }
            if ($sku !== '') {
                $payload['sku'] = $sku;
            }

            $target = DB::table(self::TABLE)
                ->where('listing_id', $newId)
                ->where(function ($q) use ($ghost) {
                    $q->where('campaign_id', (string) $ghost->campaign_id)
                        ->orWhereNull('campaign_id')
                        ->orWhere('campaign_id', '');
                })
                ->orderByRaw('CASE WHEN campaign_id = ? THEN 0 ELSE 1 END', [(string) $ghost->campaign_id])
                ->first();

            if ($target) {
                DB::table(self::TABLE)->where('id', $target->id)->update($payload);
            } else {
                DB::table(self::TABLE)->insert($payload + ['listing_id' => $newId, 'created_at' => now()]);
            }

            DB::table(self::TABLE)->where('id', $ghost->id)->delete();
        });
    }
}
