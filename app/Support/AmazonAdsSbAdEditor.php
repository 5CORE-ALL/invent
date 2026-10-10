<?php

namespace App\Support;

use App\Models\AmazonAdsCampaignSku;
use App\Services\AmazonAdsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        return preg_match('/^[A-Z0-9]{10}$/', $a) === 1 ? $a : '';
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

        $creativeRow = $this->loadCreative($adId);
        $current = $this->currentAsins($cid, $target, $creativeRow);
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

        $creative = self::copyCreativeForAsins($creativeRow, $target, $next);
        try {
            $updated = $this->submitCreative($adId, $creativeRow, $creative);
        } catch (\Throwable $e) {
            $empty['message'] = $this->adsError($e);

            return $empty;
        }
        $err = $this->firstAdsError($updated);
        if ($err !== '' || ! $this->creativeSubmitSucceeded($updated)) {
            $empty['message'] = $err !== '' ? $err : 'Amazon did not accept the SB creative product update.';

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
            $missing = [];
            foreach ($needSku as $sku) {
                $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
                $asin = $map[$key] ?? $map[$sku] ?? '';
                $norm = self::normalizeAsin($asin);
                if ($norm !== '') {
                    $out[$norm] = true;
                } else {
                    $missing[] = $sku;
                }
            }
            if ($missing !== []) {
                $fromAmazon = $this->asinsFromProductMetadata($missing);
                foreach ($missing as $sku) {
                    $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
                    $asin = $fromAmazon[$key] ?? $fromAmazon[$sku] ?? '';
                    $norm = self::normalizeAsin($asin);
                    if ($norm !== '') {
                        $out[$norm] = true;
                    } else {
                        $failed[] = ['sku' => $sku, 'message' => 'No ASIN for this SKU.'];
                    }
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
     * @return array<string, mixed>
     */
    private function loadCreative(string $adId): array
    {
        try {
            $resp = $this->ads->listSbAdCreatives($adId);
        } catch (\Throwable $e) {
            Log::warning('SB creative list failed', ['adId' => $adId, 'error' => $e->getMessage()]);

            return [];
        }
        $rows = $resp['creatives'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [];
        }
        $best = null;
        $bestScore = -1;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = strtoupper(trim((string) ($row['creativeStatus'] ?? '')));
            $score = match ($status) {
                'PUBLISHED' => 5,
                'APPROVED_BY_MODERATION' => 4,
                'SUBMITTED_FOR_MODERATION' => 3,
                'PENDING_MODERATION_REVIEW' => 2,
                default => 1,
            };
            $updated = (int) ($row['lastUpdateTime'] ?? $row['creationTime'] ?? 0);
            if ($score > $bestScore || ($score === $bestScore && $updated > (int) ($best['lastUpdateTime'] ?? 0))) {
                $best = $row;
                $bestScore = $score;
            }
        }

        return is_array($best) ? $best : [];
    }

    /**
     * @param  array<string, mixed>  $ad
     * @param  array<string, mixed>  $creativeRow
     * @return list<string>
     */
    private function currentAsins(string $campaignId, array $ad, array $creativeRow): array
    {
        $fromCreative = AmazonAdsCampaignSkuSync::extractAsinsFromSbAd($creativeRow);
        if ($fromCreative !== []) {
            return $fromCreative;
        }
        $fromAd = AmazonAdsCampaignSkuSync::extractAsinsFromSbAd($ad);
        if ($fromAd !== []) {
            return $fromAd;
        }

        return $this->localAsins($campaignId);
    }

    /**
     * @return list<string>
     */
    private function localAsins(string $campaignId): array
    {
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            return [];
        }
        $rows = AmazonAdsCampaignSku::query()
            ->where('campaign_id', $campaignId)
            ->where('ad_id', 'like', AmazonAdsCampaignSkuSync::SB_AD_PREFIX.'%')
            ->whereNotNull('asin')
            ->where('asin', '!=', '')
            ->pluck('asin');
        $out = [];
        foreach ($rows as $asin) {
            $a = self::normalizeAsin($asin);
            if ($a !== '') {
                $out[$a] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param  array<string, mixed>  $creativeRow
     * @param  array<string, mixed>  $ad
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    public static function copyCreativeForAsins(array $creativeRow, array $ad, array $asins): array
    {
        $props = self::creativeProperties($creativeRow);
        if ($props === []) {
            $props = is_array($ad['creative'] ?? null) ? $ad['creative'] : [];
        }
        $keep = [
            'brandName', 'headline', 'brandLogoAssetId', 'brandLogoAssetID', 'brandLogoCrop',
            'customImageAssetId', 'customImageCrop', 'customImages', 'consentToTranslate',
            'shouldOptimizeAsins', 'creativePropertiesToOptimize',
        ];
        $out = ['asins' => $asins];
        foreach ($keep as $key) {
            if (! array_key_exists($key, $props) || $props[$key] === null || $props[$key] === '') {
                continue;
            }
            $out[$key] = $props[$key];
        }
        if (isset($out['brandLogoAssetID']) && ! isset($out['brandLogoAssetId'])) {
            $out['brandLogoAssetId'] = $out['brandLogoAssetID'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function creativeProperties(array $row): array
    {
        $direct = $row['creativeProperties'] ?? $row['creative'] ?? $row;
        if (! is_array($direct)) {
            return [];
        }
        if (isset($direct['asins']) || isset($direct['brandName']) || isset($direct['headline']) || isset($direct['brandLogoAssetId']) || isset($direct['brandLogoAssetID'])) {
            return $direct;
        }
        foreach ($direct as $value) {
            if (is_array($value) && (isset($value['asins']) || isset($value['brandName']) || isset($value['headline']))) {
                return $value;
            }
        }

        return $direct;
    }

    /**
     * @param  array<string, mixed>  $creativeRow
     * @param  array<string, mixed>  $creative
     * @return array<string, mixed>
     */
    private function submitCreative(string $adId, array $creativeRow, array $creative): array
    {
        $type = strtoupper(trim((string) ($creativeRow['creativeType'] ?? $creativeRow['type'] ?? '')));
        $order = match (true) {
            str_contains($type, 'EXTENDED') => ['extended', 'collection', 'manual'],
            str_contains($type, 'MANUAL') => ['manual', 'collection', 'extended'],
            default => ['collection', 'manual', 'extended'],
        };
        $last = ['creatives' => ['error' => [['message' => 'No SB creative endpoint accepted the update.']]]];
        foreach ($order as $kind) {
            try {
                $resp = match ($kind) {
                    'manual' => $this->ads->updateSbManualCollectionCreative($adId, $creative),
                    'extended' => $this->ads->updateSbProductCollectionExtendedCreative($adId, $creative),
                    default => $this->ads->updateSbProductCollectionCreative($adId, $creative),
                };
            } catch (\Throwable $e) {
                Log::warning('SB creative update failed', [
                    'adId' => $adId,
                    'kind' => $kind,
                    'error' => $e->getMessage(),
                ]);
                $last = ['creatives' => ['error' => [['message' => $this->adsError($e)]]]];

                continue;
            }
            if ($this->firstAdsError($resp) === '' && $this->creativeSubmitSucceeded($resp)) {
                return $resp;
            }
            $last = $resp;
        }

        return $last;
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function creativeSubmitSucceeded(array $resp): bool
    {
        foreach (['creatives.success', 'ads.success'] as $path) {
            $ok = data_get($resp, $path, []);
            if (is_array($ok) && $ok !== []) {
                return true;
            }
        }

        return (string) (data_get($resp, 'creativeVersion') ?? '') !== ''
            || ((string) (data_get($resp, 'adId') ?? '') !== '' && $this->firstAdsError($resp) === '' && ! isset($resp['creatives']) && ! isset($resp['ads']));
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function asinsFromProductMetadata(array $skus): array
    {
        try {
            $resp = $this->ads->getProductMetadata($skus);
        } catch (\Throwable $e) {
            Log::warning('SB product metadata lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
        $out = [];
        foreach (($resp['ProductMetadataList'] ?? $resp['productMetadataList'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) ($row['sku'] ?? $row['sellerSku'] ?? ''))));
            $asin = self::normalizeAsin($row['asin'] ?? '');
            if ($sku !== '' && $asin !== '') {
                $out[$sku] = $asin;
            }
        }

        return $out;
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
        if (! $deleted && $nextAsins !== null && Schema::hasTable('amazon_ads_campaign_skus')) {
            $keep = [];
            foreach ($nextAsins as $asin) {
                $a = self::normalizeAsin($asin);
                if ($a !== '') {
                    $keep[$a] = true;
                }
            }
            $q = AmazonAdsCampaignSku::query()
                ->where('ad_id', 'like', AmazonAdsCampaignSkuSync::SB_AD_PREFIX.$adId.':%');
            if ($keep !== []) {
                $q->whereNotIn('asin', array_keys($keep));
            }
            $q->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function firstAdsError(array $resp): string
    {
        foreach (array_merge(
            is_array(data_get($resp, 'creatives.error')) ? data_get($resp, 'creatives.error') : [],
            is_array(data_get($resp, 'ads.error')) ? data_get($resp, 'ads.error') : [],
        ) as $row) {
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
