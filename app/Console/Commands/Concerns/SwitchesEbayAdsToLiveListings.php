<?php

namespace App\Console\Commands\Concerns;

use App\Support\Marketplace\EbayListingEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Shared logic for `ebay:switch-ads-to-live-listings` (eBay 1) and `ebay2:switch-ads-to-live-listings` (eBay 2).
 *
 * When a SKU is relisted under a new item id, the Promoted Listings (PMT / COST_PER_SALE) ad on the old item id
 * disappears on eBay but the campaign stays RUNNING. The campaign-ads table keeps a "ghost" row for the old
 * item id, so the new live listing never gets an ad.
 *
 * For every ghost row this:
 *   1. finds the SKU of the old listing (ad row sku, PARENT campaign name, else order history),
 *   2. finds the live listing for the SAME SKU (exact SKU only; OPEN BOX / pack variants are other products),
 *   3. confirms with eBay that the ad on the old listing is gone,
 *   4. creates the ad on the live listing in the SAME campaign with the same bid %,
 *   5. replaces the ghost row with the new ad row.
 *
 * It also stamps the SKU on live ad rows that have none, so the next relist of that listing can be mapped.
 * COST_PER_CLICK (KW) campaigns are not handled (they need keywords / ad groups).
 */
trait SwitchesEbayAdsToLiveListings
{
    /** e.g. ebay_campaign_ads */
    abstract protected function adsTable(): string;

    /** @return class-string metrics model (EbayMetric / Ebay2Metric) */
    abstract protected function metricClass(): string;

    /** Bearer token for this eBay account. */
    abstract protected function accessToken(): string;

    /**
     * Tables that remember which SKU an old item id belonged to: [table, item_id column, sku column].
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    abstract protected function historySources(): array;

    abstract protected function cachePrefix(): string;

    protected function maxEbayChecks(): int
    {
        return 450;
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $prune = (bool) $this->option('prune');

        $metrics = $this->loadMetrics();
        $liveByItem = $metrics['live'];

        if (! $dryRun) {
            $stamped = $this->stampSkuOnLiveRows($metrics['skuByItem'], $liveByItem);
            if ($stamped > 0) {
                $this->line("SKU remembered on {$stamped} live ad row(s).");
            }
        }

        $ghosts = $this->ghostRows($liveByItem);
        $this->info(($dryRun ? '[DRY RUN] ' : '').$this->adsTable().' ad switch: '.count($ghosts).' ad row(s) point at a listing that is not live.');
        if ($ghosts === []) {
            return self::SUCCESS;
        }

        $history = $this->skuHistory(array_map(fn ($r) => trim((string) $r->listing_id), $ghosts));

        try {
            $token = $this->accessToken();
        } catch (\Throwable $e) {
            $this->error('Token error: '.$e->getMessage());

            return self::FAILURE;
        }

        $stats = ['switched' => 0, 'already_ok' => 0, 'pruned' => 0, 'no_live_listing' => 0, 'no_bid' => 0, 'ebay_still_has_ad' => 0, 'already_elsewhere' => 0, 'failed' => 0];
        $done = 0;
        $checks = 0;

        foreach ($ghosts as $row) {
            if ($done >= $limit) {
                $this->line('Limit reached ('.$limit.'); the rest runs on the next schedule.');
                break;
            }

            $campaignId = (string) $row->campaign_id;
            $oldId = trim((string) $row->listing_id);
            $sku = $this->resolveSku($row, $history[$oldId] ?? '');
            $tag = "{$row->campaign_name} | old={$oldId} | sku=".($sku !== '' ? $sku : '-');

            // Local lookup first: a SKU with no live listing needs no eBay call at all (unless --prune).
            $newId = $sku !== '' ? $this->liveItemIdForSku($sku, $oldId, $liveByItem) : '';
            if ($newId === '' && ! $prune) {
                $stats['no_live_listing']++;
                continue;
            }

            // Ads that really still exist on eBay (e.g. a temporarily inactive listing) are re-checked only every few hours.
            $hasAdKey = $this->cachePrefix().":has_ad:{$campaignId}:{$oldId}";
            if (Cache::has($hasAdKey)) {
                $stats['ebay_still_has_ad']++;
                continue;
            }
            if ($checks >= $this->maxEbayChecks()) {
                $this->line('eBay check cap reached ('.$this->maxEbayChecks().'); the rest runs on the next schedule.');
                break;
            }
            $checks++;

            // eBay is the source of truth: only a campaign that really has no ad on the old listing is a ghost.
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

            // No live listing for this SKU: only --prune removes the dead row.
            if ($newId === '') {
                $this->line("  ✂ {$tag} → no live listing, ghost row removed");
                if (! $dryRun) {
                    DB::table($this->adsTable())->where('id', $row->id)->delete();
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
                    // eBay allows one PMT ad per listing. The live listing already has its ad in ANOTHER campaign,
                    // so nothing to create; the old row points at a dead listing and only misleads, drop it.
                    // (sync-campaign-listings mirrors the live ad's real campaign on its next run.)
                    if (stripos($msg, 'already exists') !== false) {
                        DB::table($this->adsTable())->where('id', $row->id)->delete();
                        $this->line("  = {$tag} → new={$newId} already has an ad in another campaign, dead row removed");
                        $stats['already_elsewhere']++;
                        continue;
                    }
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
            .' | Already in another campaign: '.$stats['already_elsewhere']
            .' | Pruned: '.$stats['pruned']
            .' | No bid: '.$stats['no_bid']
            .' | eBay still has ad: '.$stats['ebay_still_has_ad']
            .' | Failed: '.$stats['failed']);

        return self::SUCCESS;
    }

    /**
     * One pass over the metrics table.
     *  live      item_id => true for every listing that is not ended / not listed
     *  skuByItem item_id => SKU of a live listing's preferred row (PARENT row first, else lowest id)
     *
     * @return array{live: array<string, true>, skuByItem: array<string, string>}
     */
    protected function loadMetrics(): array
    {
        $class = $this->metricClass();
        $live = [];
        $pick = []; // item_id => [score, id, sku]
        foreach ($class::query()->whereNotNull('item_id')->orderBy('id')->get(['id', 'item_id', 'sku', 'listing_status']) as $m) {
            $item = trim((string) $m->item_id);
            if ($item === '') {
                continue;
            }
            $status = $m->listing_status;
            if (EbayListingEnded::isEnded($status) || EbayListingEnded::isNotListed($status)) {
                continue;
            }
            $live[$item] = true;
            $sku = trim((string) $m->sku);
            if ($sku === '') {
                continue;
            }
            $score = stripos($sku, 'PARENT') === 0 ? 0 : 1;
            if (! isset($pick[$item]) || $score < $pick[$item][0]) {
                $pick[$item] = [$score, (int) $m->id, $sku];
            }
        }

        return ['live' => $live, 'skuByItem' => array_map(fn ($p) => $p[2], $pick)];
    }

    /**
     * Remember the SKU on live ad rows that have none. When that listing is relisted later, the old item id
     * can then be mapped to its SKU (the metrics row has already moved on to the new item id by then).
     *
     * @param  array<string, string>  $skuByItem
     * @param  array<string, true>  $live
     */
    protected function stampSkuOnLiveRows(array $skuByItem, array $live): int
    {
        $rows = DB::table($this->adsTable())
            ->whereNotNull('listing_id')
            ->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', '');
            })
            ->get(['id', 'listing_id']);

        $n = 0;
        foreach ($rows as $row) {
            $item = trim((string) $row->listing_id);
            if (! isset($live[$item]) || ! isset($skuByItem[$item])) {
                continue;
            }
            // Query builder on purpose: no updated_at bump, so the "latest sync" bookkeeping stays untouched.
            DB::table($this->adsTable())->where('id', $row->id)->update(['sku' => $skuByItem[$item]]);
            $n++;
        }

        return $n;
    }

    /**
     * PMT (COST_PER_SALE) rows that sit in a campaign but whose listing is not a live listing.
     *
     * @param  array<string, true>  $live
     * @return list<object>
     */
    protected function ghostRows(array $live): array
    {
        $rows = DB::table($this->adsTable())
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

    /**
     * old item id => the one SKU it belonged to, from order history. Ids with several SKUs are left out
     * (a guess could move the ad to the wrong product).
     *
     * @param  list<string>  $listingIds
     * @return array<string, string>
     */
    protected function skuHistory(array $listingIds): array
    {
        $listingIds = array_values(array_unique(array_filter($listingIds)));
        $seen = []; // item => [normalized sku => sku]
        foreach ($this->historySources() as [$table, $itemCol, $skuCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $itemCol) || ! Schema::hasColumn($table, $skuCol)) {
                continue;
            }
            foreach (array_chunk($listingIds, 500) as $chunk) {
                $rows = DB::table($table)
                    ->whereIn($itemCol, $chunk)
                    ->whereNotNull($skuCol)
                    ->where($skuCol, '!=', '')
                    ->distinct()
                    ->get([$itemCol, $skuCol]);
                foreach ($rows as $r) {
                    $sku = trim((string) $r->$skuCol);
                    $seen[trim((string) $r->$itemCol)][strtoupper($sku)] = $sku;
                }
            }
        }

        $out = [];
        foreach ($seen as $item => $skus) {
            if (count($skus) === 1) {
                $out[$item] = reset($skus);
            }
        }

        return $out;
    }

    /** SKU of the ad: its own sku, else the PARENT campaign name ("PARENT X" / "PARENT X KW"), else order history. */
    protected function resolveSku(object $row, string $fromHistory = ''): string
    {
        $sku = trim((string) ($row->sku ?? ''));
        if ($sku !== '') {
            return $sku;
        }
        $name = trim((string) preg_replace('/\s+KW$/i', '', trim((string) ($row->campaign_name ?? ''))));
        if (stripos($name, 'PARENT ') === 0) {
            return $name;
        }

        return trim($fromHistory);
    }

    /**
     * Live item id for this exact SKU (OPEN BOX / pack variants are different products, so no fuzzy match).
     *
     * @param  array<string, true>  $live
     */
    protected function liveItemIdForSku(string $sku, string $oldId, array $live): string
    {
        $metric = EbayListingEnded::preferredRow($this->metricClass(), $sku);
        $id = trim((string) ($metric?->item_id ?? ''));

        return ($id !== '' && $id !== $oldId && isset($live[$id])) ? $id : '';
    }

    /** Old ad's bid %, else the suggested bid already stored for the new listing. */
    protected function bidFor(object $row, string $newId): float
    {
        $bid = (float) ($row->bid_percentage ?? 0);
        if ($bid <= 0) {
            $bid = (float) ($row->suggested_bid ?? 0);
        }
        if ($bid <= 0) {
            $bid = (float) (DB::table($this->adsTable())->where('listing_id', $newId)->max('suggested_bid') ?? 0);
        }

        return $bid >= 2.0 && $bid <= 100.0 ? round($bid, 1) : 0.0;
    }

    /** true / false, or null when eBay could not be asked (never delete or create on null). */
    protected function adExistsOnEbay(string $token, string $campaignId, string $listingId): ?bool
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
    protected function saveSwitchedRow(object $ghost, string $newId, string $sku, float $bid, ?string $adId): void
    {
        $table = $this->adsTable();
        DB::transaction(function () use ($table, $ghost, $newId, $sku, $bid, $adId) {
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

            $target = DB::table($table)
                ->where('listing_id', $newId)
                ->where(function ($q) use ($ghost) {
                    $q->where('campaign_id', (string) $ghost->campaign_id)
                        ->orWhereNull('campaign_id')
                        ->orWhere('campaign_id', '');
                })
                ->orderByRaw('CASE WHEN campaign_id = ? THEN 0 ELSE 1 END', [(string) $ghost->campaign_id])
                ->first();

            if ($target) {
                DB::table($table)->where('id', $target->id)->update($payload);
            } else {
                DB::table($table)->insert($payload + ['listing_id' => $newId, 'created_at' => now()]);
            }

            DB::table($table)->where('id', $ghost->id)->delete();
        });
    }
}
