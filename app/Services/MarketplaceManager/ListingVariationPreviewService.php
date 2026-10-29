<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingExistingParents;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerFamily;
use App\Support\Marketplace\VariationListingContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListingVariationPreviewService
{
    public function __construct(
        private Temu2ListingPublishService $temu2,
        private Temu3ListingPublishService $temu3,
        private TemuListingPublishService $temu,
        private FaireListingPublishService $faire,
        private AliexpressListingPublishService $aliexpress,
        private ReverbListingPublishService $reverb,
        private WayfairListingPublishService $wayfair,
        private EbayListingPublishService $ebay,
        private TikTokListingPublishService $tiktok,
        private SheinListingPublishService $shein,
        private NeweggListingPublishService $newegg,
        private TopDawgListingPublishService $topdawg,
        private MiraklListingPublishService $mirakl,
        private AlibabaListingPublishService $alibaba,
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
        if ($mode === 'variation') {
            VariationListingContent::activate();
        }
        try {
        return $this->buildPreview($seedSkus, $channel, $skuParents, $mode);
        } finally {
            if ($mode === 'variation') {
                VariationListingContent::deactivate();
            }
        }
    }

    /**
     * @param  list<string>  $seedSkus
     * @param  array<string, string>  $skuParents
     * @return array{success: bool, groups: list<array<string, mixed>>}
     */
    private function buildPreview(array $seedSkus, string $channel, array $skuParents, string $mode): array
    {
        if (in_array($channel, ['temu2', 'temutwo'], true)) {
            return $this->temu2->previewFromSkus($seedSkus, $skuParents, $mode);
        }
        if (in_array($channel, ['temu3', 'temuthree'], true)) {
            return $this->temu3->previewFromSkus($seedSkus, $skuParents, $mode);
        }
        if (in_array($channel, ['temu', 'temu1'], true)) {
            return $this->temu->previewFromSkus($seedSkus, $skuParents, $mode);
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
            $previewSkus = $seedList;
            foreach ($groups as $group) {
                foreach (($group['children'] ?? []) as $child) {
                    $sku = trim((string) ($child['sku'] ?? ''));
                    if ($sku !== '') {
                        $previewSkus[] = $sku;
                    }
                }
            }
            $payload['suggested_package'] = $this->aliexpress->suggestPackageForSkus($previewSkus);
        }
        if ($channel === 'wayfair' && $seedList !== []) {
            $payload['suggested_category'] = $this->wayfair->suggestClassForSku($seedList[0]);
        }
        if ($this->isEbayChannel($channel) && $seedList !== []) {
            $payload['suggested_category'] = $this->ebay->suggestCategoryForSku($seedList[0], $channel);
        }
        if ($this->isTiktokChannel($channel) && $seedList !== []) {
            $payload['suggested_category'] = $this->tiktok->suggestCategoryForSku($seedList[0], $channel);
        }
        if ($this->isSheinChannel($channel) && $seedList !== []) {
            $payload['suggested_category'] = $this->shein->suggestCategoryForSku($seedList[0]);
        }
        if ($mode === 'single') {
            $perSku = $this->suggestCategoriesPerSku($groups, $channel);
            if ($perSku !== []) {
                $payload['suggested_categories'] = $perSku;
            }
        }

        return $payload;
    }

    /**
     * Single listings: every SKU is its own listing, so each gets the category that fits its own
     * product type (listed sibling, Listing Manager draft or title) instead of one shared category.
     * Spends at most ~20 seconds; SKUs not reached are resolved again when they are published.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, array{id: string, path: string, name: string}>
     */
    private function suggestCategoriesPerSku(array $groups, string $channel): array
    {
        $isReverb = in_array($channel, ['reverb', 'reverbcom'], true);
        $supported = $isReverb || $channel === 'aliexpress' || $channel === 'wayfair'
            || $this->isEbayChannel($channel) || $this->isTiktokChannel($channel) || $this->isSheinChannel($channel);
        if (! $supported) {
            return [];
        }

        $skus = [];
        foreach ($groups as $group) {
            foreach (($group['children'] ?? []) as $child) {
                $sku = trim((string) ($child['sku'] ?? ''));
                if ($sku !== '' && ($child['status'] ?? '') === 'will_publish') {
                    $skus[$sku] = true;
                }
            }
        }

        $out = [];
        $deadline = microtime(true) + 20;
        foreach (array_slice(array_keys($skus), 0, 60) as $sku) {
            if (microtime(true) > $deadline) {
                break;
            }
            try {
                if ($isReverb) {
                    $row = $this->reverb->suggestCategoryForSku($sku);
                } elseif ($channel === 'aliexpress') {
                    $row = $this->aliexpress->suggestCategoryForSku($sku);
                } elseif ($channel === 'wayfair') {
                    $row = $this->wayfair->suggestClassForSku($sku);
                } elseif ($this->isEbayChannel($channel)) {
                    $row = $this->ebay->suggestCategoryForSku($sku, $channel);
                } elseif ($this->isTiktokChannel($channel)) {
                    $row = $this->tiktok->suggestCategoryForSku($sku, $channel);
                } else {
                    $row = $this->shein->suggestCategoryForSku($sku);
                }
            } catch (\Throwable $e) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '0') {
                $id = '';
            }
            $path = trim((string) ($row['path'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' && $path === '' && $name === '') {
                continue;
            }
            $out[$sku] = ['id' => $id, 'path' => $path, 'name' => $name];
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, string $channel, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = '', ?int $categoryId = null, ?string $categoryUuid = null, ?string $categoryName = null, ?float $weightLb = null, ?float $weightKg = null, array $overrides = []): array
    {
        $variation = strtolower(trim($mode)) !== 'single';
        if ($variation) {
            VariationListingContent::activate();
        }

        try {
        $channel = strtolower(trim($channel));
        if (in_array($channel, ['temu2', 'temutwo'], true)) {
            return $this->temu2->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId);
        }
        if (in_array($channel, ['temu3', 'temuthree'], true)) {
            return $this->temu3->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId);
        }
        if (in_array($channel, ['temu', 'temu1'], true)) {
            return $this->temu->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId);
        }
        if ($channel === 'faire') {
            return $this->faire->publishSkus($skus, $expandSiblings, $mode, $overrides);
        }
        if ($channel === 'aliexpress') {
            return $this->aliexpress->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId, $categoryName, $weightLb, $weightKg, $overrides);
        }
        if (in_array($channel, ['reverb', 'reverbcom'], true)) {
            return $this->reverb->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryUuid, $categoryName);
        }
        if ($channel === 'wayfair') {
            return $this->wayfair->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId, $categoryName, $overrides);
        }
        if ($this->isEbayChannel($channel)) {
            return $this->ebay->publishSkus(
                $skus,
                $channel,
                $expandSiblings,
                $mode,
                $parentHint,
                $categoryId,
                $categoryName,
                is_array($overrides['item_specifics'] ?? null) ? $overrides['item_specifics'] : [],
                [
                    'attach_mode' => (string) ($overrides['attach_mode'] ?? ''),
                    'existing_listing_id' => (string) ($overrides['existing_listing_id'] ?? ''),
                ]
            );
        }
        if ($this->isTiktokChannel($channel)) {
            return $this->tiktok->publishSkus($skus, $channel, $expandSiblings, $mode, $parentHint, $categoryId, $categoryName, $weightLb);
        }
        if ($this->isSheinChannel($channel)) {
            return $this->shein->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId, $categoryName, $weightLb);
        }
        if ($this->isNeweggChannel($channel)) {
            return $this->newegg->publishSkus($skus, $channel, $expandSiblings, $mode, $parentHint, $categoryId, $overrides);
        }
        if (in_array($channel, ['topdawg', 'top-dawg', 'top_dawg'], true)) {
            return $this->topdawg->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryUuid, $categoryName, $overrides);
        }
        if ($channel === 'alibaba') {
            return $this->alibaba->publishSkus($skus, $expandSiblings, $mode, $parentHint, $categoryId, $overrides);
        }
        if (DirectStoreListingPublishService::channelFor($channel) !== null) {
            return app(DirectStoreListingPublishService::class)->publishSkus($channel, $skus, $overrides);
        }
        if ($this->isMiraklChannel($channel)) {
            $code = trim((string) ($categoryUuid ?? ''))
                ?: trim((string) ($categoryName ?? ''))
                ?: ($categoryId !== null && $categoryId > 0 ? (string) $categoryId : null);

            return $this->mirakl->publishSkus($skus, $channel, $expandSiblings, $mode, $parentHint, $code, $overrides);
        }
        if ($this->isAmazonChannel($channel)) {
            return $this->publishAmazon($skus);
        }

        $label = $this->channelLabel($channel);

        return [
            'success' => false,
            'message' => $label.' listing API is not connected. Add images on CP Master, then export these SKUs to list them on '.$label.'.',
        ];
        } finally {
            if ($variation) {
                VariationListingContent::deactivate();
            }
        }
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

        ListingManagerFamily::warmAttributes($skus);
        $rows = [];
        $publishSkus = [];
        foreach ($children as $product) {
            $sku = trim((string) $product->sku);
            $classified = $this->classifyChildSku($sku, $product, $listedMap, $nrValues, $channel);
            $shopifyRow = $shopify->get($sku);
            $selected = $classified['status'] === 'will_publish'
                && isset($seedLookup[strtoupper($sku)]);
            $attr = ListingManagerFamily::attributesForSku($sku);
            $rows[] = [
                'sku' => $sku,
                'spec' => $attr['name'] !== '' ? $attr['name'] : $sku,
                'variation_type' => $attr['type'],
                'variation_name' => $attr['name'],
                'inv' => (int) ($shopifyRow?->available_to_sell ?? $shopifyRow?->inv ?? 0),
                'status' => $classified['status'],
                'reason' => $classified['reason'],
                'selected' => $selected,
            ];
            if ($classified['status'] === 'will_publish') {
                $publishSkus[] = $sku;
            }
        }

        $attachSupported = ListingExistingParents::supports($channel);

        return [
            'parent' => $parent,
            'children' => $rows,
            'publish_skus' => $publishSkus,
            'publish_count' => count($publishSkus),
            'attach_supported' => $attachSupported,
            'existing_parents' => ($attachSupported && strtolower(trim($mode)) !== 'single')
                ? ListingExistingParents::forGroup($channel, $skus, $listedMap)
                : [],
        ];
    }

    /**
     * @param  array<string, string>  $listedMap
     * @return array{status: string, reason: string}
     */
    private function classifyChildSku(string $sku, ProductMaster $product, array $listedMap, $nrValues, string $channel = ''): array
    {
        if (stripos($sku, 'PARENT') !== false) {
            return ['status' => 'skipped_parent', 'reason' => 'Parent row'];
        }
        $listedId = trim((string) ($listedMap[strtolower($sku)] ?? ''));
        if ($listedId !== '') {
            return ['status' => 'skipped_listed', 'reason' => 'Already listed'];
        }
        // NRL SKUs are publishable too: every Missing L listing may be added to a group.
        if ($this->isAmazonChannel($channel)) {
            return $this->classifyAmazonChild($sku, $product);
        }
        if (VariationListingContent::active()) {
            $title = VariationListingContent::titleFor($product) ?? '';
            if ($title === '') {
                return ['status' => 'skipped_no_title', 'reason' => VariationListingContent::titleGapMessage()];
            }
            $description = VariationListingContent::descriptionFor($product) ?? '';
            if ($description === '') {
                return ['status' => 'skipped_no_description', 'reason' => VariationListingContent::descriptionGapMessage()];
            }
        }
        if (ListingManagerAmazonHydrator::imageMasterUploadSources($sku, (string) ($product->parent ?? '')) === []) {
            return ['status' => 'skipped_no_image', 'reason' => 'No images on Image Master'];
        }

        return ['status' => 'will_publish', 'reason' => ''];
    }

    /**
     * Amazon listings are one SKU each, so copy comes from the masters even when
     * the publish window is set to variation.
     *
     * @return array{status: string, reason: string}
     */
    private function classifyAmazonChild(string $sku, ProductMaster $product): array
    {
        $gap = $this->amazonMasterGap($sku, $product->toArray());
        if ($gap !== null) {
            return ['status' => 'skipped_no_'.$gap['key'], 'reason' => $gap['message']];
        }

        return ['status' => 'will_publish', 'reason' => ''];
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string|null, skus?: list<string>}
     */
    private function publishAmazon(array $skus): array
    {
        $publisher = app(AmazonListingPublishService::class);
        $ok = [];
        $errors = [];
        $last = null;
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                continue;
            }
            $product = ProductMaster::query()->where('sku', $sku)->whereNull('deleted_at')->first();
            $pm = $product ? $product->toArray() : [];
            $gap = $this->amazonMasterGap($sku, $pm);
            if ($gap !== null) {
                $errors[] = $sku.': '.$gap['message'];
                continue;
            }
            $content = $this->amazonMasterContent($sku, $pm);
            if (! $publisher->apiIsConfigured()) {
                $errors[] = $sku.': Amazon SP-API is not connected. Set Amazon client id, secret, refresh token, and seller id.';
                continue;
            }
            $productType = $publisher->suggestProductType($sku, $content['title']);
            if ($productType === '') {
                $errors[] = $sku.': Amazon product type could not be matched from the title or an existing family listing.';
                continue;
            }
            $qty = ListingManagerAmazonHydrator::shopifyQuantity($sku);
            $details = [
                'description' => $content['description'],
                'product_type' => $productType,
                'upc' => ListingManagerAmazonHydrator::upcFromCpMaster($sku, $pm),
                'images' => $content['images'],
                'image_source' => 'image_master',
            ];
            foreach ($content['bullets'] as $i => $line) {
                $details['bullet_'.($i + 1)] = $line;
            }
            $result = $publisher->publishSku($sku, $details, $content['title'], $qty);
            if (! ($result['success'] ?? false)) {
                $errors[] = $sku.': '.trim((string) ($result['message'] ?? 'Amazon publish failed.'));
                continue;
            }
            $ok[] = $sku;
            $last = $result;
        }

        if ($ok === []) {
            return [
                'success' => false,
                'message' => $errors !== [] ? implode("\n", $errors) : 'Amazon publish failed.',
            ];
        }

        $message = trim((string) ($last['message'] ?? 'Published to Amazon.'));
        if (count($ok) > 1) {
            $message = 'Published '.count($ok).' Amazon SKUs.'.($errors !== [] ? ' '.implode(' ', $errors) : '');
        } elseif ($errors !== []) {
            $message .= ' '.implode(' ', $errors);
        }

        return [
            'success' => true,
            'message' => $message,
            'goods_id' => $last['goods_id'] ?? null,
            'skus' => $ok,
        ];
    }

    /**
     * @param  array<string, mixed>  $pm
     * @return array{key: string, message: string}|null
     */
    private function amazonMasterGap(string $sku, array $pm): ?array
    {
        if (ListingManagerAmazonHydrator::titleFromProductMaster($pm, 'amazon') === '') {
            return ['key' => 'title', 'message' => 'Title missing in Title Master'];
        }
        if ($this->amazonDescriptionText($sku, $pm) === '') {
            return ['key' => 'description', 'message' => 'Description missing in Description Master'];
        }
        if ($this->amazonBullets($pm) === []) {
            return ['key' => 'bullets', 'message' => 'Bullet points missing in Bullet Points Master'];
        }
        if (ListingManagerAmazonHydrator::publishImageUrls($sku, null, 9) === []) {
            return ['key' => 'image', 'message' => 'Images missing in Image Master'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pm
     * @return array{title: string, description: string, bullets: list<string>, images: list<string>}
     */
    private function amazonMasterContent(string $sku, array $pm): array
    {
        $description = $this->amazonDescriptionText($sku, $pm);
        $limit = (int) (ListingManagerAmazonHydrator::limitsForChannel('amazon')['description'] ?? 2000);
        if ($limit > 0 && mb_strlen($description) > $limit) {
            $description = mb_substr($description, 0, $limit);
        }

        return [
            'title' => ListingManagerAmazonHydrator::titleFromProductMaster($pm, 'amazon'),
            'description' => $description,
            'bullets' => $this->amazonBullets($pm),
            'images' => ListingManagerAmazonHydrator::publishImageUrls($sku, null, 9),
        ];
    }

    /**
     * Amazon Description Master text: this SKU's amazon_metrics row, then the Description Master columns.
     *
     * @param  array<string, mixed>  $pm
     */
    private function amazonDescriptionText(string $sku, array $pm): string
    {
        $text = '';
        try {
            if (Schema::hasTable('amazon_metrics') && Schema::hasColumn('amazon_metrics', 'description_master')) {
                $text = trim((string) DB::table('amazon_metrics')->where('sku', $sku)->value('description_master'));
            }
        } catch (\Throwable) {
            $text = '';
        }
        if ($text === '') {
            foreach (['description_1500', 'product_description', 'description_html', 'description_1000', 'description_800', 'description_600'] as $col) {
                $text = trim((string) ($pm[$col] ?? ''));
                if ($text !== '') {
                    break;
                }
            }
        }
        if ($text !== '' && preg_match('/<[^>]+>/', $text)) {
            $text = html_entity_decode(strip_tags(str_ireplace(
                ['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'],
                "\n",
                $text
            )), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $pm
     * @return list<string>
     */
    private function amazonBullets(array $pm): array
    {
        $bullets = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $line = trim((string) ($pm[$col] ?? ''));
            if ($line !== '' && ! in_array($line, $bullets, true)) {
                $bullets[] = mb_substr($line, 0, 500);
            }
        }

        return $bullets;
    }

    private function groupKeyForProduct(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));

        return $parent !== '' ? $parent : trim((string) $product->sku);
    }

    private function isSheinChannel(string $channel): bool
    {
        return in_array($channel, ['shein'], true);
    }

    private function isTiktokChannel(string $channel): bool
    {
        return in_array($channel, [
            'tiktok', 'tiktokshop', 'tiktok1',
            'tiktok2', 'tiktokshop2', 'tiktoktwo',
        ], true);
    }

    private function isEbayChannel(string $channel): bool
    {
        return in_array($channel, [
            'ebay', 'ebay1', 'ebayone',
            'ebay2', 'ebaytwo',
            'ebay3', 'ebaythree',
        ], true);
    }

    private function isNeweggChannel(string $channel): bool
    {
        return in_array($channel, [
            'newegg', 'neweggb2c', 'newegg-b2c', 'newegg_b2c',
            'neweggb2b', 'newegg-b2b', 'newegg_b2b',
        ], true);
    }

    private function isMiraklChannel(string $channel): bool
    {
        return MiraklListingPublishService::isMiraklListingChannel($channel);
    }

    private function isAmazonChannel(string $channel): bool
    {
        return in_array($channel, ['amazon', 'amazonfba', 'amz', 'amzfbm'], true);
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
            'temu3' => 'Temu 3',
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
            'topdawg' => 'TOPDAWG',
            'purchasingpower' => 'Purchasing Power',
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
