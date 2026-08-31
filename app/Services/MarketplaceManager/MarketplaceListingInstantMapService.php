<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressMetric;
use App\Models\BestbuyUSAListingStatus;
use App\Models\BestbuyUsaProduct;
use App\Models\DobaListingStatus;
use App\Models\DobaMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayListingStatus;
use App\Models\EbayMetric;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\FaireListingStatus;
use App\Models\FaireMetric;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\NeweggB2CListingStatus;
use App\Models\NeweggMetric;
use App\Models\PlsListingStatus;
use App\Models\PurchasingPowerProduct;
use App\Models\ReverbListingStatus;
use App\Models\ReverbMetric;
use App\Models\ReverbProduct;
use App\Models\SheinListingStatus;
use App\Models\SheinMmMetric;
use App\Models\Temu2ListingStatus;
use App\Models\Temu2Metric;
use App\Models\TemuListingStatus;
use App\Models\TemuMetric;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokShopListingStatus;
use App\Models\TiktokTwoShopListingStatus;
use App\Models\TopDawgProduct;
use App\Models\WayfairListingStatus;
use App\Models\WayfairPricingPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Instantly map a Shopify SKU to a marketplace listing (auto by SKU, or pasted marketplace id).
 */
class MarketplaceListingInstantMapService
{
    public function __construct(
        protected AmazonLinkMapSyncService $amazonLinkMap
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     needs_id?: bool,
     *     needs_sku_id?: bool,
     *     product_id?: string,
     *     sku_id?: string,
     *     source?: string,
     *     message: string,
     *     id_label: string
     * }
     */
    public function link(string $slug, string $sku, ?string $marketplaceId = null, ?string $skuId = null): array
    {
        $slug = strtolower(trim($slug));
        $sku = trim($sku);
        $marketplaceId = trim((string) $marketplaceId);
        $skuId = trim((string) $skuId);

        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.', 'id_label' => 'Marketplace ID'];
        }

        if ($slug === 'amazon') {
            return $this->amazonLinkMap->linkSku($sku, $marketplaceId !== '' ? $marketplaceId : null);
        }

        $cfg = $this->channelConfig($slug);
        if ($cfg === null) {
            return ['success' => false, 'message' => 'Instant map is not available for this marketplace.', 'id_label' => 'Marketplace ID'];
        }

        $found = $this->findExisting($cfg, $sku);
        $productId = $marketplaceId !== '' ? $marketplaceId : (string) ($found['product_id'] ?? '');
        $resolvedSkuId = $skuId !== '' ? $skuId : (string) ($found['sku_id'] ?? '');

        if (! empty($cfg['sku_only'])) {
            $persistId = ($productId !== '' && strcasecmp($productId, $sku) !== 0) ? $productId : $sku;
            $this->persist($cfg, $sku, $persistId, $resolvedSkuId);

            return [
                'success' => true,
                'product_id' => $persistId,
                'source' => $marketplaceId !== '' ? 'manual' : ((string) ($found['source'] ?? '') !== '' ? $found['source'] : 'sku'),
                'message' => 'Mapped '.$sku.' on '.$cfg['label'].'. Sync inventory to push Shopify qty.',
                'id_label' => $cfg['id_label'],
            ];
        }

        if ($productId === '' || strcasecmp($productId, $sku) === 0) {
            $needsSkuId = ! empty($cfg['needs_sku_id']);
            if (! empty($cfg['no_manual'])) {
                return [
                    'success' => false,
                    'message' => 'This SKU is not on the '.$cfg['label'].' store yet. Click Fetch new listings after it exists there.',
                    'id_label' => $cfg['id_label'],
                ];
            }

            return [
                'success' => false,
                'needs_id' => true,
                'needs_sku_id' => $needsSkuId,
                'message' => 'This SKU is not in the '.$cfg['label'].' catalog yet. Paste the '.$cfg['id_label']
                    .' to map it, or create the listing on '.$cfg['label'].' first.',
                'id_label' => $cfg['id_label'],
            ];
        }

        if (! empty($cfg['needs_sku_id']) && $resolvedSkuId === '') {
            return [
                'success' => false,
                'needs_id' => true,
                'needs_sku_id' => true,
                'product_id' => $productId,
                'message' => 'Enter the TikTok SKU ID as well as the product ID.',
                'id_label' => $cfg['id_label'],
            ];
        }

        $this->persist($cfg, $sku, $productId, $resolvedSkuId);
        $source = $marketplaceId !== '' ? 'manual' : (string) ($found['source'] ?? 'catalog');

        return [
            'success' => true,
            'product_id' => $productId,
            'sku_id' => $resolvedSkuId !== '' ? $resolvedSkuId : null,
            'source' => $source,
            'message' => 'Linked '.$sku.' to '.$cfg['label'].' '.$cfg['id_label'].' '.$productId.'.',
            'id_label' => $cfg['id_label'],
        ];
    }

    public function idLabel(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === 'amazon') {
            return 'Amazon ASIN';
        }

        return $this->channelConfig($slug)['id_label'] ?? 'Marketplace ID';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function channelConfig(string $slug): ?array
    {
        return match ($slug) {
            'ebay1' => [
                'label' => 'eBay 1',
                'id_label' => 'eBay item ID',
                'id_key' => 'item_id',
                'status_model' => EbayListingStatus::class,
                'lookups' => [
                    ['table' => 'ebay_metrics', 'sku' => 'sku', 'id' => 'item_id'],
                ],
                'writes' => [
                    ['table' => 'ebay_metrics', 'sku' => 'sku', 'id' => 'item_id', 'model' => EbayMetric::class],
                ],
            ],
            'ebay2' => [
                'label' => 'eBay 2',
                'id_label' => 'eBay item ID',
                'id_key' => 'item_id',
                'status_model' => EbayTwoListingStatus::class,
                'lookups' => [
                    ['table' => 'ebay_2_metrics', 'sku' => 'sku', 'id' => 'item_id'],
                ],
                'writes' => [
                    ['table' => 'ebay_2_metrics', 'sku' => 'sku', 'id' => 'item_id', 'model' => Ebay2Metric::class],
                ],
            ],
            'ebay3' => [
                'label' => 'eBay 3',
                'id_label' => 'eBay item ID',
                'id_key' => 'item_id',
                'status_model' => EbayThreeListingStatus::class,
                'lookups' => [
                    ['table' => 'ebay_3_metrics', 'sku' => 'sku', 'id' => 'item_id'],
                ],
                'writes' => [
                    ['table' => 'ebay_3_metrics', 'sku' => 'sku', 'id' => 'item_id', 'model' => Ebay3Metric::class],
                ],
            ],
            'temu' => [
                'label' => 'Temu',
                'id_label' => 'Temu goods ID',
                'id_key' => 'goods_id',
                'status_model' => TemuListingStatus::class,
                'lookups' => [
                    ['table' => 'temu_metrics', 'sku' => 'sku', 'id' => 'goods_id'],
                ],
                'writes' => [
                    ['table' => 'temu_metrics', 'sku' => 'sku', 'id' => 'goods_id', 'model' => TemuMetric::class],
                ],
            ],
            'temu2' => [
                'label' => 'Temu 2',
                'id_label' => 'Temu goods ID',
                'id_key' => 'goods_id',
                'status_model' => Temu2ListingStatus::class,
                'lookups' => [
                    ['table' => 'temu2_metrics', 'sku' => 'sku', 'id' => 'goods_id'],
                ],
                'writes' => [
                    ['table' => 'temu2_metrics', 'sku' => 'sku', 'id' => 'goods_id', 'model' => Temu2Metric::class],
                ],
            ],
            'reverb' => [
                'label' => 'Reverb',
                'id_label' => 'Reverb listing ID',
                'id_key' => 'listing_id',
                'status_model' => ReverbListingStatus::class,
                'lookups' => [
                    ['table' => 'reverb_metric', 'sku' => 'sku', 'id' => 'product_id'],
                    ['table' => 'reverb_products', 'sku' => 'sku', 'id' => 'reverb_listing_id'],
                ],
                'writes' => [
                    ['table' => 'reverb_metric', 'sku' => 'sku', 'id' => 'product_id', 'model' => ReverbMetric::class],
                    ['table' => 'reverb_products', 'sku' => 'sku', 'id' => 'reverb_listing_id', 'model' => ReverbProduct::class],
                ],
            ],
            'shein' => [
                'label' => 'Shein',
                'id_label' => 'Shein product ID',
                'id_key' => 'product_id',
                'status_model' => SheinListingStatus::class,
                'lookups' => [
                    ['table' => 'shein_metric', 'sku' => 'sku', 'id' => 'product_id'],
                    ['table' => 'shein_listing_statuses', 'sku' => 'sku', 'id' => null],
                ],
                'writes' => [
                    ['table' => 'shein_metric', 'sku' => 'sku', 'id' => 'product_id', 'model' => SheinMmMetric::class],
                ],
            ],
            'faire' => [
                'label' => 'Faire',
                'id_label' => 'Faire product ID',
                'id_key' => 'product_id',
                'status_model' => FaireListingStatus::class,
                'lookups' => [
                    ['table' => 'faire_metric', 'sku' => 'sku', 'id' => 'product_id'],
                ],
                'writes' => [
                    ['table' => 'faire_metric', 'sku' => 'sku', 'id' => 'product_id', 'model' => FaireMetric::class],
                ],
            ],
            'doba' => [
                'label' => 'Doba',
                'id_label' => 'Doba item ID',
                'id_key' => 'item_id',
                'status_model' => DobaListingStatus::class,
                'sku_only' => true,
                'lookups' => [
                    ['table' => 'doba_metrics', 'sku' => 'sku', 'id' => 'item_id'],
                ],
                'writes' => [
                    ['table' => 'doba_metrics', 'sku' => 'sku', 'id' => 'item_id', 'model' => DobaMetric::class],
                ],
            ],
            'wayfair' => [
                'label' => 'Wayfair',
                'id_label' => 'Wayfair product ID',
                'id_key' => 'product_id',
                'status_model' => WayfairListingStatus::class,
                'sku_only' => true,
                'lookups' => [
                    ['table' => 'wayfair_pricing_prices', 'sku' => 'sku', 'id' => 'sku'],
                ],
                'writes' => [
                    ['table' => 'wayfair_pricing_prices', 'sku' => 'sku', 'id' => null, 'model' => WayfairPricingPrice::class],
                ],
            ],
            'topdawg' => [
                'label' => 'TopDawg',
                'id_label' => 'TopDawg listing ID',
                'id_key' => 'listing_id',
                'status_model' => null,
                'lookups' => [
                    ['table' => 'topdawg_products', 'sku' => 'sku', 'id' => 'topdawg_listing_id'],
                    ['table' => 'topdawg_products', 'sku' => 'sku', 'id' => 'tdid'],
                ],
                'writes' => [
                    ['table' => 'topdawg_products', 'sku' => 'sku', 'id' => 'topdawg_listing_id', 'model' => TopDawgProduct::class],
                ],
            ],
            'tiktok' => [
                'label' => 'TikTok Shop',
                'id_label' => 'TikTok product ID',
                'id_key' => 'product_id',
                'needs_sku_id' => true,
                'status_model' => TiktokShopListingStatus::class,
                'lookups' => [
                    ['table' => 'tiktok_products', 'sku' => 'sku', 'id' => 'product_id', 'sku_id' => 'sku_id'],
                ],
                'writes' => [
                    ['table' => 'tiktok_products', 'sku' => 'sku', 'id' => 'product_id', 'sku_id' => 'sku_id', 'model' => TikTokProduct::class],
                ],
            ],
            'tiktok2' => [
                'label' => 'TikTok 2',
                'id_label' => 'TikTok product ID',
                'id_key' => 'product_id',
                'needs_sku_id' => true,
                'status_model' => TiktokTwoShopListingStatus::class,
                'lookups' => [
                    ['table' => 'tiktok_products_two', 'sku' => 'sku', 'id' => 'product_id', 'sku_id' => 'sku_id'],
                ],
                'writes' => [
                    ['table' => 'tiktok_products_two', 'sku' => 'sku', 'id' => 'product_id', 'sku_id' => 'sku_id', 'model' => TikTokProductTwo::class],
                ],
            ],
            'alibaba' => [
                'label' => 'Alibaba',
                'id_label' => 'Alibaba product ID',
                'id_key' => 'product_id',
                'status_model' => null,
                'lookups' => [
                    ['table' => 'alibaba_metrics', 'sku' => 'sku', 'id' => 'product_id'],
                ],
                'writes' => [
                    ['table' => 'alibaba_metrics', 'sku' => 'sku', 'id' => 'product_id', 'model' => AlibabaMetric::class],
                ],
            ],
            'aliexpress' => [
                'label' => 'AliExpress',
                'id_label' => 'AliExpress product ID',
                'id_key' => 'product_id',
                'status_model' => AliexpressListingStatus::class,
                'lookups' => [
                    ['table' => 'aliexpress_metric', 'sku' => 'sku', 'id' => 'product_id'],
                ],
                'writes' => [
                    ['table' => 'aliexpress_metric', 'sku' => 'sku', 'id' => 'product_id', 'model' => AliexpressMetric::class],
                ],
            ],
            'newegg' => [
                'label' => 'Newegg',
                'id_label' => 'Newegg item ID',
                'id_key' => 'product_id',
                'status_model' => NeweggB2CListingStatus::class,
                'lookups' => [
                    ['table' => 'newegg_metric', 'sku' => 'sku', 'id' => 'product_id'],
                ],
                'writes' => [
                    ['table' => 'newegg_metric', 'sku' => 'sku', 'id' => 'product_id', 'model' => NeweggMetric::class],
                ],
            ],
            'bestbuy' => [
                'label' => 'Best Buy',
                'id_label' => 'Best Buy product ID',
                'id_key' => 'product_id',
                'status_model' => BestbuyUSAListingStatus::class,
                'sku_only' => true,
                'lookups' => [
                    ['table' => 'bestbuy_usa_products', 'sku' => 'sku', 'id' => 'sku'],
                ],
                'writes' => [
                    ['table' => 'bestbuy_usa_products', 'sku' => 'sku', 'id' => null, 'model' => BestbuyUsaProduct::class],
                ],
            ],
            'macy' => [
                'label' => "Macy's",
                'id_label' => "Macy's product ID",
                'id_key' => 'product_id',
                'status_model' => MacysListingStatus::class,
                'sku_only' => true,
                'lookups' => [
                    ['table' => 'macy_products', 'sku' => 'sku', 'id' => 'sku'],
                ],
                'writes' => [
                    ['table' => 'macy_products', 'sku' => 'sku', 'id' => null, 'model' => MacyProduct::class],
                ],
            ],
            'purchasingpower' => [
                'label' => 'Purchasing Power',
                'id_label' => 'Product ID',
                'id_key' => 'product_id',
                'status_model' => null,
                'sku_only' => true,
                'lookups' => [
                    ['table' => 'purchasing_power_products', 'sku' => 'sku', 'id' => 'sku'],
                ],
                'writes' => [
                    ['table' => 'purchasing_power_products', 'sku' => 'sku', 'id' => null, 'model' => PurchasingPowerProduct::class],
                ],
            ],
            'pls' => [
                'label' => 'PLS',
                'id_label' => 'PLS SKU',
                'id_key' => 'sku',
                'status_model' => PlsListingStatus::class,
                'no_manual' => true,
                'lookups' => [
                    ['table' => 'shopify_catalog_variants', 'sku' => 'sku', 'id' => 'sku', 'store' => 'pls'],
                ],
                'writes' => [],
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{product_id: string, sku_id: string, source: string}
     */
    protected function findExisting(array $cfg, string $sku): array
    {
        $out = ['product_id' => '', 'sku_id' => '', 'source' => ''];

        foreach ($cfg['lookups'] ?? [] as $lookup) {
            $table = (string) ($lookup['table'] ?? '');
            $skuCol = (string) ($lookup['sku'] ?? 'sku');
            $idCol = $lookup['id'] ?? null;
            if ($table === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuCol)) {
                continue;
            }

            $query = DB::table($table)->where(function ($q) use ($skuCol, $sku) {
                $q->where($skuCol, $sku)
                    ->orWhereRaw('UPPER(TRIM(`'.$skuCol.'`)) = ?', [strtoupper($sku)]);
            });
            if (! empty($lookup['store']) && Schema::hasColumn($table, 'store')) {
                $query->where('store', $lookup['store']);
            }

            $row = $query->orderByDesc('id')->first();
            if (! $row) {
                continue;
            }

            $productId = '';
            if (is_string($idCol) && $idCol !== '' && Schema::hasColumn($table, $idCol)) {
                $productId = trim((string) ($row->{$idCol} ?? ''));
            }
            $skuIdCol = (string) ($lookup['sku_id'] ?? '');
            if ($skuIdCol !== '' && Schema::hasColumn($table, $skuIdCol)) {
                $out['sku_id'] = trim((string) ($row->{$skuIdCol} ?? ''));
            }

            if ($productId !== '' && strcasecmp($productId, $sku) !== 0) {
                $out['product_id'] = $productId;
                $out['source'] = $table;

                return $out;
            }

            if (! empty($cfg['sku_only']) || $table === 'shopify_catalog_variants') {
                $out['product_id'] = $productId !== '' ? $productId : $sku;
                $out['source'] = $table;

                return $out;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    protected function persist(array $cfg, string $sku, string $productId, string $skuId = ''): void
    {
        foreach ($cfg['writes'] ?? [] as $write) {
            $this->upsertMetricRow($write, $sku, $productId, $skuId);
        }

        $statusModel = $cfg['status_model'] ?? null;
        if (is_string($statusModel) && class_exists($statusModel)) {
            $this->upsertListingStatus($statusModel, $sku, $productId, (string) ($cfg['id_key'] ?? 'product_id'), $skuId);
        }
    }

    /**
     * @param  array<string, mixed>  $write
     */
    protected function upsertMetricRow(array $write, string $sku, string $productId, string $skuId = ''): void
    {
        $table = (string) ($write['table'] ?? '');
        $skuCol = (string) ($write['sku'] ?? 'sku');
        $idCol = $write['id'] ?? null;
        if ($table === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuCol)) {
            return;
        }

        if ($table === 'wayfair_pricing_prices') {
            WayfairPricingPrice::upsertBySku($sku);

            return;
        }

        $payload = [];
        if (is_string($idCol) && $idCol !== '' && Schema::hasColumn($table, $idCol) && $productId !== '') {
            $payload[$idCol] = $productId;
        }
        $skuIdCol = (string) ($write['sku_id'] ?? '');
        if ($skuIdCol !== '' && $skuId !== '' && Schema::hasColumn($table, $skuIdCol)) {
            $payload[$skuIdCol] = $skuId;
        }

        $existing = DB::table($table)
            ->where(function ($q) use ($skuCol, $sku) {
                $q->where($skuCol, $sku)
                    ->orWhereRaw('UPPER(TRIM(`'.$skuCol.'`)) = ?', [strtoupper($sku)]);
            })
            ->orderByDesc('id')
            ->first();

        try {
            if ($existing) {
                if ($payload !== []) {
                    if (Schema::hasColumn($table, 'updated_at')) {
                        $payload['updated_at'] = now();
                    }
                    DB::table($table)->where('id', $existing->id)->update($payload);
                }

                return;
            }

            $insert = array_merge([$skuCol => $sku], $payload);
            if (Schema::hasColumn($table, 'created_at')) {
                $insert['created_at'] = now();
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $insert['updated_at'] = now();
            }
            DB::table($table)->insert($insert);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceListingInstantMapService: metric upsert failed', [
                'table' => $table,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function upsertListingStatus(string $modelClass, string $sku, string $productId, string $idKey, string $skuId = ''): void
    {
        try {
            if ($modelClass === WayfairListingStatus::class) {
                $existing = WayfairListingStatus::query()
                    ->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->first();
                $value = is_array($existing?->value) ? $existing->value : [];
                $value[$idKey] = $productId;
                $value['product_id'] = $productId;
                $value['listed'] = $value['listed'] ?? 'Listed';
                $value['linked_at'] = now()->toDateTimeString();
                WayfairListingStatus::upsertBySku($sku, $value);

                return;
            }

            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $modelClass;
            $query = $model->newQuery()
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            $row = $query->first();
            $value = is_array($row?->value ?? null) ? $row->value : [];
            $value[$idKey] = $productId;
            $value['product_id'] = $productId;
            if ($skuId !== '') {
                $value['sku_id'] = $skuId;
            }
            $value['listed'] = $value['listed'] ?? 'Listed';
            $value['linked_at'] = now()->toDateTimeString();

            if ($row) {
                $row->sku = $sku;
                $row->value = $value;
                $row->save();
            } else {
                $modelClass::query()->create([
                    'sku' => $sku,
                    'value' => $value,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceListingInstantMapService: listing status upsert failed', [
                'model' => $modelClass,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push Shopify qty for one newly linked SKU using MarketplaceLiveInventoryRules
     * (percent / max cap / never invent stock). Explicit Link does not require the
     * bulk inventory_sync setting to be on.
     *
     * @return array{success: bool, updated: int, failed: int, skipped: int, message: string}
     */
    public function pushInventory(string $slug, string $sku): array
    {
        $sku = trim($sku);
        $empty = ['success' => false, 'updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'SKU missing.'];
        if ($sku === '') {
            return $empty;
        }

        try {
            $result = match (strtolower($slug)) {
                'amazon' => app(AmazonInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'ebay1' => app(Ebay1InventorySyncService::class)->syncSkusFromShopify([$sku]),
                'ebay2' => app(Ebay2InventorySyncService::class)->syncSkusFromShopify([$sku]),
                'ebay3' => app(Ebay3InventorySyncService::class)->syncSkusFromShopify([$sku]),
                'temu' => app(TemuInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'temu2' => app(Temu2InventorySyncService::class)->syncSkusFromShopify([$sku]),
                'reverb' => app(ReverbInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'shein' => app(SheinInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'faire' => app(FaireInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'doba' => app(DobaInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'wayfair' => app(WayfairInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'topdawg' => app(TopDawgInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'tiktok' => app(TikTokInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'tiktok2' => app(TikTok2InventorySyncService::class)->syncSkusFromShopify([$sku]),
                'alibaba' => app(AlibabaInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'aliexpress' => app(AliexpressInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'newegg' => app(NeweggInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'bestbuy' => app(BestBuyInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'macy' => app(MacyInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'purchasingpower' => app(PurchasingPowerInventorySyncService::class)->syncSkusFromShopify([$sku]),
                'pls' => app(PlsInventorySyncService::class)->syncSkusFromShopify([$sku]),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('MarketplaceListingInstantMapService: inventory push failed', [
                'slug' => $slug,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'updated' => 0,
                'failed' => 1,
                'skipped' => 0,
                'message' => $e->getMessage(),
            ];
        }

        if (! is_array($result)) {
            return [
                'success' => false,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory push is not available for this marketplace.',
            ];
        }

        $updated = (int) ($result['updated'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);

        return [
            'success' => $updated > 0 || $failed === 0,
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => (string) ($result['message'] ?? 'Inventory push finished.'),
        ];
    }
}
