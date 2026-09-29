<?php

namespace App\Services\Support;

use App\Models\ProductMaster;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Description Master "A+" content: a one-time snapshot of the live Shopify body_html (with images)
 * stored on product_master. Re-fetch only happens when explicitly forced.
 */
class ShopifyAplusContentSync
{
    public const COLUMNS = [
        'shopify_aplus_content',
        'shopify_aplus_images',
        'shopify_aplus_fetched_at',
        'shopify_aplus_fetch_error',
    ];

    public function __construct(private readonly ShopifyApiService $shopify) {}

    public static function isSchemaReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $ready = Schema::hasTable('product_master')
                && Schema::hasColumn('product_master', 'shopify_aplus_content')
                && Schema::hasColumn('product_master', 'shopify_aplus_fetched_at');
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    public static function hasContent(?ProductMaster $product): bool
    {
        return $product !== null && trim((string) ($product->shopify_aplus_content ?? '')) !== '';
    }

    /**
     * @return array<int, string>
     */
    public static function imagesOf(?ProductMaster $product): array
    {
        $raw = $product?->shopify_aplus_images;
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($v) => is_string($v) && trim($v) !== ''));
    }

    /**
     * Compact row payload used by the Description Master table (no HTML body).
     *
     * @return array{has_content: bool, fetched_at: ?string, error: ?string, chars: int}
     */
    public static function statusPayload(?ProductMaster $product): array
    {
        $html = (string) ($product?->shopify_aplus_content ?? '');
        $fetchedAt = $product?->shopify_aplus_fetched_at;
        $error = trim((string) ($product?->shopify_aplus_fetch_error ?? ''));

        return [
            'has_content' => trim($html) !== '',
            'fetched_at' => $fetchedAt ? (string) $fetchedAt : null,
            'error' => $error !== '' ? $error : null,
            'chars' => mb_strlen($html),
        ];
    }

    /**
     * Full payload for the A+ modal.
     */
    public static function contentPayload(ProductMaster $product): array
    {
        return array_merge(self::statusPayload($product), [
            'sku' => (string) $product->sku,
            'title' => (string) ($product->title150 ?? ''),
            'html' => (string) ($product->shopify_aplus_content ?? ''),
            'images' => self::imagesOf($product),
        ]);
    }

    /**
     * Fetch from Shopify and store. When $force is false and a snapshot already exists, the stored
     * copy is returned untouched (this is what makes the fetch "one time").
     *
     * @return array{success: bool, status: string, message: string, product: ProductMaster}
     */
    public function fetchAndStore(ProductMaster $product, bool $force = false): array
    {
        if (! self::isSchemaReady()) {
            return [
                'success' => false,
                'status' => 'schema_missing',
                'message' => 'A+ columns are missing on product_master. Run migrations first.',
                'product' => $product,
            ];
        }

        if (! $force && self::hasContent($product)) {
            return [
                'success' => true,
                'status' => 'cached',
                'message' => 'Using stored Shopify A+ content.',
                'product' => $product,
            ];
        }

        $sku = trim((string) $product->sku);
        try {
            $res = $this->shopify->fetchProductDescriptionHtml($sku);
        } catch (\Throwable $e) {
            $res = ['success' => false, 'message' => $e->getMessage()];
        }

        if (! ($res['success'] ?? false)) {
            $message = trim((string) ($res['message'] ?? 'Shopify fetch failed.')) ?: 'Shopify fetch failed.';
            $this->recordFailure($product, $message);
            Log::warning('ShopifyAplusContentSync: fetch failed', ['sku' => $sku, 'error' => $message]);

            return ['success' => false, 'status' => 'failed', 'message' => $message, 'product' => $product];
        }

        $html = trim((string) ($res['html'] ?? ''));
        $images = array_values(array_filter(array_map(
            fn ($u) => is_string($u) ? trim($u) : '',
            (array) ($res['images'] ?? [])
        )));

        if ($html === '' && $images === []) {
            $message = 'Shopify returned an empty description for this SKU.';
            $this->recordFailure($product, $message);

            return ['success' => false, 'status' => 'empty', 'message' => $message, 'product' => $product];
        }

        $encoded = json_encode($images, JSON_UNESCAPED_SLASHES);
        $update = [
            'shopify_aplus_content' => $html,
            'shopify_aplus_fetched_at' => now(),
            'shopify_aplus_fetch_error' => null,
        ];
        if (Schema::hasColumn('product_master', 'shopify_aplus_images')) {
            $update['shopify_aplus_images'] = $encoded !== false ? $encoded : null;
        }

        // Query-builder update: avoids the model's saving() hook re-computing Values for an unrelated change.
        ProductMaster::query()->whereKey($product->getKey())->update($update);
        $product->forceFill($update)->syncOriginal();

        Log::info('ShopifyAplusContentSync: stored Shopify A+ content', [
            'sku' => $sku,
            'chars' => mb_strlen($html),
            'images' => count($images),
            'forced' => $force,
            'source' => (string) ($res['source'] ?? ''),
        ]);

        return [
            'success' => true,
            'status' => $force ? 'refetched' : 'fetched',
            'message' => 'Fetched Shopify description and stored as A+ content.',
            'product' => $product,
        ];
    }

    /**
     * Persist manually edited A+ HTML (from the A+ modal editor). Images list is kept as-is.
     *
     * @return array{success: bool, status: string, message: string, product: ProductMaster}
     */
    public function saveEdited(ProductMaster $product, string $html): array
    {
        if (! self::isSchemaReady()) {
            return [
                'success' => false,
                'status' => 'schema_missing',
                'message' => 'A+ columns are missing on product_master. Run migrations first.',
                'product' => $product,
            ];
        }

        $html = trim($html);
        $update = [
            'shopify_aplus_content' => $html,
            'shopify_aplus_fetch_error' => null,
        ];
        if ($html !== '' && empty($product->shopify_aplus_fetched_at)) {
            $update['shopify_aplus_fetched_at'] = now();
        }

        ProductMaster::query()->whereKey($product->getKey())->update($update);
        $product->forceFill($update)->syncOriginal();

        Log::info('ShopifyAplusContentSync: saved edited A+ content', [
            'sku' => (string) $product->sku,
            'chars' => mb_strlen($html),
        ]);

        return [
            'success' => true,
            'status' => $html === '' ? 'cleared' : 'saved',
            'message' => $html === '' ? 'A+ content cleared.' : 'A+ content saved.',
            'product' => $product,
        ];
    }

    private function recordFailure(ProductMaster $product, string $message): void
    {
        if (! Schema::hasColumn('product_master', 'shopify_aplus_fetch_error')) {
            return;
        }
        // fetched_at stays as the last successful snapshot; the error column marks the failed attempt
        // so the backfill command can skip it until someone retries manually (or with --retry-failed).
        $update = ['shopify_aplus_fetch_error' => mb_substr($message, 0, 2000)];
        try {
            ProductMaster::query()->whereKey($product->getKey())->update($update);
            $product->forceFill($update)->syncOriginal();
        } catch (\Throwable $e) {
            Log::warning('ShopifyAplusContentSync: could not record failure', ['sku' => $product->sku, 'error' => $e->getMessage()]);
        }
    }
}
