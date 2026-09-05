<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ListingManagerChannelDraft;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Support\Facades\Schema;

class ListingManagerPublishDispatcher
{
    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    public function publish(ListingManagerChannelDraft $draft, array $details): array
    {
        $channelName = (string) ($draft->channel->channel ?? '');
        $key = ListingChannelCounts::normalize($channelName);
        $sku = trim((string) $draft->seller_sku);

        if (in_array($key, ['amazon', 'amazonfba', 'amz', 'amzfbm'], true)) {
            return $this->publishAmazon($draft, $details);
        }
        if (in_array($key, ['ebay', 'ebay1', 'ebayone', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree'], true)) {
            return $this->publishEbay($key, $draft, $details);
        }
        if (self::supportsListingApi($channelName)) {
            $skus = $this->publishSkusForMode($draft, $details);
            $mode = $this->effectivePublishMode($details, $skus);
            $categoryId = $this->categoryIdFromDetails($details);
            $categoryUuid = trim((string) ($details['category_uuid'] ?? $details['primary_category_id'] ?? '')) ?: null;
            $categoryName = trim((string) ($details['primary_category_path'] ?? $details['category_name'] ?? '')) ?: null;
            $weightLb = $this->weightLbFromDetails($details);
            $result = app(ListingVariationPreviewService::class)->publishSkus(
                $skus !== [] ? $skus : [$sku],
                $key,
                false,
                $mode,
                trim((string) ($details['parent_group'] ?? '')),
                $categoryId,
                $categoryUuid,
                $categoryName,
                $weightLb
            );
            if (! ($result['success'] ?? false)) {
                return $result;
            }

            return [
                'success' => true,
                'message' => $result['message'] ?? ('Published to '.$channelName.'.'),
                'item_id' => $result['goods_id'] ?? $result['item_id'] ?? null,
                'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : $skus,
            ];
        }

        $url = ListingChannelCounts::listingUrl($channelName);
        $hint = $url
            ? ' Draft is ready. Finish publish on the channel listing page: '.$url
            : ' Draft is ready. Open the marketplace listing page to finish publish.';

        return [
            'success' => false,
            'message' => $channelName.' is connected for drafts and variation grouping. Direct API publish is not wired yet.'.$hint,
            'queued' => true,
        ];
    }

    public static function canDirectPublish(string $channelName): bool
    {
        return self::supportsListingApi($channelName);
    }

    /**
     * Channels that can create a new listing or update an existing one via API.
     */
    public static function supportsListingApi(string $channelName): bool
    {
        $key = ListingChannelCounts::normalize($channelName);

        return in_array($key, [
            'amazon', 'amazonfba', 'amz', 'amzfbm',
            'ebay', 'ebay1', 'ebayone',
            'ebay2', 'ebaytwo',
            'ebay3', 'ebaythree',
            'temu', 'temu1',
            'temu2', 'temutwo',
            'faire',
            'reverb', 'reverbcom',
            'wayfair',
            'aliexpress',
            'tiktok', 'tiktokshop',
            'tiktok2', 'tiktokshop2', 'tiktoktwo',
            'shein',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishAmazon(ListingManagerChannelDraft $draft, array $details): array
    {
        $sku = trim((string) $draft->seller_sku);
        $skus = $this->publishSkusForMode($draft, $details);
        if ($skus === []) {
            $skus = $sku !== '' ? [$sku] : [];
        }

        $ok = [];
        $errors = [];
        $last = null;
        foreach ($skus as $rowSku) {
            $result = app(AmazonListingPublishService::class)->publishSku(
                $rowSku,
                $details,
                $rowSku === $sku ? (string) $draft->title : null,
                $rowSku === $sku && $draft->quantity !== null ? (int) $draft->quantity : null
            );
            if (! ($result['success'] ?? false)) {
                $errors[] = $rowSku.': '.trim((string) ($result['message'] ?? 'failed'));
                continue;
            }
            $ok[] = $rowSku;
            $last = $result;
        }

        if ($ok === []) {
            return $last ?? [
                'success' => false,
                'message' => $errors !== [] ? implode(' ', $errors) : 'Amazon publish failed.',
            ];
        }

        $message = $last['message'] ?? 'Published to Amazon.';
        if (count($ok) > 1) {
            $message = 'Published '.count($ok).' Amazon SKUs'.($errors !== [] ? ' (some siblings failed)' : '').'.';
        }

        return [
            'success' => true,
            'message' => $message,
            'item_id' => $last['goods_id'] ?? $last['item_id'] ?? null,
            'sibling_skus' => $ok,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishEbay(string $key, ListingManagerChannelDraft $draft, array $details): array
    {
        $variations = $this->variationPayload($draft, $details);
        $parentGroup = ListingManagerFamily::normalizeParentKey((string) ($details['parent_group'] ?? ''));
        $parentSku = ($parentGroup !== '' ? ListingManagerFamily::parentRowSkuFromKey($parentGroup) : null)
            ?: ListingManagerFamily::parentRowSku((string) $draft->seller_sku)
            ?: (ListingManagerFamily::isParentSku((string) $draft->seller_sku)
                ? trim((string) $draft->seller_sku)
                : '');
        $listingSku = $variations !== [] && $parentSku !== ''
            ? $parentSku
            : trim((string) $draft->seller_sku);

        $existingId = trim((string) $draft->external_listing_id);
        if ($variations !== [] && $existingId !== '' && ! ListingManagerEbayTradingPublisher::itemHasVariations($key, $existingId)) {
            ListingManagerEbayTradingPublisher::endItem($key, $existingId, 'OtherListingError');
        }

        $result = ListingManagerEbayTradingPublisher::publish($key, array_merge($details, [
            'sku' => $listingSku,
            'title' => $draft->title,
            'price' => $draft->price,
            'quantity' => $draft->quantity,
            'variations' => $variations,
        ]));

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $itemId = trim((string) ($result['item_id'] ?? ''));
        $skus = $variations !== []
            ? array_values(array_unique(array_filter(array_merge(
                [trim((string) $draft->seller_sku), $parentSku],
                array_map(fn ($row) => (string) $row['sku'], $variations)
            ))))
            : [trim((string) $draft->seller_sku)];
        $this->persistEbayMetrics($key, $skus, $itemId, (string) $draft->title, $draft->price, $draft->quantity);

        return [
            'success' => true,
            'message' => $variations !== []
                ? ('Published variation listing with '.count($variations).' child SKUs.')
                : ($result['message'] ?? 'Published.'),
            'item_id' => $itemId !== '' ? $itemId : null,
            'sibling_skus' => $skus,
        ];
    }

    /**
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishTemu2(string $sku): array
    {
        $result = app(Temu2ListingPublishService::class)->publish($sku);
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published to Temu 2.',
            'item_id' => $result['goods_id'] ?? null,
            'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : [$sku],
        ];
    }

    /**
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishFaire(string $sku): array
    {
        $result = app(FaireListingPublishService::class)->publishSkus([$sku], true);
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published to Faire.',
            'item_id' => $result['goods_id'] ?? null,
            'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : [$sku],
        ];
    }

    /**
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishReverb(string $sku): array
    {
        $result = app(ReverbListingPublishService::class)->publishSkus([$sku], false, 'single');
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published to Reverb.',
            'item_id' => $result['goods_id'] ?? null,
            'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : [$sku],
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<array{sku: string, price: float, quantity: int, variation_label: string, upc: string}>
     */
    private function variationPayload(ListingManagerChannelDraft $draft, array $details): array
    {
        $selected = $this->publishSkusForMode($draft, $details);
        $explicit = strtolower(trim((string) ($details['publish_mode'] ?? '')));
        if (count($selected) < 2) {
            return [];
        }
        if ($explicit === 'single' && ! ListingManagerFamily::isParentSku((string) $draft->seller_sku)) {
            return [];
        }

        $parentGroup = ListingManagerFamily::normalizeParentKey((string) ($details['parent_group'] ?? ''));
        $family = $parentGroup !== ''
            ? ListingManagerFamily::forParent($parentGroup, (string) $draft->seller_sku)
            : ListingManagerFamily::forSku((string) $draft->seller_sku);
        $bySku = [];
        foreach ($family['children'] as $child) {
            $bySku[strtoupper((string) $child['sku'])] = $child;
        }

        $rows = [];
        foreach ($selected as $sku) {
            $child = $bySku[strtoupper($sku)] ?? [
                'sku' => $sku,
                'variation_label' => $sku,
            ];
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            $price = (float) ($hydrated['price'] ?? $draft->price ?? 0);
            if (strcasecmp($sku, (string) $draft->seller_sku) === 0 && $draft->price) {
                $price = (float) $draft->price;
            }
            $liveQty = ListingManagerAmazonHydrator::shopifyQuantity($sku, true);
            $qty = $liveQty ?? (int) ($hydrated['quantity'] ?? $draft->quantity ?? 0);
            $rows[] = [
                'sku' => $sku,
                'price' => $price,
                'quantity' => $qty,
                'variation_label' => (string) ($child['variation_label'] ?? $sku),
                'upc' => '',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function publishMode(array $details): string
    {
        return strtolower(trim((string) ($details['publish_mode'] ?? ''))) === 'variation'
            ? 'variation'
            : 'single';
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $skus
     */
    private function effectivePublishMode(array $details, array $skus): string
    {
        if (count($skus) < 2) {
            return 'single';
        }
        $explicit = strtolower(trim((string) ($details['publish_mode'] ?? '')));
        if ($explicit === 'single') {
            return 'single';
        }

        return 'variation';
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private function publishSkusForMode(ListingManagerChannelDraft $draft, array $details): array
    {
        $sku = trim((string) $draft->seller_sku);
        $parentGroup = ListingManagerFamily::normalizeParentKey((string) ($details['parent_group'] ?? ''));
        $family = $parentGroup !== ''
            ? ListingManagerFamily::forParent($parentGroup, $sku)
            : ListingManagerFamily::forSku($sku);
        $allowed = [];
        foreach ($family['skus'] as $familySku) {
            $familySku = trim((string) $familySku);
            if ($familySku === '' || ListingManagerFamily::isParentSku($familySku)) {
                continue;
            }
            $allowed[strtoupper($familySku)] = $familySku;
        }

        $isParent = ListingManagerFamily::isParentSku($sku);
        $explicit = strtolower(trim((string) ($details['publish_mode'] ?? '')));
        $useVariation = ($isParent && count($allowed) > 1)
            || $explicit === 'variation'
            || ($explicit === '' && count($allowed) > 1);

        if (! $useVariation) {
            if ($isParent) {
                return array_values($allowed);
            }

            return $sku !== '' ? [$sku] : [];
        }

        $selected = $details['variation_skus'] ?? [];
        if (! is_array($selected)) {
            $selected = [];
        }

        $out = [];
        $seen = [];
        foreach ($selected as $row) {
            $candidate = trim((string) $row);
            $key = strtoupper($candidate);
            if ($candidate === '' || ! isset($allowed[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $allowed[$key];
        }

        $currentKey = strtoupper($sku);
        if ($sku !== '' && isset($allowed[$currentKey]) && ! isset($seen[$currentKey])) {
            array_unshift($out, $allowed[$currentKey]);
        }

        if (count($out) < 2) {
            $out = array_values($allowed);
        }

        return $out !== [] ? $out : ($sku !== '' && ! $isParent ? [$sku] : []);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function categoryIdFromDetails(array $details): ?int
    {
        $raw = trim((string) ($details['primary_category_id'] ?? $details['category_uuid'] ?? ''));
        if ($raw !== '' && ctype_digit($raw)) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function weightLbFromDetails(array $details): ?float
    {
        $lb = (float) ($details['package_weight_lb'] ?? 0);
        $oz = (float) ($details['package_weight_oz'] ?? 0);
        $total = $lb + ($oz / 16);
        if ($total > 0) {
            return round($total, 4);
        }

        return null;
    }

    /**
     * @param  list<string>  $skus
     */
    private function persistEbayMetrics(
        string $key,
        array $skus,
        string $itemId,
        string $title,
        mixed $price,
        mixed $qty
    ): void {
        if ($itemId === '') {
            return;
        }

        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $link = 'https://www.ebay.com/itm/'.$itemId;
            if (in_array($key, ['ebay2', 'ebaytwo'], true) && Schema::hasTable('ebay_2_metrics')) {
                Ebay2Metric::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'item_id' => $itemId,
                        'ebay_title' => $title,
                        'ebay_price' => $price,
                        'ebay_stock' => $qty,
                        'ebay_link' => $link,
                    ]
                );
            } elseif (in_array($key, ['ebay3', 'ebaythree'], true) && Schema::hasTable('ebay_3_metrics')) {
                Ebay3Metric::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'item_id' => $itemId,
                        'ebay_title' => $title,
                        'ebay_price' => $price,
                        'ebay_stock' => $qty,
                        'ebay_link' => $link,
                    ]
                );
            } elseif (in_array($key, ['ebay', 'ebay1', 'ebayone'], true) && Schema::hasTable('ebay_metrics')) {
                EbayMetric::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'item_id' => $itemId,
                        'ebay_title' => $title,
                        'ebay_price' => $price,
                        'ebay_stock' => $qty,
                        'ebay_link' => $link,
                    ]
                );
            }
        }
    }
}
