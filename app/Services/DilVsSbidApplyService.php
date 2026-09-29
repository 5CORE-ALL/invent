<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Support\DilVsSbidRule;
use App\Support\SbidSlabRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push Dil vs SBid for one eBay account: ES Bid or the dynamic % as the
 * promoted-listing bid, and pause listings that land on an Auto Off slab.
 */
class DilVsSbidApplyService
{
    /**
     * @param  class-string  $metricClass
     * @param  class-string  $apiServiceClass
     * @param  array<int, mixed>  $listingIds
     * @return array{success:int,failed:int,skipped:int,results:array<int,array<string,mixed>>}
     */
    public function apply(string $ruleKey, string $adsTable, string $metricClass, string $apiServiceClass, array $listingIds): array
    {
        $listingIds = array_values(array_unique(array_map('strval', $listingIds)));
        if ($listingIds === []) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'results' => [], 'error' => 'No listings to apply'];
        }

        $stored = DilVsSbidRule::load($ruleKey);
        $useDil = ! empty($stored['enabled']);
        $slabs = $stored['slabs'];
        $viewSlabs = $useDil ? [] : $this->viewVsSbidSlabs();
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
        $shopifyMap = $this->shopifyByNormSku($skus);

        try {
            $service = new $apiServiceClass();
            $token = $service->generateBearerToken();
        } catch (\Exception $e) {
            return ['success' => 0, 'failed' => 0, 'skipped' => 0, 'results' => [], 'error' => 'Token error: '.$e->getMessage()];
        }

        $results = [];
        $success = 0;
        $failed = 0;
        $skipped = 0;
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
                $esold = (float) ($metric?->ebay_l30 ?? 0);
                $l7Views = (float) ($metric?->l7_views ?? 0);
                $decision = SbidSlabRule::match($esold, $l7Views, $viewSlabs);
                if ($decision['pause']) {
                    if (empty($ad->ad_id)) {
                        $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Paused slab but no ad id'];
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
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Dil vs SBid is off and no View VS SBID slab matched'];
                    $skipped++;
                    continue;
                }
                $bidsByCampaign[(string) $ad->campaign_id][] = [
                    'listingId' => $lid,
                    'adId' => $ad->ad_id ? (string) $ad->ad_id : null,
                    'bidPercentage' => (string) round($decision['bid'], 2),
                ];
                continue;
            }

            $shopify = $shopifyMap[$this->normSku($sku)] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $qty = (float) ($shopify->quantity ?? 0);
            $dil = $inv > 0 ? ($qty / $inv) * 100 : 0;
            $esBid = (float) ($ad->suggested_bid ?? 0);
            $decision = DilVsSbidRule::resolve($dil, $esBid, $slabs);

            if ($decision['off']) {
                if (empty($ad->ad_id)) {
                    $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Auto Off but no ad id'];
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

            $bidsByCampaign[(string) $ad->campaign_id][] = [
                'listingId' => $lid,
                'adId' => $ad->ad_id ? (string) $ad->ad_id : null,
                'bidPercentage' => (string) round($decision['bid'], 2),
            ];
        }

        foreach ($bidsByCampaign as $campaignId => $requests) {
            $this->resumeAds($token, $adsTable, $campaignId, $requests);
            foreach (array_chunk($requests, 200) as $chunk) {
                $this->pushBids($token, $adsTable, $campaignId, $chunk, $results, $success, $failed);
            }
        }

        foreach ($offsByCampaign as $campaignId => $requests) {
            foreach (array_chunk($requests, 200) as $chunk) {
                $this->pauseAds($token, $campaignId, $chunk, $results, $success, $failed);
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ];
    }

    private function pushBids(string $token, string $adsTable, string $campaignId, array $requests, array &$results, int &$success, int &$failed): void
    {
        $payload = array_map(fn ($r) => [
            'listingId' => $r['listingId'],
            'bidPercentage' => $r['bidPercentage'],
        ], $requests);

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id", [
                    'requests' => $payload,
                ]);

            if ($response->successful()) {
                foreach ($requests as $r) {
                    DB::table($adsTable)
                        ->where('listing_id', (string) $r['listingId'])
                        ->where('campaign_id', $campaignId)
                        ->update([
                            'bid_percentage' => round((float) $r['bidPercentage'], 2),
                            'updated_at' => now(),
                        ]);
                    $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => $r['bidPercentage'].'%'];
                    $success++;
                }

                return;
            }

            $reason = $this->ebayReason($response->status(), $response->json());
            $this->noteSellerPause($adsTable, $campaignId, $response->status(), $reason);
            foreach ($requests as $r) {
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => $reason];
                $failed++;
            }
        } catch (\Exception $e) {
            foreach ($requests as $r) {
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => $e->getMessage()];
                $failed++;
            }
        }
    }

    private function pauseAds(string $token, string $campaignId, array $requests, array &$results, int &$success, int &$failed): void
    {
        $payload = array_map(fn ($r) => [
            'adId' => $r['adId'],
            'listingId' => $r['listingId'],
            'status' => 'PAUSED',
        ], $requests);

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_status", [
                    'requests' => $payload,
                ]);

            if ($response->successful()) {
                foreach ($requests as $r) {
                    $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => 'OFF'];
                    $success++;
                }

                return;
            }

            $reason = $this->ebayReason($response->status(), $response->json());
            Log::warning('Dil vs SBid auto-off pause failed', [
                'campaign_id' => $campaignId,
                'http' => $response->status(),
                'reason' => $reason,
            ]);
            foreach ($requests as $r) {
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => 'Auto Off: '.$reason];
                $failed++;
            }
        } catch (\Exception $e) {
            foreach ($requests as $r) {
                $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => $e->getMessage()];
                $failed++;
            }
        }
    }

    /** Turn a previously paused ad back on before writing the new bid. Failures are logged; the bid push still runs. */
    private function resumeAds(string $token, string $adsTable, string $campaignId, array $requests): void
    {
        $payload = [];
        foreach ($requests as $r) {
            if (empty($r['adId'])) {
                continue;
            }
            $payload[] = [
                'adId' => $r['adId'],
                'listingId' => $r['listingId'],
                'status' => 'ACTIVE',
            ];
        }
        if ($payload === []) {
            return;
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_status", [
                    'requests' => $payload,
                ]);
            if (! $response->successful()) {
                $this->noteSellerPause($adsTable, $campaignId, $response->status(), $this->ebayReason($response->status(), $response->json()));
            }
        } catch (\Exception $e) {
            Log::warning('Dil vs SBid resume failed', ['campaign_id' => $campaignId, 'error' => $e->getMessage()]);
        }
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

    private function viewVsSbidSlabs(): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', 'ebay1_sbid_slabs')->first();
        $decoded = $row ? json_decode((string) $row->rule, true) : null;
        $rules = is_array($decoded['rules'] ?? null) ? $decoded['rules'] : [];

        return $rules;
    }

    private function shopifyByNormSku(array $skus): array
    {
        $map = [];
        $skus = array_values(array_unique(array_filter($skus, fn ($s) => $s !== '')));
        if ($skus === []) {
            return $map;
        }
        foreach (ShopifySku::whereIn('sku', $skus)->get(['sku', 'inv', 'quantity']) as $row) {
            $key = $this->normSku($row->sku);
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    private function normSku(?string $s): string
    {
        $s = (string) $s;
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x87", "\xE2\x80\x8B"], ' ', $s);

        return strtoupper(preg_replace('/\s+/u', ' ', trim($s)));
    }
}
