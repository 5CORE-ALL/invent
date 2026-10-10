<?php

namespace App\Support;

use App\Models\AmazonAdsCampaignSku;
use App\Services\AmazonAdsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add or remove products on an existing Sponsored Brands creative.
 * Does not create brand assets. Does not change SP product ads.
 */
final class AmazonAdsSbAdEditor
{
    public function __construct(private AmazonAdsService $ads) {}

    public static function campaignIsSb(string $campaignId): bool
    {
        $cid = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        if ($cid === '' || ! Schema::hasTable('amazon_sb_campaign_reports')) {
            return false;
        }

        return DB::table('amazon_sb_campaign_reports')->where('campaign_id', $cid)->exists();
    }

    /**
     * @param  list<string>  $skus
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    public function add(string $campaignId, array $skus = [], array $asins = []): array
    {
        return $this->change($campaignId, $skus, $asins, true);
    }

    /**
     * @param  list<string>  $skus
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    public function remove(string $campaignId, array $skus = [], array $asins = []): array
    {
        return $this->change($campaignId, $skus, $asins, false);
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $add
     * @return list<string>
     */
    public static function mergeAsins(array $current, array $add): array
    {
        $out = [];
        foreach (array_merge($current, $add) as $asin) {
            $a = self::normalizeAsin($asin);
            if ($a !== '') {
                $out[$a] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $remove
     * @return list<string>
     */
    public static function withoutAsins(array $current, array $remove): array
    {
        $drop = [];
        foreach ($remove as $asin) {
            $a = self::normalizeAsin($asin);
            if ($a !== '') {
                $drop[$a] = true;
            }
        }
        $out = [];
        foreach ($current as $asin) {
            $a = self::normalizeAsin($asin);
            if ($a !== '' && ! isset($drop[$a])) {
                $out[$a] = true;
            }
        }

        return array_keys($out);
    }

    public static function normalizeAsin(mixed $value): string
    {
        $a = strtoupper(trim((string) $value));

        return preg_match('/^B0[A-Z0-9]{8}$/', $a) === 1 ? $a : '';
    }

    /**
     * @param  list<string>  $skus
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    private function change(string $campaignId, array $skus, array $asins, bool $adding): array
    {
        $cid = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        $empty = [
            'success' => false,
            'message' => '',
            'campaign_id' => $cid,
            'added' => [],
            'removed' => [],
            'failed' => [],
            'deleted_ads' => [],
        ];
        if ($cid === '') {
            $empty['message'] = 'Campaign ID is required.';

            return $empty;
        }
        if (! self::campaignIsSb($cid)) {
            $empty['message'] = 'This is not an SB campaign. Add and remove products here is SB only.';

            return $empty;
        }

        $wanted = $this->resolveAsins($skus, $asins);
        if ($wanted['asins'] === []) {
            $empty['message'] = 'Provide at least one SB SKU or ASIN.';
            $empty['failed'] = $wanted['failed'];

            return $empty;
        }

        try {
            $listed = $this->ads->fetchAllSbAds(['ENABLED', 'PAUSED'], [$cid]);
        } catch (\Throwable $e) {
            $empty['message'] = $this->adsError($e);

            return $empty;
        }
        if (empty($listed['success'])) {
            $empty['message'] = (string) ($listed['message'] ?? 'Could not load SB ads from Amazon.');

            return $empty;
        }
        $ads = [];
        foreach ($listed['ads'] ?? [] as $ad) {
            if (is_array($ad)) {
                $ads[] = $ad;
            }
        }
        if ($ads === []) {
            $empty['message'] = 'This SB campaign has no creative yet. Create the ad in Amazon Ads first, then add products here.';
            $empty['failed'] = $wanted['failed'];

            return $empty;
        }

        $target = $this->pickAd($ads);
        $adId = preg_replace('/\D+/', '', trim((string) ($target['adId'] ?? $target['ad_id'] ?? ''))) ?: '';
        if ($adId === '') {
            $empty['message'] = 'The SB ad is missing an ad id.';

            return $empty;
        }

        $current = AmazonAdsCampaignSkuSync::extractAsinsFromSbAd($target);
        $next = $adding
            ? self::mergeAsins($current, $wanted['asins'])
            : self::withoutAsins($current, $wanted['asins']);

        $changed = array_values(array_diff($adding ? $next : $current, $adding ? $current : $next));
        if ($changed === []) {
            $empty['success'] = true;
            $empty['message'] = $adding
                ? 'Those products are already on the SB creative.'
                : 'Those products are not on the SB creative.';
            $empty['failed'] = $wanted['failed'];

            return $empty;
        }

        if (! $adding && $next === []) {
            try {
                $deleted = $this->ads->deleteSbAds([$adId]);
            } catch (\Throwable $e) {
                $empty['message'] = $this->adsError($e);

                return $empty;
            }
            $err = $this->firstAdsError($deleted);
            if ($err !== '') {
                $empty['message'] = $err;

                return $empty;
            }
            $this->persist($cid, $listed, $adId, true);
            $empty['success'] = true;
            $empty['removed'] = $changed;
            $empty['deleted_ads'] = [$adId];
            $empty['failed'] = $wanted['failed'];
            $empty['message'] = 'Removed the last product and deleted the SB ad.';

            return $empty;
        }

        try {
            $updated = $this->ads->updateSbAds([$this->updatePayload($target, $adId, $next)]);
        } catch (\Throwable $e) {
            $empty['message'] = $this->adsError($e);

            return $empty;
        }
        $err = $this->firstAdsError($updated);
        if ($err !== '') {
            $empty['message'] = $err;

            return $empty;
        }

        $this->persist($cid, $listed, $adId, false, $next);
        $empty['success'] = true;
        $empty['added'] = $adding ? $changed : [];
        $empty['removed'] = $adding ? [] : $changed;
        $empty['failed'] = $wanted['failed'];
        $n = count($changed);
        $empty['message'] = $adding
            ? ($n === 1 ? '1 product added to the SB creative.' : $n.' products added to the SB creative.')
            : ($n === 1 ? '1 product removed from the SB creative.' : $n.' products removed from the SB creative.');

        return $empty;
    }

    /**
     * @param  list<string>  $skus
     * @param  list<string>  $asins
     * @return array{asins: list<string>, failed: list<array{sku: string, message: string}>}
     */
    private function resolveAsins(array $skus, array $asins): array
    {
        $out = [];
        $failed = [];
        foreach ($asins as $raw) {
            $a = self::normalizeAsin($raw);
            if ($a !== '') {
                $out[$a] = true;
            } elseif (trim((string) $raw) !== '') {
                $failed[] = ['sku' => trim((string) $raw), 'message' => 'Not a valid ASIN.'];
            }
        }
        $needSku = [];
        foreach ($skus as $raw) {
            $sku = trim((string) $raw);
            if ($sku === '') {
                continue;
            }
            $asAsin = self::normalizeAsin($sku);
            if ($asAsin !== '') {
                $out[$asAsin] = true;

                continue;
            }
            $needSku[] = $sku;
        }
        if ($needSku !== []) {
            $map = AmazonAdsCampaignSkuSync::asinsBySkus($needSku);
            foreach ($needSku as $sku) {
                $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
                $asin = $map[$key] ?? '';
                if (self::normalizeAsin($asin) !== '') {
                    $out[self::normalizeAsin($asin)] = true;
                } else {
                    $failed[] = ['sku' => $sku, 'message' => 'No ASIN for this SKU.'];
                }
            }
        }

        return ['asins' => array_keys($out), 'failed' => $failed];
    }

    /**
     * @param  list<array<string, mixed>>  $ads
     * @return array<string, mixed>
     */
    private function pickAd(array $ads): array
    {
        $withAsins = [];
        foreach ($ads as $ad) {
            if (AmazonAdsCampaignSkuSync::extractAsinsFromSbAd($ad) !== []) {
                $withAsins[] = $ad;
            }
        }
        $pool = $withAsins !== [] ? $withAsins : $ads;
        foreach ($pool as $ad) {
            if (strtoupper(trim((string) ($ad['state'] ?? ''))) === 'ENABLED') {
                return $ad;
            }
        }

        return $pool[0];
    }

    /**
     * @param  array<string, mixed>  $ad
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    private function updatePayload(array $ad, string $adId, array $asins): array
    {
        $payload = [
            'adId' => $adId,
            'creative' => ['asins' => $asins],
        ];
        $landing = $ad['landingPage'] ?? null;
        if (is_array($landing) && (isset($landing['asins']) || isset($landing['pageType']))) {
            $page = ['asins' => $asins];
            if (isset($landing['pageType']) && is_string($landing['pageType']) && $landing['pageType'] !== '') {
                $page['pageType'] = $landing['pageType'];
            }
            $payload['landingPage'] = $page;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $listed
     * @param  list<string>|null  $nextAsins
     */
    private function persist(string $campaignId, array $listed, string $adId, bool $deleted, ?array $nextAsins = null): void
    {
        $ads = [];
        foreach ($listed['ads'] ?? [] as $ad) {
            if (! is_array($ad)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', trim((string) ($ad['adId'] ?? $ad['ad_id'] ?? ''))) ?: '';
            if ($id === $adId && $deleted) {
                continue;
            }
            if ($id === $adId && $nextAsins !== null) {
                $ad['creative'] = array_merge(is_array($ad['creative'] ?? null) ? $ad['creative'] : [], ['asins' => $nextAsins]);
                if (isset($ad['landingPage']) && is_array($ad['landingPage'])) {
                    $ad['landingPage']['asins'] = $nextAsins;
                }
            }
            $ads[] = $ad;
        }
        AmazonAdsCampaignSkuSync::persistSbAds($this->ads->resolvedProfileId() ?: 'default', $ads);
        if ($deleted && Schema::hasTable('amazon_ads_campaign_skus')) {
            AmazonAdsCampaignSku::query()
                ->where('ad_id', 'like', AmazonAdsCampaignSkuSync::SB_AD_PREFIX.$adId.':%')
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function firstAdsError(array $resp): string
    {
        foreach (data_get($resp, 'ads.error', []) ?: [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $msg = trim((string) (data_get($row, 'errors.0.errorValue.message')
                ?? data_get($row, 'errors.0.message')
                ?? data_get($row, 'errorValue.message')
                ?? data_get($row, 'message')
                ?? ''));
            if ($msg !== '') {
                return $msg;
            }
        }

        return '';
    }

    private function adsError(\Throwable $e): string
    {
        return $this->ads->amazonErrorMessage($e);
    }
}
