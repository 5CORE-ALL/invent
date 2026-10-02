<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Support\CpMasterDil;
use App\Support\DilVsSbidRule;
use App\Support\EbayMarketingPushRetry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push Dil vs SBid for one eBay account: ES Bid or the dynamic % as the
 * promoted-listing bid. When the switch is off, nothing is pushed.
 */
class DilVsSbidApplyService
{
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
        $metrics = $metricClass::whereIn('item_id', $listingIds)->get()->keyBy(fn ($m) => (string) $m->item_id);
        $ads = DB::table($adsTable)
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy(fn ($ad) => (string) $ad->listing_id);

        $skus = [];
        foreach ($listingIds as $lid) {
            $metric = $metrics->get($lid);
            $ad = $ads->get($lid);
            $sku = (string) ($metric->sku ?? $ad->sku ?? '');
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
        $shopifyMap = $this->shopifyBySku($skus);

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
            $ad = $ads->get($lid);
            if (! $ad || ! $ad->campaign_id) {
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
            $decision = DilVsSbidRule::resolve((float) $dil, $esBid, $slabs);
            if ($decision['bid'] > 0) {
                $adjusted = DilVsSbidRule::applyCvr(
                    (float) $decision['bid'],
                    (float) ($metric?->views ?? 0),
                    (float) ($metric?->ebay_l30 ?? 0),
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

            if ($decision['bid'] <= 0) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => $decision['label'] !== '' ? $decision['label'] : 'No S Bid for this Dil'];
                $skipped++;
                continue;
            }

            $nextBid = round((float) $decision['bid'], 2);
            if ($onlyChanged && abs(round((float) ($ad->bid_percentage ?? 0), 2) - $nextBid) < 0.009) {
                $unchanged++;
                continue;
            }

            $bidsByCampaign[(string) $ad->campaign_id][] = [
                'listingId' => $lid,
                'adId' => $ad->ad_id ? (string) $ad->ad_id : null,
                'bidPercentage' => (string) $nextBid,
            ];
        }

        foreach ($bidsByCampaign as $campaignId => $requests) {
            if (! $onlyChanged) {
                $this->resumeAds($http, $adsTable, $campaignId, $requests);
            }
            foreach (array_chunk($requests, 200) as $chunk) {
                $this->pushBids($http, $adsTable, $campaignId, $chunk, $results, $success, $failed);
            }
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
     * Push RUNNING ads whose Dil vs SBid (plus CVR overlay) no longer matches
     * the live bid. Unchanged ads are left alone.
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

    private function pushBids(EbayMarketingPushRetry $http, string $adsTable, string $campaignId, array $requests, array &$results, int &$success, int &$failed): void
    {
        $payload = array_map(fn ($r) => [
            'listingId' => $r['listingId'],
            'bidPercentage' => $r['bidPercentage'],
        ], $requests);

        $out = $http->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id", [
            'requests' => $payload,
        ]);

        if ($out['ok'] && $out['response'] !== null) {
            foreach ($requests as $r) {
                DB::table($adsTable)
                    ->where('listing_id', (string) $r['listingId'])
                    ->where('campaign_id', $campaignId)
                    ->update([
                        'bid_percentage' => round((float) $r['bidPercentage'], 2),
                        'campaign_status' => 'RUNNING',
                        'updated_at' => now(),
                    ]);
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => $r['bidPercentage'].'%'];
                $success++;
            }

            return;
        }

        $reason = (string) ($out['error'] ?? 'Push failed');
        $status = $out['response'] !== null ? $out['response']->status() : 0;
        $this->noteSellerPause($adsTable, $campaignId, $status, $reason);
        foreach ($requests as $r) {
            $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => $reason];
            $failed++;
        }
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
