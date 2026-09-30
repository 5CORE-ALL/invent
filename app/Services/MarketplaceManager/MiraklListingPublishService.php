<?php

namespace App\Services\MarketplaceManager;

use App\Models\BestbuyPriceData;
use App\Models\BestbuyUSAListingStatus;
use App\Models\BestbuyUsaProduct;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\MacysPriceData;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerListingStatus;
use App\Models\PurchasingPowerProduct;
use App\Models\ShopifySku;
use App\Services\BestBuyApiService;
use App\Services\MacysApiService;
use App\Services\PurchasingPowerApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to Macy's / Best Buy / Purchasing Power via Mirakl.
 */
class MiraklListingPublishService
{
    public function __construct(
        private MacysApiService $macys,
        private BestBuyApiService $bestbuy,
        private PurchasingPowerApiService $purchasingPower
    ) {
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(
        array $skus,
        string $channel,
        bool $expandSiblings = true,
        string $mode = 'variation',
        string $parentHint = '',
        ?string $categoryCode = null
    ): array {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $channel = $this->normalizeChannel($channel);
        $api = $this->apiFor($channel);
        $label = $this->channelLabel($channel);
        if (! $api->isConfigured()) {
            return [
                'success' => false,
                'message' => $label.' API credentials are missing. Connect the marketplace API first.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        $publishSkus = ($expandSiblings && $mode === 'variation')
            ? $this->expandToPublishableSiblings($skus, $channel)
            : $this->filterPublishable($skus, $channel);

        if ($publishSkus === []) {
            return ['success' => false, 'message' => $this->publishBlockReason($skus, $channel, $label)];
        }

        if (count($publishSkus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastId = null;
            foreach ($publishSkus as $sku) {
                $one = $this->publishSkus([$sku], $channel, false, 'single', $parentHint, $categoryCode);
                if ($one['success'] ?? false) {
                    $ok[] = $one['message'] ?? ('Published '.$sku);
                    foreach ($one['skus'] ?? [$sku] as $listedSku) {
                        $listed[] = $listedSku;
                    }
                    if (! empty($one['goods_id'])) {
                        $lastId = $one['goods_id'];
                    }
                } else {
                    $fail[] = $sku.': '.($one['message'] ?? 'Publish failed');
                }
            }

            return [
                'success' => $fail === [],
                'message' => trim(implode(' ', $ok).($fail !== [] ? ' '.implode(' ', $fail) : '')),
                'goods_id' => $lastId,
                'sku_id' => $lastId,
                'skus' => array_values(array_unique($listed)),
            ];
        }

        $sku = $publishSkus[0];
        $product = $this->findProduct($sku);
        if (! $product) {
            return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
        }

        $title = $this->resolveTitle($product, $sku);
        if ($title === '') {
            return ['success' => false, 'message' => $sku.': Title missing in Title Master'];
        }

        $price = $this->resolvePrice($sku, $product, $channel);
        if ($price === null || $price <= 0) {
            return [
                'success' => false,
                'message' => 'No price found for '.$sku.'. Set '.$label.' price or Shopify price.',
            ];
        }

        $images = $this->productImages($product, $sku);
        if ($images === []) {
            return ['success' => false, 'message' => 'No public image URL for '.$sku.'. Add an https image on CP Master (or Image Master).'];
        }

        $categoryCode = trim((string) $categoryCode);
        if ($categoryCode === '') {
            $categoryCode = $this->existingCategoryCode($sku, $channel);
        }

        $description = $this->resolveDescription($product, $title);
        $bullets = $this->resolveBullets($product);
        $inv = $this->shopifyInv($sku);

        // The picked category drives the P41 hierarchy for every push below.
        $api->setMiraklMcmHierarchyOverride($sku, $categoryCode);
        try {
            return $this->pushProduct($api, $sku, $title, $description, $bullets, $images, $price, $inv, $channel, $label, $categoryCode);
        } finally {
            $api->setMiraklMcmHierarchyOverride($sku, '');
        }
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    private function pushProduct(
        MacysApiService|BestBuyApiService|PurchasingPowerApiService $api,
        string $sku,
        string $title,
        string $description,
        string $bullets,
        array $images,
        float $price,
        int $inv,
        string $channel,
        string $label,
        string $categoryCode
    ): array {
        $titleRes = $api->updateTitle($sku, $title);
        if (empty($titleRes['success'])) {
            return [
                'success' => false,
                'message' => $titleRes['message'] ?? ($label.' title/create failed for '.$sku.'.'),
            ];
        }

        $parts = [trim((string) ($titleRes['message'] ?? ''))];
        if ($description !== '') {
            $descRes = $api->updateDescription($sku, $description, $images);
            if (empty($descRes['success'])) {
                $parts[] = trim((string) ($descRes['message'] ?? 'Description push failed'));
            }
        }
        $imgRes = $api->updateListingImages($sku, $images);
        if (empty($imgRes['success']) && method_exists($api, 'updateImages')) {
            $imgRes = $api->updateImages($sku, $images);
        }
        if (empty($imgRes['success'])) {
            $parts[] = trim((string) ($imgRes['message'] ?? 'Image push failed'));
        }
        if ($bullets !== '' && method_exists($api, 'updateBulletPoints')) {
            $api->updateBulletPoints($sku, $bullets);
        }
        try {
            $priceRes = $api->updatePrice($sku, $price);
            if (empty($priceRes['success'])) {
                $parts[] = trim((string) ($priceRes['message'] ?? 'Price will apply after the offer is live'));
            }
        } catch (\Throwable $e) {
            $parts[] = 'Price follow-up: '.$e->getMessage();
        }
        try {
            $invRes = $api->updateItemInventoryBulk([['sku' => $sku, 'quantity' => $inv]]);
            if (is_array($invRes) && isset($invRes['success']) && ! $invRes['success']) {
                $parts[] = trim((string) ($invRes['message'] ?? 'Inventory follow-up pending'));
            }
        } catch (\Throwable $e) {
            Log::warning('Mirakl publish inventory follow-up failed', [
                'sku' => $sku,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }

        $this->persistListed($sku, $title, $price, $inv, $channel, $categoryCode);
        $this->forgetListingCaches($channel);

        return [
            'success' => true,
            'message' => trim(implode(' ', array_filter($parts))) ?: ('Published '.$sku.' to '.$label.'.'),
            'goods_id' => $sku,
            'sku_id' => $sku,
            'skus' => [$sku],
        ];
    }

    /**
     * @return array{success: bool, categories: list<array{id: string, name: string, path: string}>}
     */
    public function searchListingCategories(string $q, string $channel, string $title = ''): array
    {
        $channel = $this->normalizeChannel($channel);
        $label = $this->channelLabel($channel);
        $rows = $this->categoryRowsForChannel($channel);
        if ($rows === []) {
            $api = $this->apiFor($channel);

            return [
                'success' => false,
                'categories' => [],
                'message' => $api->isConfigured()
                    ? $label.' returned no categories (Mirakl H11 /api/hierarchies). Check the MCM API key and try again.'
                    : $label.' MCM API key is not configured, so its category tree cannot be loaded.',
            ];
        }

        $needle = mb_strtolower(trim($q));
        $terms = $needle !== ''
            ? array_values(array_filter(preg_split('/\s+/', $needle) ?: []))
            : [];
        // No query yet: use the product title to suggest categories, otherwise list leaves.
        $titleTerms = $needle === '' && trim($title) !== ''
            ? array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)) ?: [], fn ($w) => mb_strlen($w) >= 4))
            : [];

        $scored = [];
        foreach ($rows as $row) {
            $hay = mb_strtolower($row['id'].' '.$row['path']);
            $score = 0;
            if ($terms !== []) {
                foreach ($terms as $term) {
                    if (! str_contains($hay, $term)) {
                        continue 2;
                    }
                }
                $score = str_contains(mb_strtolower($row['name']), $needle) ? 3 : 1;
            } elseif ($titleTerms !== []) {
                foreach ($titleTerms as $word) {
                    if (str_contains($hay, $word)) {
                        $score++;
                    }
                }
            }
            if ($row['leaf']) {
                $score += 2;
            }
            $scored[] = [$score, $row];
        }
        if ($terms === [] && $titleTerms !== []) {
            $matched = array_filter($scored, fn ($item) => $item[0] > 2);
            if ($matched !== []) {
                $scored = array_values($matched);
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0] ?: strcmp($a[1]['path'], $b[1]['path']));

        $out = [];
        foreach ($scored as [, $row]) {
            $out[] = ['id' => $row['id'], 'name' => $row['name'], 'path' => $row['path']];
            if (count($out) >= 40) {
                break;
            }
        }

        return ['success' => true, 'categories' => $out];
    }

    public static function isMiraklListingChannel(string $channel): bool
    {
        $key = ListingChannelCounts::normalize($channel);

        return in_array($key, ['macys', 'macy', 'bestbuy', 'bestbuyusa', 'purchasingpower'], true);
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandToPublishableSiblings(array $seedSkus, string $channel): array
    {
        $seeds = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $seedSkus)
            ->get();

        $parentKeys = [];
        foreach ($seeds as $product) {
            $parent = trim((string) ($product->parent ?? ''));
            $parentKeys[$parent !== '' ? $parent : trim((string) $product->sku)] = true;
        }

        $children = collect();
        foreach (array_keys($parentKeys) as $parent) {
            $group = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->orderBy('sku')
                ->get();
            if ($group->isEmpty()) {
                $group = $seeds->filter(function ($product) use ($parent) {
                    $key = trim((string) ($product->parent ?? ''));
                    if ($key === '') {
                        $key = trim((string) $product->sku);
                    }

                    return $key === $parent && stripos((string) $product->sku, 'PARENT') === false;
                })->values();
            }
            $children = $children->concat($group);
        }

        return $this->filterPublishable(
            $children->map(fn ($p) => trim((string) $p->sku))->filter()->unique()->values()->all(),
            $channel
        );
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function filterPublishable(array $skus, string $channel): array
    {
        $cfg = ChannelListingRegistry::get($channel);
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            if (trim((string) ($listedMap[strtolower($sku)] ?? '')) !== '') {
                continue;
            }
            $product = $this->findProduct($sku);
            if (! $product || $this->productImages($product, $sku) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     */
    private function publishBlockReason(array $skus, string $channel, string $label): string
    {
        $reasons = [];
        foreach ($this->uniqueSkus($skus) as $sku) {
            $product = $this->findProduct($sku);
            if (! $product) {
                $reasons[] = $sku.': not in product master';
                continue;
            }
            if ($this->productImages($product, $sku) === []) {
                $reasons[] = $sku.': no public https image';
                continue;
            }
            $reasons[] = $sku.': already listed on '.$label;
        }

        return $reasons !== []
            ? implode('; ', $reasons)
            : 'No Missing L child SKUs left to publish (already listed or missing images).';
    }

    private function persistListed(
        string $sku,
        string $title,
        float $price,
        int $inv,
        string $channel,
        string $categoryCode
    ): void {
        try {
            $statusValue = [
                'listed' => 'Listed',
                'listing_id' => $sku,
                'category_code' => $categoryCode,
                'title' => $title,
            ];
            if ($channel === 'macys' && Schema::hasTable('macy_products')) {
                $existing = MacyProduct::query()->where('sku', $sku)->first()
                    ?: MacyProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
                $payload = ['price' => $price, 'stock' => max(0, $inv), 'listing_status' => 'listed'];
                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    MacyProduct::create(array_merge(['sku' => $sku], $payload));
                }
                if (Schema::hasTable('macys_listing_statuses')) {
                    MacysListingStatus::updateOrCreate(['sku' => $sku], ['value' => $statusValue]);
                }
            } elseif ($channel === 'bestbuyusa' && Schema::hasTable('bestbuy_usa_products')) {
                $existing = BestbuyUsaProduct::query()->where('sku', $sku)->first()
                    ?: BestbuyUsaProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
                $payload = ['price' => $price, 'stock' => max(0, $inv), 'listing_status' => 'listed'];
                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    BestbuyUsaProduct::create(array_merge(['sku' => $sku], $payload));
                }
                if (Schema::hasTable('bestbuy_usa_listing_statuses')) {
                    BestbuyUSAListingStatus::updateOrCreate(['sku' => $sku], ['value' => $statusValue]);
                }
            } elseif ($channel === 'purchasingpower' && Schema::hasTable('purchasing_power_products')) {
                $existing = PurchasingPowerProduct::query()->where('sku', $sku)->first()
                    ?: PurchasingPowerProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
                $payload = ['price' => $price, 'stock' => max(0, $inv), 'listing_status' => 'listed'];
                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    PurchasingPowerProduct::create(array_merge(['sku' => $sku], $payload));
                }
                if (Schema::hasTable('purchasing_power_listing_statuses')) {
                    PurchasingPowerListingStatus::updateOrCreate(['sku' => $sku], ['value' => $statusValue]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Mirakl persist listed failed', [
                'sku' => $sku,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function forgetListingCaches(string $channel): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            Cache::forget('listing_channel_counts_v1:inv:'.$channel);
            Cache::forget('listing_channel_counts_v1:cp:'.$channel);
        } catch (\Throwable) {
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    /**
     * Operator category tree from Mirakl H11, with the codes already used on our price data
     * merged in (they may be leaves the tree call did not return).
     *
     * @return list<array{id: string, name: string, path: string, leaf: bool}>
     */
    private function categoryRowsForChannel(string $channel): array
    {
        $byCode = [];
        $hasChildren = [];
        try {
            $tree = $this->apiFor($channel)->fetchMiraklMcmHierarchies();
        } catch (\Throwable $e) {
            Log::warning('Mirakl hierarchy fetch failed', ['channel' => $channel, 'error' => $e->getMessage()]);
            $tree = [];
        }
        foreach ($tree as $node) {
            $byCode[$node['code']] = $node;
            if ($node['parent_code'] !== '') {
                $hasChildren[$node['parent_code']] = true;
            }
        }

        $pathFor = function (string $code) use ($byCode): string {
            $parts = [];
            $guard = 0;
            while ($code !== '' && isset($byCode[$code]) && $guard++ < 12) {
                array_unshift($parts, $byCode[$code]['label'] !== '' ? $byCode[$code]['label'] : $code);
                $code = $byCode[$code]['parent_code'];
            }

            return implode(' > ', $parts);
        };

        $rows = [];
        foreach ($byCode as $code => $node) {
            $name = $node['label'] !== '' ? $node['label'] : $code;
            $rows[$code] = [
                'id' => $code,
                'name' => $name,
                'path' => $pathFor($code) ?: $name,
                'leaf' => ! isset($hasChildren[$code]),
            ];
        }

        foreach ($this->priceDataCategoryRows($channel) as $row) {
            if (isset($rows[$row['id']])) {
                continue;
            }
            $rows[$row['id']] = [
                'id' => $row['id'],
                'name' => $row['name'] !== '' ? $row['name'] : $row['id'],
                'path' => $row['name'] !== '' ? $row['name'] : $row['id'],
                'leaf' => true,
            ];
        }

        return array_values($rows);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function priceDataCategoryRows(string $channel): array
    {
        if ($channel === 'purchasingpower') {
            return [];
        }
        $table = $channel === 'bestbuyusa' ? 'bestbuy_price_data' : 'macys_price_data';
        $model = $channel === 'bestbuyusa' ? BestbuyPriceData::class : MacysPriceData::class;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category_code')) {
            return [];
        }

        return $model::query()
            ->whereNotNull('category_code')
            ->where('category_code', '!=', '')
            ->selectRaw('category_code, MAX(category_label) as category_label')
            ->groupBy('category_code')
            ->orderBy('category_code')
            ->limit(400)
            ->get()
            ->map(fn ($row) => [
                'id' => trim((string) $row->category_code),
                'name' => trim((string) ($row->category_label ?? '')),
            ])
            ->all();
    }

    private function existingCategoryCode(string $sku, string $channel): string
    {
        $table = $channel === 'bestbuyusa' ? 'bestbuy_price_data' : 'macys_price_data';
        $model = $channel === 'bestbuyusa' ? BestbuyPriceData::class : MacysPriceData::class;
        if (! Schema::hasTable($table)) {
            return '';
        }
        $row = $model::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->orWhereRaw('UPPER(TRIM(product_sku)) = ?', [strtoupper($sku)])
            ->orderByDesc('id')
            ->first(['category_code']);

        return trim((string) ($row->category_code ?? ''));
    }

    private function apiFor(string $channel): MacysApiService|BestBuyApiService|PurchasingPowerApiService
    {
        return match ($channel) {
            'macys' => $this->macys,
            'purchasingpower' => $this->purchasingPower,
            default => $this->bestbuy,
        };
    }

    private function normalizeChannel(string $channel): string
    {
        $key = ListingChannelCounts::normalize($channel);

        return match ($key) {
            'macy', 'macys' => 'macys',
            'bestbuy', 'bestbuyusa' => 'bestbuyusa',
            'purchasingpower' => 'purchasingpower',
            default => $key,
        };
    }

    private function channelLabel(string $channel): string
    {
        return match ($this->normalizeChannel($channel)) {
            'macys' => "Macy's",
            'bestbuyusa' => 'Best Buy',
            'purchasingpower' => 'Purchasing Power',
            default => 'Mirakl',
        };
    }

    private function resolveTitle(ProductMaster $product, string $sku): string
    {
        foreach (['title80', 'title100', 'title150', 'title60'] as $field) {
            $title = trim((string) ($product->{$field} ?? ''));
            if ($title !== '') {
                return mb_substr($title, 0, 150);
            }
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return mb_substr(trim((string) ($shopify->product_title ?? $shopify->title ?? $product->parent ?? $sku)), 0, 150);
    }

    private function resolvePrice(string $sku, ProductMaster $product, string $channel): ?float
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        $price = (float) ($shopify->price ?? $shopify->b2c_price ?? 0);
        if ($price > 0) {
            return round($price, 2);
        }
        $values = is_array($product->Values) ? $product->Values : [];
        $lp = isset($values['lp']) && is_numeric($values['lp']) ? (float) $values['lp'] : 0.0;
        $ship = isset($values['ship']) && is_numeric($values['ship']) ? (float) $values['ship'] : 0.0;
        if ($lp > 0) {
            return round($lp + $ship, 2);
        }

        return null;
    }

    private function resolveDescription(ProductMaster $product, string $title): string
    {
        foreach (['product_description', 'description_800', 'description_600', 'description_1000', 'description_1500'] as $col) {
            $text = trim((string) ($product->{$col} ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return $title;
    }

    private function resolveBullets(ProductMaster $product): string
    {
        $lines = [];
        foreach (['bullet_1', 'bullet_2', 'bullet_3', 'bullet_4', 'bullet_5'] as $col) {
            $line = trim((string) ($product->{$col} ?? ''));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function productImages(ProductMaster $product, string $sku): array
    {
        return ListingManagerAmazonHydrator::publishImageUrls($sku, trim((string) ($product->parent ?? '')), 8);
    }

    private function shopifyInv(string $sku): int
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return max(0, (int) ($shopify->available_to_sell ?? $shopify->inv ?? 0));
    }

    private function findProduct(string $sku): ?ProductMaster
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function uniqueSkus(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && ! in_array($sku, $out, true)) {
                $out[] = $sku;
            }
        }

        return $out;
    }
}
