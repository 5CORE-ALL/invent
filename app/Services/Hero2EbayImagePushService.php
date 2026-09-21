<?php

namespace App\Services;

use App\Http\Controllers\ProductMaster\ImageMasterController;
use App\Models\Hero2EbayPush;
use App\Models\ProductRawImage;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Hero2EbayImagePushService
{
    public const ACCOUNTS = [
        'ebay' => EbayApiService::class,
        'ebay2' => Ebay2ApiService::class,
        'ebay3' => EbayThreeApiService::class,
    ];

    public const METRICS = [
        'ebay' => 'ebay_metrics',
        'ebay2' => 'ebay_2_metrics',
        'ebay3' => 'ebay_3_metrics',
    ];

    /**
     * Push one Hero Image 2 URL to one eBay account.
     * Variation listings update only that SKU's VariationSpecificPictureSet.
     *
     * @return array{success: bool, message: string, account: string, label: string, stamp?: array<string, mixed>}
     */
    public function push(string $sku, string $account, string $imageUrl): array
    {
        $sku = $this->normalizeSku($sku);
        $account = strtolower(trim($account));
        $imageUrl = $this->absoluteImageUrl($imageUrl);
        $label = Hero2EbayPush::label($account);

        if ($sku === '' || $imageUrl === '') {
            return ['success' => false, 'message' => 'SKU and image URL are required.', 'account' => $account, 'label' => $label];
        }
        if (! isset(self::ACCOUNTS[$account])) {
            return ['success' => false, 'message' => 'Unknown eBay account.', 'account' => $account, 'label' => $label];
        }

        try {
            $svc = app(self::ACCOUNTS[$account]);
            $itemId = $this->resolveItemId($sku, $account, $svc);
            if (! $itemId) {
                return [
                    'success' => false,
                    'message' => 'No '.$label.' listing found for this SKU.',
                    'account' => $account,
                    'label' => $label,
                ];
            }

            $getItem = $svc->getItem($itemId);
            if (! is_array($getItem)) {
                return [
                    'success' => false,
                    'message' => 'GetItem failed for '.$label.' listing '.$itemId.'.',
                    'account' => $account,
                    'label' => $label,
                ];
            }

            $ctx = $svc->tradingReviseContext();
            $isVariation = EbayTradingReviseItem::listingHasVariations($getItem);

            if ($isVariation) {
                $result = EbayTradingReviseItem::reviseVariationSpecificPictures(
                    $ctx['endpoint'],
                    $ctx['compatLevel'],
                    $ctx['devId'],
                    $ctx['appId'],
                    $ctx['certId'],
                    $ctx['siteId'],
                    $ctx['authToken'],
                    $getItem,
                    $sku,
                    [$imageUrl]
                );
            } else {
                $imageMaster = app(ImageMasterController::class);
                $live = $imageMaster->fetchEbayGallery($sku, $account);
                $existing = ($live['success'] ?? false)
                    ? array_values($live['images'] ?? [])
                    : $imageMaster->existingImageUrls($account, $sku);
                $images = $this->prependAsMain($imageUrl, $existing);
                $result = EbayTradingReviseItem::reviseItemImages(
                    $ctx['endpoint'],
                    $ctx['compatLevel'],
                    $ctx['devId'],
                    $ctx['appId'],
                    $ctx['certId'],
                    $ctx['siteId'],
                    $ctx['authToken'],
                    $itemId,
                    $images
                );
                if ($result['success'] ?? false) {
                    $result['message'] = 'Updated main image on '.$label.' (single-SKU listing).';
                    $result['is_variation'] = false;
                    $result['item_id'] = $itemId;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Hero Image 2 eBay push failed', [
                'sku' => $sku,
                'account' => $account,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage(), 'account' => $account, 'label' => $label];
        }

        $ok = (bool) ($result['success'] ?? false);
        $message = (string) ($result['message'] ?? ($ok ? 'Updated image on '.$label.'.' : 'Could not update image on '.$label.'.'));

        $payload = [
            'success' => $ok,
            'message' => trim($message),
            'account' => $account,
            'label' => $label,
            'is_variation' => (bool) ($result['is_variation'] ?? $isVariation),
            'variation_value' => $result['variation_value'] ?? null,
            'item_id' => $result['item_id'] ?? $itemId,
        ];

        if ($ok) {
            $payload['stamp'] = $this->rememberPush(
                $sku,
                $account,
                $imageUrl,
                $payload['item_id'] ?? null,
                $payload['variation_value'] ?? null,
                (bool) $payload['is_variation']
            );
        }

        return $payload;
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, account: string, label: string, results: list<array<string, mixed>>}
     */
    public function pushMany(array $skus, string $account): array
    {
        $account = strtolower(trim($account));
        $label = Hero2EbayPush::label($account);
        $results = [];
        $ok = 0;
        $fail = 0;

        $unique = [];
        foreach ($skus as $sku) {
            $sku = $this->normalizeSku((string) $sku);
            if ($sku === '' || str_contains(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $unique[$sku] = $sku;
        }

        foreach (array_values($unique) as $sku) {
            $url = $this->firstHero2Url($sku);
            if ($url === '') {
                $fail++;
                $results[] = [
                    'sku' => $sku,
                    'success' => false,
                    'message' => 'No Hero Image 2 uploaded for this SKU.',
                    'account' => $account,
                    'label' => $label,
                ];
                continue;
            }

            $row = $this->push($sku, $account, $url);
            $row['sku'] = $sku;
            $results[] = $row;
            if ($row['success'] ?? false) {
                $ok++;
            } else {
                $fail++;
            }
        }

        return [
            'success' => $fail === 0 && $ok > 0,
            'message' => $ok.' of '.($ok + $fail).' SKUs pushed to '.$label.'.',
            'account' => $account,
            'label' => $label,
            'ok' => $ok,
            'fail' => $fail,
            'results' => $results,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function stampsBySku(): array
    {
        if (! Schema::hasTable('hero2_ebay_pushes')) {
            return [];
        }

        $out = [];
        foreach (Hero2EbayPush::query()->orderBy('id')->get() as $row) {
            $sku = $this->normalizeSku((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $out[$sku][$row->account] = $row->toUiArray();
        }

        return $out;
    }

    public function forgetStampsForSku(string $sku): void
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '' || ! Schema::hasTable('hero2_ebay_pushes')) {
            return;
        }

        Hero2EbayPush::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)
                    ->orWhere('sku', strtoupper($sku))
                    ->orWhere('sku', strtolower($sku));
            })
            ->delete();
    }

    public function firstHero2Url(string $sku): string
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            return '';
        }

        $image = ProductRawImage::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)
                    ->orWhere('sku', strtoupper($sku))
                    ->orWhere('sku', strtolower($sku));
            })
            ->whereIn('kind', [ProductRawImage::KIND_HERO_2, ProductRawImage::KIND_HERO_2_AI])
            ->orderByRaw('CASE WHEN kind = ? THEN 0 ELSE 1 END', [ProductRawImage::KIND_HERO_2])
            ->orderBy('id')
            ->first();

        return $image ? $this->absoluteImageUrl($image->url) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function rememberPush(
        string $sku,
        string $account,
        string $imageUrl,
        ?string $itemId,
        ?string $variationValue,
        bool $isVariation,
    ): array {
        if (! Schema::hasTable('hero2_ebay_pushes')) {
            return [];
        }

        $row = Hero2EbayPush::query()->updateOrCreate(
            ['sku' => $sku, 'account' => $account],
            [
                'image_url' => $imageUrl,
                'item_id' => $itemId,
                'variation_value' => $variationValue,
                'is_variation' => $isVariation,
                'pushed_at' => now(),
            ]
        );

        return $row->toUiArray();
    }

    private function resolveItemId(string $sku, string $account, object $svc): ?string
    {
        $table = self::METRICS[$account] ?? null;
        if ($table && Schema::hasTable($table)) {
            $row = DB::table($table)->where(function ($q) use ($sku) {
                $q->where('sku', $sku)
                    ->orWhere('sku', strtoupper($sku))
                    ->orWhere('sku', strtolower($sku));
            })->first();
            if (! $row && Schema::hasColumn($table, 'item_id')) {
                $row = DB::table($table)->where('item_id', $sku)->first();
            }
            $itemId = ($row && ! empty($row->item_id)) ? trim((string) $row->item_id) : null;
            if ($itemId) {
                return $itemId;
            }
        }

        try {
            $token = $svc->generateBearerToken();
            $resolved = EbaySellInventoryListingResolver::resolveWithTradingFallback(
                $token,
                $svc->getTradingEndpoint(),
                $svc->getTradingHeadersForResolver(),
                $sku
            );

            return $resolved ? trim((string) $resolved) : null;
        } catch (\Throwable $e) {
            Log::warning('Hero Image 2 eBay item lookup failed', [
                'sku' => $sku,
                'account' => $account,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<string>  $existing
     * @return list<string>
     */
    private function prependAsMain(string $mainUrl, array $existing): array
    {
        $out = [$mainUrl];
        $seen = [strtolower($mainUrl) => true];
        foreach ($existing as $url) {
            $url = trim((string) $url);
            $key = strtolower($url);
            if ($url === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $url;
        }

        return array_slice($out, 0, 12);
    }

    private function absoluteImageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return url('/'.ltrim($url, '/'));
    }

    private function normalizeSku(string $sku): string
    {
        return trim(str_replace("\u{00a0}", ' ', $sku));
    }
}
