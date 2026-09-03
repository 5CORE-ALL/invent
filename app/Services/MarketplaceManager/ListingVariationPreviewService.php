<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Collection;

class ListingVariationPreviewService
{
    public function __construct(
        private Temu2ListingPublishService $temu2,
        private TemuListingPublishService $temu,
        private FaireListingPublishService $faire,
        private AliexpressListingPublishService $aliexpress,
        private ReverbListingPublishService $reverb,
    ) {
    }

    /**
     * @param  list<string>  $seedSkus
     * @param  array<string, string>  $skuParents
     * @return array{success: bool, groups: list<array<string, mixed>>}
     */
    public function previewFromSkus(array $seedSkus, string $channel, array $skuParents = [], string $mode = 'variation'): array
    {
        $channel = strtolower(trim($channel));
        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if (in_array($channel, ['temu2', 'temutwo'], true)) {
            return $this->temu2->previewFromSkus($seedSkus, $skuParents, $mode);
        }

        $seeds = [];
        foreach ($seedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $seeds[strtoupper($sku)] = $sku;
            }
        }
        $seedList = array_values($seeds);

        $groups = [];
        foreach ($this->expandSeedSkusToParentGroups($seedSkus, $mode === 'variation') as $parent => $children) {
            $groups[] = $this->formatPreviewGroup($channel, $parent, $children, $seedList, $mode);
        }

        $payload = [
            'success' => true,
            'groups' => array_values($groups),
        ];
        if (in_array($channel, ['reverb', 'reverbcom'], true) && $seedList !== []) {
            $payload['suggested_category'] = $this->reverb->suggestCategoryForSku($seedList[0]);
        }
        if ($channel === 'aliexpress' && $seedList !== []) {
            $payload['suggested_category'] = $this->aliexpress->suggestCategoryForSku($seedList[0]);
        }

        return $payload;
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, string $channel, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = '', ?int $categoryId = null, ?string $categoryUuid = null, ?string $categoryName = null): array
    {
        $channel = strtolower(trim($channel));
        if (in_array($channel, ['temu2', 'temutwo'], true)) {
            return $this->temu2->publishSkus($skus, $expandSiblings, $mode, $parentHint);
        }
        if (in_array($channel, ['temu', 'temu1'], true)) {
            return $this->temu->publishSkus($skus, $expandSiblings, $mode, $parentHint);
        }
        if ($channel === 'faire') {
            return $this->faire->publishSkus($skus, $expandSiblings);
        }
        if ($channel === 'aliexpress') {
            return $this->aliexpress->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId, $categoryName);
        }
        if (in_array($channel, ['reverb', 'reverbcom'], true)) {
            return $this->reverb->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryUuid);
        }

        $label = $this->channelLabel($channel);

        return [
            'success' => false,
            'message' => $label.' listing API is not connected. Add images on CP Master, then export these SKUs to list them on '.$label.'.',
        ];
    }

    /**
     * @param  list<string>  $seedSkus
     * @return array<string, Collection<int, ProductMaster>>
     */
    private function expandSeedSkusToParentGroups(array $seedSkus, bool $includeSiblings = true): array
    {
        $seeds = [];
        foreach ($seedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $seeds[strtoupper($sku)] = $sku;
        }
        $seeds = array_values($seeds);
        if ($seeds === []) {
            return [];
        }

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $seeds)
            ->get();

        if (! $includeSiblings) {
            $groups = [];
            foreach ($products as $product) {
                $sku = trim((string) $product->sku);
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                $parent = $this->groupKeyForProduct($product);
                if (! isset($groups[$parent])) {
                    $groups[$parent] = collect();
                }
                $groups[$parent]->push($product);
            }

            return $groups;
        }

        $parentKeys = [];
        foreach ($products as $product) {
            $parentKeys[$this->groupKeyForProduct($product)] = true;
        }

        $groups = [];
        foreach (array_keys($parentKeys) as $parent) {
            $children = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->orderBy('sku')
                ->get();

            if ($children->isEmpty()) {
                $children = $products
                    ->filter(function ($product) use ($parent) {
                        $sku = (string) $product->sku;

                        return $this->groupKeyForProduct($product) === $parent
                            && stripos($sku, 'PARENT') === false;
                    })
                    ->values();
            }

            if ($children->isNotEmpty()) {
                $groups[$parent] = $children;
            }
        }

        return $groups;
    }

    /**
     * @param  Collection<int, ProductMaster>  $children
     * @param  list<string>  $seedSkus
     * @return array<string, mixed>
     */
    private function formatPreviewGroup(string $channel, string $parent, Collection $children, array $seedSkus = [], string $mode = 'variation'): array
    {
        $skus = $children->map(fn ($p) => trim((string) $p->sku))->filter()->values()->all();
        $cfg = ChannelListingRegistry::get($channel);
        $listedMap = ($cfg !== null) ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $dataView = $cfg['dataView'] ?? null;
        $nrValues = ($dataView && class_exists($dataView))
            ? ListingCountsEngine::loadNrValues($dataView, $skus)
            : collect();
        $shopify = ShopifySku::mapByProductSkus($skus);
        $seedLookup = [];
        foreach ($seedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $seedLookup[strtoupper($sku)] = true;
            }
        }

        $rows = [];
        $publishSkus = [];
        foreach ($children as $product) {
            $sku = trim((string) $product->sku);
            $classified = $this->classifyChildSku($sku, $product, $listedMap, $nrValues);
            $shopifyRow = $shopify->get($sku);
            $selected = $classified['status'] === 'will_publish'
                && isset($seedLookup[strtoupper($sku)]);
            $rows[] = [
                'sku' => $sku,
                'spec' => $sku,
                'inv' => (int) ($shopifyRow?->available_to_sell ?? $shopifyRow?->inv ?? 0),
                'status' => $classified['status'],
                'reason' => $classified['reason'],
                'selected' => $selected,
            ];
            if ($classified['status'] === 'will_publish') {
                $publishSkus[] = $sku;
            }
        }

        return [
            'parent' => $parent,
            'children' => $rows,
            'publish_skus' => $publishSkus,
            'publish_count' => count($publishSkus),
        ];
    }

    /**
     * @param  array<string, string>  $listedMap
     * @return array{status: string, reason: string}
     */
    private function classifyChildSku(string $sku, ProductMaster $product, array $listedMap, $nrValues): array
    {
        if (stripos($sku, 'PARENT') !== false) {
            return ['status' => 'skipped_parent', 'reason' => 'Parent row'];
        }
        $listedId = trim((string) ($listedMap[strtolower($sku)] ?? ''));
        if ($listedId !== '') {
            return ['status' => 'skipped_listed', 'reason' => 'Already listed'];
        }
        $nrReq = ListingCountsEngine::nrReqFromDataView(
            $nrValues->get(strtoupper($sku))
        );
        if ($nrReq === 'NR') {
            return ['status' => 'skipped_nrl', 'reason' => 'NRL'];
        }
        if ($this->productMasterImages($product) === []) {
            return ['status' => 'skipped_no_image', 'reason' => 'No images on product master'];
        }

        return ['status' => 'will_publish', 'reason' => ''];
    }

    /**
     * @return list<string>
     */
    private function productMasterImages(ProductMaster $product): array
    {
        $urls = [];
        $push = function (string $raw) use (&$urls): void {
            $raw = trim($raw);
            if ($raw === '' || in_array($raw, $urls, true)) {
                return;
            }
            $urls[] = $raw;
        };

        $push((string) ($product->main_image ?? ''));
        $push((string) ($product->main_image_brand ?? ''));
        for ($i = 1; $i <= 19; $i++) {
            $push((string) ($product->{'image'.$i} ?? ''));
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['image_path', 'image', 'Image', 'main_image', 'photo'] as $key) {
            $raw = $values[$key] ?? '';
            $push(is_array($raw) ? (string) ($raw[0]['url'] ?? $raw[0] ?? '') : (string) $raw);
        }

        return $urls;
    }

    private function groupKeyForProduct(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));

        return $parent !== '' ? $parent : trim((string) $product->sku);
    }

    private function channelLabel(string $channel): string
    {
        $labels = [
            'ebay' => 'eBay',
            'ebaytwo' => 'eBay 2',
            'ebaythree' => 'eBay 3',
            'ebayvariation' => 'eBay Variation',
            'temu' => 'Temu',
            'temu2' => 'Temu 2',
            'shein' => 'Shein',
            'amazon' => 'Amazon',
            'aliexpress' => 'AliExpress',
            'wayfair' => 'Wayfair',
            'walmart' => 'Walmart',
            'macys' => "Macy's",
            'faire' => 'Faire',
            'pls' => 'PLS',
            'doba' => 'Doba',
            'reverb' => 'Reverb',
            'bestbuyusa' => 'Best Buy USA',
            'neweggb2c' => 'Newegg B2C',
            'neweggb2b' => 'Newegg B2B',
            'tiktokshop' => 'TikTok Shop',
            'tiktokshop2' => 'TikTok Shop 2',
            'fbmarketplace' => 'FB Marketplace',
            'fbshop' => 'FB Shop',
            'instagramshop' => 'Instagram Shop',
            'shopifyb2c' => 'Shopify B2C',
            'mercariwoship' => 'Mercari w/o Ship',
            'autods' => 'AutoDS',
            'poshmark' => 'Poshmark',
            'spocket' => 'Spocket',
            'zendrop' => 'Zendrop',
            'syncee' => 'Syncee',
            'offerup' => 'OfferUp',
            'appscenic' => 'AppScenic',
            'yamibuy' => 'Yamibuy',
            'swgearexchange' => 'SW Gear Exchange',
        ];

        return $labels[$channel] ?? strtoupper($channel);
    }
}
