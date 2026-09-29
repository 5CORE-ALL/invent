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
     * A+ content must not repeat the top "5 bullet points" block (Bullet Points Master owns those).
     * Removes the Bullet Points Master block / "About Item" section, then any leading bold-label bullet
     * paragraphs, <br>-separated bullet lines, bullet lists or symbol bullets Shopify puts before the body.
     */
    public static function cleanFetchedHtml(string $html): string
    {
        $html = trim(str_replace("\xc2\xa0", ' ', $html));
        if ($html === '') {
            return '';
        }
        $raw = $html;

        // Bullet Points Master marker block, "About Item" sections and 【】 bracket bullets.
        $html = trim(ShopifyBulletPointsFormatter::removeAboutItemBlock($html));

        // Leading block-level wrappers that only open (div/section/span) — look past them but keep them.
        $open = '(?:<(?:div|section|span|font|center)\b[^>]*>\s*)*';
        $sep = '(?:-|:|–|—|\|)';
        $boldOpen = '<(?:strong|b)\b[^>]*>\s*(?:<[^>]+>\s*)*';
        $boldClose = '(?:<\/[^>]+>\s*)*<\/(?:strong|b)>';
        // A bullet = bold label + (separator inside/outside the bold tag OR a real sentence right after it).
        // A bold-only line such as <p><strong>BUILT TO LAST</strong></p> is a heading and is kept.
        $label = '(?:'
            .$boldOpen.'[^<]{2,120}?\s*'.$sep.'\s*'.$boldClose
            .'|'.$boldOpen.'[^<]{2,120}?\s*'.$boldClose.'\s*(?:<(?!\/?(?:p|div|li|h[1-6])\b)[^>]+>\s*)*'.$sep
            .'|'.$boldOpen.'[^<]{2,120}?\s*'.$boldClose.'\s*(?:<(?!\/?(?:p|div|li|h[1-6])\b)[^>]+>\s*)*[^<]{20,}'
            .')';
        $headingLabel = '(?:Highlighted\s+Features|About\s+(?:this\s+)?Item|Key\s+Features|Product\s+Highlights|Main\s+Features|Bullet\s+Points|Top\s+Features|Features)';

        $patterns = [
            // "Highlighted Features" / "Key Features" style label heading at the top (bullets follow).
            '/^'.$open.'<(?:p|h[1-6]|div)\b[^>]*>\s*(?:<[^>]+>\s*)*'.$headingLabel.'\s*:?\s*(?:<\/[^>]+>\s*)*<\/(?:p|h[1-6]|div)>\s*(?='.$open.'<(?:p|h[1-6]|div|li|ul|ol)\b[^>]*>\s*(?:<[^>]+>\s*)*(?:<(?:strong|b)\b|•|✔|✓|★|【|<li))/iu',
            // <p>/<h*>/<div>/<li> whose content starts with a bold label bullet.
            '/^'.$open.'<(?:p|h[1-6]|div|li)\b[^>]*>\s*(?:<(?:span|font|em|i|u)\b[^>]*>\s*)*'.$label.'[\s\S]*?<\/(?:p|h[1-6]|div|li)>\s*/iu',
            // Bullets as <br>-separated bold-label lines inside one paragraph (2+ lines).
            '/^'.$open.'<p\b[^>]*>\s*(?:'.$label.'[^<]*(?:<(?!br)[^>]+>[^<]*)*<br\s*\/?>\s*){2,}'.$label.'[\s\S]*?<\/p>\s*/iu',
            // Bullet list right at the top.
            '/^'.$open.'<(?:ul|ol)\b[^>]*>[\s\S]*?<\/(?:ul|ol)>\s*/iu',
            // "•", "✔", "✓", "★" symbol bullets in leading paragraphs.
            '/^'.$open.'<(?:p|div)\b[^>]*>\s*(?:<[^>]+>\s*)*(?:•|✔|✓|★|☑|►|▶|【)[\s\S]*?<\/(?:p|div)>\s*/iu',
            // Empty paragraphs left behind.
            '/^'.$open.'<p\b[^>]*>\s*(?:<br\s*\/?>|&nbsp;|\s|<[^>]+>\s*<\/[^>]+>)*<\/p>\s*/iu',
        ];

        // At most 5 bullet paragraphs (+ leftovers) are removed; stop as soon as nothing matches so the
        // real description paragraphs are never eaten.
        for ($removed = 0; $removed < 8; $removed++) {
            $before = $html;
            foreach ($patterns as $pattern) {
                $updated = preg_replace($pattern, '', $html, 1, $count);
                if ($count > 0 && is_string($updated)) {
                    $html = trim($updated);
                    break;
                }
            }
            if ($html === $before) {
                break;
            }
        }

        // Safety against over-stripping: if nothing meaningful is left, keep the original body.
        if (trim(strip_tags($html)) === '' && ! preg_match('/<img\b/i', $html)) {
            return $raw;
        }

        return trim($html);
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
                'message' => 'Using stored A+ content.',
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
            $message = trim((string) ($res['message'] ?? '')) ?: 'A+ fetch failed.';
            // The page presents this as "A+ content"; keep the source name out of user-facing text.
            $message = preg_replace('/\bShopify\b/i', 'store', $message) ?? $message;
            $this->recordFailure($product, $message);
            Log::warning('ShopifyAplusContentSync: fetch failed', ['sku' => $sku, 'error' => $message]);

            return ['success' => false, 'status' => 'failed', 'message' => $message, 'product' => $product];
        }

        $html = self::cleanFetchedHtml((string) ($res['html'] ?? ''));
        $images = array_values(array_filter(array_map(
            fn ($u) => is_string($u) ? trim($u) : '',
            (array) ($res['images'] ?? [])
        )));

        if ($html === '' && $images === []) {
            $message = 'No A+ content found for this SKU on the store.';
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
            'message' => 'Fetched and stored A+ content.',
            'product' => $product,
        ];
    }

    /**
     * Re-run the bullet cleaner on an already stored snapshot (no Shopify call). Used to rectify SKUs
     * that were stored with the full body including the top bullet block.
     *
     * @return array{changed: bool, before: int, after: int}
     */
    public function recleanStored(ProductMaster $product): array
    {
        $before = (string) ($product->shopify_aplus_content ?? '');
        if (trim($before) === '') {
            return ['changed' => false, 'before' => 0, 'after' => 0];
        }

        $after = self::cleanFetchedHtml($before);
        if ($after === trim($before)) {
            return ['changed' => false, 'before' => mb_strlen($before), 'after' => mb_strlen($after)];
        }

        $update = ['shopify_aplus_content' => $after];
        ProductMaster::query()->whereKey($product->getKey())->update($update);
        $product->forceFill($update)->syncOriginal();

        Log::info('ShopifyAplusContentSync: re-cleaned stored A+ content', [
            'sku' => (string) $product->sku,
            'before_chars' => mb_strlen($before),
            'after_chars' => mb_strlen($after),
        ]);

        return ['changed' => true, 'before' => mb_strlen($before), 'after' => mb_strlen($after)];
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
