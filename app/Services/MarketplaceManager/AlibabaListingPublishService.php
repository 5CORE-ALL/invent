<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AlibabaApiService;
use App\Support\Marketplace\AlibabaProductSchema;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\LmpStdPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Add Missing L SKUs to Alibaba by cloning a listed sibling's product schema
 * (category and required attributes stay) and setting this SKU's title and Std Prc.
 */
class AlibabaListingPublishService
{
    public function __construct(private AlibabaApiService $api)
    {
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $overrides
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(
        array $skus,
        bool $expandSiblings = true,
        string $mode = 'variation',
        string $parentHint = '',
        ?int $categoryId = null,
        array $overrides = []
    ): array {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Alibaba API credentials missing. Set ALIBABA_APP_KEY, ALIBABA_APP_SECRET, and ALIBABA_ACCESS_TOKEN.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($expandSiblings && $mode === 'variation') {
            $skus = $this->expandMissingSiblings($skus);
        }
        if (count($skus) > 1) {
            return $this->publishEach($skus, $parentHint, $categoryId, $overrides);
        }

        return $this->publishOne($skus[0], $parentHint, $categoryId, $overrides);
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $overrides
     * @return array{success: bool, message: string, goods_id?: string|null, skus?: list<string>}
     */
    private function publishEach(array $skus, string $parentHint, ?int $categoryId, array $overrides): array
    {
        $ok = [];
        $fail = [];
        $listed = [];
        $lastId = null;
        foreach ($skus as $sku) {
            $one = $this->publishOne($sku, $parentHint, $categoryId, strcasecmp($sku, $skus[0]) === 0 ? $overrides : []);
            if ($one['success'] ?? false) {
                $ok[] = $one['message'] ?? ('Added '.$sku);
                foreach ($one['skus'] ?? [$sku] as $listedSku) {
                    $listed[] = $listedSku;
                }
                if (! empty($one['goods_id'])) {
                    $lastId = $one['goods_id'];
                }
            } else {
                $fail[] = $sku.': '.($one['message'] ?? 'Alibaba add failed');
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    private function publishOne(string $sku, string $parentHint, ?int $categoryId, array $overrides): array
    {
        $sku = trim($sku);
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $existingId = $this->listedProductId($sku);
        if ($existingId !== '') {
            return [
                'success' => true,
                'message' => $sku.' is already on Alibaba (product '.$existingId.').',
                'goods_id' => $existingId,
                'sku_id' => $existingId,
                'skus' => [$sku],
            ];
        }

        $product = $this->findProduct($sku);
        if (! $product) {
            return ['success' => false, 'message' => $sku.' was not found in CP Master.'];
        }

        $title = trim((string) ($overrides['title'] ?? ''));
        if ($title === '') {
            $title = $this->resolveTitle($product, $sku);
        }
        $title = mb_substr($title, 0, 128);
        if ($title === '') {
            return ['success' => false, 'message' => $sku.': title is missing on CP Master.'];
        }

        $price = LmpStdPrice::forSku($sku);
        if ($price === null || $price <= 0) {
            return ['success' => false, 'message' => $sku.': set Std Prc on LMP Overall before adding it to Alibaba.'];
        }

        $templateId = $this->templateProductId($product, $sku, $parentHint);
        $schema = ['success' => false, 'message' => ''];
        $catId = $categoryId !== null && $categoryId > 0 ? (string) $categoryId : '';
        if ($templateId !== '') {
            $info = $this->api->getProductInfo($templateId);
            $productData = is_array($info['data'] ?? null) ? $info['data'] : [];
            $fromProduct = AlibabaProductSchema::categoryIdFromProduct($productData);
            if ($fromProduct !== '') {
                $catId = $fromProduct;
            }
            $schema = $this->api->productSchemaXml($templateId);
        }
        if (empty($schema['success']) && $catId !== '') {
            $schema = $this->api->renderCategorySchema($catId);
        }
        if ($catId === '' ) {
            return [
                'success' => false,
                'message' => $sku.': no listed Alibaba product in this parent to copy. List one SKU from this parent on Alibaba first, or enter an Alibaba category id.',
            ];
        }
        if (empty($schema['success']) || trim((string) ($schema['xml'] ?? '')) === '') {
            return [
                'success' => false,
                'message' => $sku.': '.((string) ($schema['message'] ?? 'Alibaba schema could not be loaded.')),
            ];
        }

        $xml = AlibabaProductSchema::rewrite((string) $schema['xml'], $title, $sku, number_format($price, 2, '.', ''));
        $added = $this->api->addProductSchema($catId, $xml);
        if (empty($added['success']) || trim((string) ($added['product_id'] ?? '')) === '') {
            Log::warning('Alibaba listing add failed', [
                'sku' => $sku,
                'cat_id' => $catId,
                'template' => $templateId,
                'message' => $added['message'] ?? '',
            ]);

            return [
                'success' => false,
                'message' => $sku.': '.((string) ($added['message'] ?? 'Alibaba did not create the product.')),
            ];
        }

        $productId = trim((string) $added['product_id']);
        $this->persistListed($sku, $productId, $title, $price);
        $this->forgetListingCaches();

        $copied = $templateId !== '' ? ' Copied the category from listed product '.$templateId.'.' : '';

        return [
            'success' => true,
            'message' => 'Added '.$sku.' to Alibaba (product '.$productId.').'.$copied,
            'goods_id' => $productId,
            'sku_id' => $productId,
            'skus' => [$sku],
        ];
    }

    private function persistListed(string $sku, string $productId, string $title, float $price): void
    {
        if (! Schema::hasTable('alibaba_metrics')) {
            return;
        }

        $row = AlibabaMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        if (! $row) {
            $row = new AlibabaMetric(['sku' => $sku]);
        }
        $row->sku = $sku;
        $row->product_id = $productId;
        $row->product_name = $title;
        $row->price = $price;
        $row->save();
    }

    private function forgetListingCaches(): void
    {
        try {
            ListingChannelCounts::refreshChannelOnMissingListingPage('alibaba');
            app(AlibabaLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            Log::warning('Alibaba listing cache refresh failed', ['error' => $e->getMessage()]);
        }
    }

    private function listedProductId(string $sku): string
    {
        if (! Schema::hasTable('alibaba_metrics')) {
            return '';
        }
        $row = AlibabaMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        $id = trim((string) ($row->product_id ?? ''));
        if ($id === '' || strcasecmp($id, $sku) === 0) {
            return '';
        }

        return $id;
    }

    private function templateProductId(ProductMaster $product, string $sku, string $parentHint): string
    {
        if (! Schema::hasTable('alibaba_metrics')) {
            return '';
        }
        $parent = trim($parentHint) !== '' ? trim($parentHint) : trim((string) ($product->parent ?? ''));
        if ($parent === '') {
            return '';
        }

        $siblingSkus = [];
        ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parent)
            ->get(['sku'])
            ->each(function ($row) use (&$siblingSkus, $sku) {
                $candidate = trim((string) $row->sku);
                if ($candidate !== '' && strcasecmp($candidate, $sku) !== 0 && stripos($candidate, 'PARENT') === false) {
                    $siblingSkus[strtoupper($candidate)] = true;
                }
            });
        if ($siblingSkus === []) {
            return '';
        }

        $match = AlibabaMetric::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->get(['sku', 'product_id'])
            ->first(function ($row) use ($siblingSkus) {
                $norm = strtoupper(trim((string) $row->sku));
                $id = trim((string) $row->product_id);

                return $norm !== '' && isset($siblingSkus[$norm]) && $id !== '' && strcasecmp($id, (string) $row->sku) !== 0;
            });

        return $match ? trim((string) $match->product_id) : '';
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandMissingSiblings(array $seedSkus): array
    {
        $out = [];
        foreach ($seedSkus as $sku) {
            $product = $this->findProduct($sku);
            $parent = trim((string) ($product->parent ?? ''));
            if ($parent === '') {
                $out[] = $sku;
                continue;
            }
            ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->orderBy('sku')
                ->get(['sku'])
                ->each(function ($row) use (&$out) {
                    $candidate = trim((string) $row->sku);
                    if ($candidate !== '' && stripos($candidate, 'PARENT') === false && $this->listedProductId($candidate) === '') {
                        $out[] = $candidate;
                    }
                });
        }

        return $this->uniqueSkus($out !== [] ? $out : $seedSkus);
    }

    private function findProduct(string $sku): ?ProductMaster
    {
        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();
    }

    private function resolveTitle(ProductMaster $product, string $sku): string
    {
        foreach (['title80', 'title100', 'title150', 'title60'] as $field) {
            $title = trim((string) ($product->{$field} ?? ''));
            if ($title !== '') {
                return $title;
            }
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return trim((string) ($shopify->product_title ?? $shopify->title ?? $sku));
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
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (! isset($out[$key])) {
                $out[$key] = $sku;
            }
        }

        return array_values($out);
    }
}
