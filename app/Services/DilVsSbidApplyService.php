<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Support\CpMasterDil;
use App\Support\DilVsSbidRule;
use App\Support\EbayBidPercentage;
use App\Support\EbayCampaignAdLiveBid;
use App\Support\EbayMarketingPushRetry;
use App\Support\EbayStdNpftLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push Dil vs SBid for one eBay account: ES Bid or the dynamic % as the
 * promoted-listing bid. When the switch is off, nothing is pushed.
 * A mismatch pulls live C Bid, pushes S Bid, and pulls again until they
 * match or the verify rounds run out.
 */
class DilVsSbidApplyService
{
    public const VERIFY_ROUNDS = 5;

    /** @var list<int> */
    public const VERIFY_DELAYS_MS = [500, 1000, 2000, 4000, 8000];

    /** @var callable(int): void|null */
    public $sleeper = null;

    /**
     * @param  class-string  $metricClass
     * @param  class-string  $apiServiceClass
     * @param  array<int, mixed>  $listingIds
     * @return array{success:int,failed:int,skipped:int,unchanged?:int,results:array<int,array<string,mixed>>,error?:string}
     */
    public function apply(string $ruleKey, string $adsTable, string $metricClass, string $apiServiceClass, array $listingIds, bool $onlyChanged = false): array
    {
        $listingIds = array_values(array_unique(array_map('strval', $listingIds)));
        if ($listingIds === []) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'results' => [], 'error' => 'No listings to apply'];
        }

        $stored = DilVsSbidRule::load($ruleKey);
        $useDil = ! empty($stored['enabled']);
        $slabs = $stored['slabs'];
        $cvr = $stored['cvr'];
        $tables = $stored['tables'];
        $metrics = $metricClass::whereIn('item_id', $listingIds)->get()->keyBy(fn ($m) => (string) $m->item_id);
        $ads = DB::table($adsTable)
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->groupBy(fn ($ad) => (string) $ad->listing_id);

        $skus = [];
        foreach ($listingIds as $lid) {
            $metric = $metrics->get($lid);
            $ad = $ads->get($lid)?->first();
            $sku = (string) ($metric->sku ?? $ad->sku ?? '');
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
        $shopifyMap = $this->shopifyBySku($skus);
        $npftMap = ($useDil && DilVsSbidRule::usesNpft($tables))
            ? EbayStdNpftLookup::forSkus($skus)
            : [];

        $service = new $apiServiceClass();
        $http = new EbayMarketingPushRetry($service);
        try {
            $http->acquireToken();
        } catch (\Exception $e) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'results' => [], 'error' => 'Token error: '.$e->getMessage()];
        }

        $results = [];
        $success = 0;
        $failed = 0;
        $skipped = 0;
        $unchanged = 0;
        $bidsByCampaign = [];
        $offsByCampaign = [];

        foreach ($listingIds as $lid) {
            $campaignAds = $ads->get($lid);
            if ($campaignAds === null || $campaignAds->isEmpty()) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                $skipped++;
                continue;
            }

            foreach ($campaignAds as $ad) {
                if (! $ad->campaign_id) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                    $skipped++;
                    continue;
                }

                $metric = $metrics->get($lid);
                $sku = (string) ($metric?->sku ?? $ad->sku ?? '');

                if (! $useDil) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Dil vs SBid is off'];
                    $skipped++;
                    continue;
                }

                $shopify = $shopifyMap[trim($sku)] ?? null;
                $dil = CpMasterDil::slabPercent($shopify->quantity ?? null, $shopify->inv ?? null);
                if ($dil === null) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'No CP Master Dil'];
                    $skipped++;
                    continue;
                }
                $esBid = (float) ($ad->suggested_bid ?? 0);
                $views = (float) ($metric?->views ?? 0);
                $sold = (float) ($metric?->ebay_l30 ?? 0);
                $decision = DilVsSbidRule::resolveTotal((float) $dil, $esBid, $slabs, $tables, [
                    'views' => $views,
                    'cvr' => $views > 0 ? ($sold / $views) * 100 : null,
                    'sold' => $sold,
                    'npft' => $npftMap[EbayStdNpftLookup::key($sku)] ?? null,
                ]);
                if ($decision['bid'] > 0) {
                    $adjusted = DilVsSbidRule::applyCvr(
                        (float) $decision['bid'],
                        $views,
                        $sold,
                        (float) ($metric?->ebay_l60 ?? 0),
                        $cvr
                    );
                    $decision['bid'] = $adjusted['bid'];
                    if ($adjusted['why'] !== '') {
                        $decision['label'] = trim($decision['label'].' '.$adjusted['why']);
                    }
                }

                if ($decision['off']) {
                    if (empty($ad->ad_id)) {
                        $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Pause but no ad id'];
                        $skipped++;
                        continue;
                    }
                    $offsByCampaign[(string) $ad->campaign_id][] = [
                        'listingId' => $lid,
                        'adId' => (string) $ad->ad_id,
                    ];
                    continue;
                }

                $formatted = EbayBidPercentage::forPush((float) $decision['bid']);
                if ($formatted === null) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => $decision['label'] !== '' ? $decision['label'] : 'No S Bid for this Dil'];
                    $skipped++;
                    continue;
                }

                $bidsByCampaign[(string) $ad->campaign_id][] = [
                    'listingId' => $lid,
                    'adId' => $ad->ad_id ? (string) $ad->ad_id : null,
                    'bidPercentage' => $formatted,
                ];
            }
        }

        foreach ($bidsByCampaign as $campaignId => $requests) {
            $this->pushUntilMatched(
                $http,
                $adsTable,
                (string) $campaignId,
                $requests,
                $results,
                $success,
                $failed,
                $unchanged,
                ! $onlyChanged
            );
        }

        foreach ($offsByCampaign as $campaignId => $requests) {
            foreach (array_chunk($requests, 200) as $chunk) {
                $this->pauseAds($http, $adsTable, $campaignId, $chunk, $results, $success, $failed);
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
            'unchanged' => $unchanged,
            'results' => $results,
        ];
    }

    /**
     * Pull live C Bid for RUNNING ads, then push Dil vs SBid until live
     * matches. Ads that already match are left alone.
     *
     * @param  class-string  $metricClass
     * @param  class-string  $apiServiceClass
     * @return array{success:int,failed:int,skipped:int,unchanged?:int,results:array<int,array<string,mixed>>,error?:string}
     */
    public function applyChanged(string $ruleKey, string $adsTable, string $metricClass, string $apiServiceClass): array
    {
        $stored = DilVsSbidRule::load($ruleKey);
        if (empty($stored['enabled'])) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'unchanged' => 0, 'results' => [], 'error' => 'Dil vs SBid is off'];
        }

        $listingIds = DB::table($adsTable)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->whereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) = 'RUNNING'")
            ->pluck('listing_id')
            ->all();

        return $this->apply($ruleKey, $adsTable, $metricClass, $apiServiceClass, $listingIds, true);
    }

    /**
     * Pull live C Bid, push S Bid when they differ, pull again. Repeat until
     * they match or the verify rounds run out. Local C Bid is written only
     * from a listing-level live pull.
     *
     * @param  list<array{listingId:string,adId:?string,bidPercentage:string}>  $requests
     */
    private function pushUntilMatched(
        EbayMarketingPushRetry $http,
        string $adsTable,
        string $campaignId,
        array $requests,
        array &$results,
        int &$success,
        int &$failed,
        int &$unchanged,
        bool $resumeFirst
    ): void {
        $pending = $requests;
        $first = true;
        for ($round = 1; $round <= self::VERIFY_ROUNDS && $pending !== []; $round++) {
            if (! $first) {
                $this->pauseVerify($round - 1);
            }
            $live = $this->pullListingBids($http, $campaignId, $pending);
            $needPush = [];
            foreach ($pending as $r) {
                $want = (float) $r['bidPercentage'];
                $got = $live[(string) $r['listingId']] ?? null;
                if (EbayCampaignAdLiveBid::matches($got, $want)) {
                    $this->storeLiveBid($adsTable, $campaignId, $r, (float) $got);
                    if ($first) {
                        $unchanged++;
                    } else {
                        $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => number_format($want, 1, '.', '').'%'];
                        $success++;
                    }
                    continue;
                }
                $needPush[] = $r;
            }
            if ($needPush === []) {
                return;
            }
            if ($first && $resumeFirst) {
                $this->resumeAds($http, $adsTable, $campaignId, $needPush);
            }
            $first = false;
            $sent = $this->sendBids($http, $adsTable, $campaignId, $needPush);
            foreach ($sent['failed'] as $fail) {
                $results[] = ['listing_id' => $fail['listingId'], 'status' => 'failed', 'reason' => $fail['reason']];
                $failed++;
            }
            $pending = $sent['accepted'];
        }

        if ($pending === []) {
            return;
        }

        $this->pauseVerify(self::VERIFY_ROUNDS);
        $live = $this->pullListingBids($http, $campaignId, $pending);
        foreach ($pending as $r) {
            $want = (float) $r['bidPercentage'];
            $got = $live[(string) $r['listingId']] ?? null;
            if (EbayCampaignAdLiveBid::matches($got, $want)) {
                $this->storeLiveBid($adsTable, $campaignId, $r, (float) $got);
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => number_format($want, 1, '.', '').'%'];
                $success++;
                continue;
            }
            if ($got !== null) {
                $this->storeLiveBid($adsTable, $campaignId, $r, (float) $got);
            }
            $liveText = $got !== null ? number_format((float) $got, 1, '.', '').'%' : 'empty';
            $results[] = [
                'listing_id' => $r['listingId'],
                'status' => 'failed',
                'reason' => 'Live C Bid '.$liveText.' still does not match S Bid '.number_format($want, 1, '.', '').'%',
            ];
            $failed++;
        }
    }

    /**
     * @param  list<array{listingId:string,adId:?string,bidPercentage:string}>  $requests
     * @return array<string, float>
     */
    private function pullListingBids(EbayMarketingPushRetry $http, string $campaignId, array $requests): array
    {
        $wanted = [];
        foreach ($requests as $r) {
            $wanted[(string) $r['listingId']] = $r;
        }
        $byListing = [];
        foreach ($this->fetchCampaignAds($http, $campaignId) as $ad) {
            if (! is_array($ad)) {
                continue;
            }
            $lid = (string) ($ad['listingId'] ?? '');
            if ($lid === '' || ! isset($wanted[$lid])) {
                continue;
            }
            $bid = EbayCampaignAdLiveBid::fromPayload($ad);
            if ($bid !== null) {
                $byListing[$lid] = $bid;
            }
        }
        foreach ($wanted as $lid => $r) {
            if (isset($byListing[$lid]) || empty($r['adId'])) {
                continue;
            }
            $out = $http->get('https://api.ebay.com/sell/marketing/v1/ad_campaign/'.$campaignId.'/ad/'.$r['adId']);
            $body = ($out['ok'] && $out['response'] !== null) ? $out['response']->json() : null;
            if (! is_array($body)) {
                continue;
            }
            $bid = EbayCampaignAdLiveBid::fromPayload($body);
            if ($bid !== null) {
                $byListing[$lid] = $bid;
            }
        }

        return $byListing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCampaignAds(EbayMarketingPushRetry $http, string $campaignId): array
    {
        $all = [];
        $offset = 0;
        $limit = 200;
        do {
            $out = $http->get('https://api.ebay.com/sell/marketing/v1/ad_campaign/'.$campaignId.'/ad', [
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (! $out['ok'] || $out['response'] === null) {
                break;
            }
            $data = $out['response']->json();
            $batch = is_array($data) ? ($data['ads'] ?? []) : [];
            if (! is_array($batch) || $batch === []) {
                break;
            }
            $all = array_merge($all, $batch);
            $total = (int) ($data['total'] ?? 0);
            $offset += $limit;
            if ($total > 0 && count($all) >= $total) {
                break;
            }
        } while (count($batch) >= $limit);

        return $all;
    }

    /**
     * @param  list<array{listingId:string,adId:?string,bidPercentage:string}>  $requests
     * @return array{accepted: list<array{listingId:string,adId:?string,bidPercentage:string}>, failed: list<array{listingId:string,reason:string}>}
     */
    private function sendBids(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests, int $depth = 0): array
    {
        $accepted = [];
        $failed = [];
        $payload = array_map(fn ($r) => [
            'listingId' => $r['listingId'],
            'bidPercentage' => $r['bidPercentage'],
        ], $requests);

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id", [
            'requests' => $payload,
        ]);
        $response = $out['response'];
        $body = $response !== null ? $response->json() : null;

        if ($out['ok'] && is_array($body) && isset($body['responses']) && is_array($body['responses'])) {
            $byListing = [];
            foreach ($body['responses'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['listingId'] ?? '');
                if ($id !== '') {
                    $byListing[$id] = $row;
                }
            }
            $retryAtMax = [];
            foreach ($requests as $r) {
                $row = $byListing[(string) $r['listingId']] ?? null;
                $code = is_array($row) ? (int) ($row['statusCode'] ?? 200) : 0;
                $ok = is_array($row) && $code >= 200 && $code < 300 && empty($row['errors']);
                if ($ok) {
                    $accepted[] = $r;
                    continue;
                }
                $max = EbayBidPercentage::maxFromError($row);
                if ($max !== null && (float) $r['bidPercentage'] > $max && $depth < 2) {
                    $r['bidPercentage'] = number_format($max, 1, '.', '');
                    $retryAtMax[] = $r;
                    continue;
                }
                $reason = is_array($row) ? ($row['errors'][0]['message'] ?? 'Push failed') : 'Push failed';
                $failed[] = ['listingId' => (string) $r['listingId'], 'reason' => $reason];
            }
            if ($retryAtMax !== []) {
                $again = $this->sendBids($http, $adsTable, $campaignId, $retryAtMax, $depth + 1);
                $accepted = array_merge($accepted, $again['accepted']);
                $failed = array_merge($failed, $again['failed']);
            }

            return ['accepted' => $accepted, 'failed' => $failed];
        }

        if ($out['ok'] && $response !== null) {
            if (count($requests) === 1) {
                return ['accepted' => $requests, 'failed' => []];
            }
            if ($depth < 6) {
                $mid = intdiv(count($requests), 2);
                $left = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, 0, $mid), $depth + 1);
                $right = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, $mid), $depth + 1);

                return [
                    'accepted' => array_merge($left['accepted'], $right['accepted']),
                    'failed' => array_merge($left['failed'], $right['failed']),
                ];
            }
            foreach ($requests as $r) {
                $failed[] = ['listingId' => (string) $r['listingId'], 'reason' => 'No per-listing result'];
            }

            return ['accepted' => [], 'failed' => $failed];
        }

        $reason = (string) ($out['error'] ?? 'Push failed');
        $status = $response !== null ? $response->status() : 0;
        $max = EbayBidPercentage::maxFromError($body);
        if ($max !== null && $depth < 2) {
            $clamped = [];
            $changed = false;
            foreach ($requests as $r) {
                if ((float) $r['bidPercentage'] > $max) {
                    $r['bidPercentage'] = number_format($max, 1, '.', '');
                    $changed = true;
                }
                $clamped[] = $r;
            }
            if ($changed) {
                return $this->sendBids($http, $adsTable, $campaignId, $clamped, $depth + 1);
            }
        }

        $bidRejected = $max !== null
            || str_contains(strtolower($reason), 'bidpercentage')
            || str_contains(strtolower($reason), 'bid percentage');
        if ($bidRejected && count($requests) > 1 && $depth < 6) {
            $mid = intdiv(count($requests), 2);
            $left = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, 0, $mid), $depth + 1);
            $right = $this->sendBids($http, $adsTable, $campaignId, array_slice($requests, $mid), $depth + 1);

            return [
                'accepted' => array_merge($left['accepted'], $right['accepted']),
                'failed' => array_merge($left['failed'], $right['failed']),
            ];
        }

        $this->noteSellerPause($adsTable, $campaignId, $status, $reason);
        foreach ($requests as $r) {
            $failed[] = ['listingId' => (string) $r['listingId'], 'reason' => $reason];
        }

        return ['accepted' => [], 'failed' => $failed];
    }

    private function storeLiveBid(string $adsTable, string $campaignId, array $r, float $live): void
    {
        DB::table($adsTable)
            ->where('listing_id', (string) $r['listingId'])
            ->where('campaign_id', $campaignId)
            ->update([
                'bid_percentage' => round($live, 1),
                'campaign_status' => 'RUNNING',
                'updated_at' => now(),
            ]);
    }

    private function pauseVerify(int $round): void
    {
        $ms = self::VERIFY_DELAYS_MS[min(max($round - 1, 0), count(self::VERIFY_DELAYS_MS) - 1)];
        if ($this->sleeper !== null) {
            ($this->sleeper)($ms);

            return;
        }
        usleep($ms * 1000);
    }

    private function pauseAds(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests, array &$results, int &$success, int &$failed): void
    {
        $payload = array_map(fn ($r) => [
            'adId' => $r['adId'],
            'adStatus' => 'PAUSED',
        ], $requests);

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_status", [
            'requests' => $payload,
        ]);
        $response = $out['response'];

        if ($out['ok'] && $response !== null && $this->pauseChunkOk($response->json())) {
            foreach ($requests as $r) {
                DB::table($adsTable)
                    ->where('listing_id', (string) $r['listingId'])
                    ->where('campaign_id', $campaignId)
                    ->update([
                        'campaign_status' => 'PAUSED',
                        'updated_at' => now(),
                    ]);
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => 'OFF'];
                $success++;
            }

            return;
        }

        $reason = $out['ok'] && $response !== null
            ? $this->ebayReason($response->status(), $response->json())
            : (string) ($out['error'] ?? 'Pause failed');
        Log::warning('Dil vs SBid pause failed', [
            'campaign_id' => $campaignId,
            'http' => $response !== null ? $response->status() : 0,
            'reason' => $reason,
        ]);
        foreach ($requests as $r) {
            $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => 'Pause: '.$reason];
            $failed++;
        }
    }

    /** Turn a previously paused ad back on before writing the new bid. Failures are logged; the bid push still runs. */
    private function resumeAds(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests): void
    {
        $payload = [];
        foreach ($requests as $r) {
            if (empty($r['adId'])) {
                continue;
            }
            $payload[] = [
                'adId' => $r['adId'],
                'adStatus' => 'ACTIVE',
            ];
        }
        if ($payload === []) {
            return;
        }

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_status", [
            'requests' => $payload,
        ]);
        if (! $out['ok']) {
            $status = $out['response'] !== null ? $out['response']->status() : 0;
            $this->noteSellerPause($adsTable, $campaignId, $status, (string) ($out['error'] ?? 'Resume failed'));
            Log::warning('Dil vs SBid resume failed', [
                'campaign_id' => $campaignId,
                'error' => $out['error'] ?? 'Resume failed',
            ]);
        }
    }

    /** eBay can return HTTP 200 while a listing in the chunk failed. */
    private function pauseChunkOk($body): bool
    {
        if (! is_array($body)) {
            return true;
        }
        $responses = $body['responses'] ?? null;
        if (! is_array($responses) || $responses === []) {
            return empty($body['errors']);
        }
        foreach ($responses as $row) {
            $code = (int) ($row['statusCode'] ?? 200);
            if ($code >= 400) {
                return false;
            }
        }

        return true;
    }

    private function ebayReason(int $status, $body): string
    {
        if (! is_array($body)) {
            return (string) $status;
        }
        $msg = $body['errors'][0]['message']
            ?? $body['errors'][0]['longMessage']
            ?? $body['message']
            ?? null;

        return $msg ? ($status.': '.$msg) : (string) $status;
    }

    private function noteSellerPause(string $adsTable, string $campaignId, int $status, string $reason): void
    {
        if ($status === 409 && (str_contains(strtolower($reason), 'seller level') || str_contains(strtolower($reason), 'promoted listings'))) {
            DB::table($adsTable)
                ->where('campaign_id', $campaignId)
                ->update(['campaign_status' => 'SYSTEM_PAUSED', 'updated_at' => now()]);
        }
    }

    private function shopifyBySku(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $skus
        ), fn ($s) => $s !== '' && ! str_starts_with(strtoupper($s), 'PARENT'))));
        if ($skus === []) {
            return [];
        }

        $mapped = ShopifySku::mapByProductSkus($skus);
        $out = [];
        foreach ($skus as $sku) {
            if (isset($mapped[$sku])) {
                $out[$sku] = $mapped[$sku];
            }
        }

        return $out;
    }
}
