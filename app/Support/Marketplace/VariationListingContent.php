<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;

/**
 * Title, description, and bullet points stored on the CP master PARENT row.
 * While a variation listing preview or publish is active, these fields are the
 * only source. Single listings keep using Title, Description, and Bullet masters.
 */
class VariationListingContent
{
    public const TITLE_KEY = 'variation_listing_title';

    public const DESCRIPTION_KEY = 'variation_listing_description';

    public const BULLETS_KEY = 'variation_listing_bullets';

    private static int $depth = 0;

    /** @var array<string, array{title: string, description: string, bullets: list<string>}> */
    private static array $cache = [];

    public static function activate(): void
    {
        self::$depth++;
    }

    public static function deactivate(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    public static function parentProduct(string $parent): ?ProductMaster
    {
        $sku = ListingManagerFamily::parentRowSkuFromKey($parent);
        if ($sku === null || $sku === '') {
            return null;
        }

        return ProductMaster::query()->where('sku', $sku)->first()
            ?: ProductMaster::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
    }

    public static function parentKeyFor(ProductMaster $product): string
    {
        $fromColumn = ListingManagerFamily::normalizeParentKey((string) ($product->parent ?? ''));
        if ($fromColumn !== '') {
            return $fromColumn;
        }
        if (ListingManagerFamily::isParentSku((string) $product->sku)) {
            return ListingManagerFamily::normalizeParentKey((string) $product->sku);
        }

        return ListingManagerFamily::parentKey((string) $product->sku);
    }

    /**
     * @return array{title: string, description: string, bullets: list<string>}
     */
    public static function forParent(string $parent): array
    {
        $parent = ListingManagerFamily::normalizeParentKey($parent);
        if ($parent === '') {
            return ['title' => '', 'description' => '', 'bullets' => []];
        }

        $cacheKey = strtoupper($parent);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $product = self::parentProduct($parent);
        $values = is_array($product?->Values) ? $product->Values : [];
        $bulletsRaw = $values[self::BULLETS_KEY] ?? '';
        if (is_array($bulletsRaw)) {
            $bulletsRaw = implode("\n", $bulletsRaw);
        }

        $bullets = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $bulletsRaw) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $bullets[] = $line;
            if (count($bullets) >= 10) {
                break;
            }
        }

        $title = preg_replace('/\s+/', ' ', trim((string) ($values[self::TITLE_KEY] ?? ''))) ?? '';
        $description = trim(str_replace(["\r\n", "\r"], "\n", (string) ($values[self::DESCRIPTION_KEY] ?? '')));

        $pack = [
            'title' => trim($title),
            'description' => $description,
            'bullets' => $bullets,
        ];
        self::$cache[$cacheKey] = $pack;

        return $pack;
    }

    public static function forget(?string $parent = null): void
    {
        if ($parent === null || $parent === '') {
            self::$cache = [];

            return;
        }
        unset(self::$cache[strtoupper(ListingManagerFamily::normalizeParentKey($parent))]);
    }

    public static function titleFor(ProductMaster $product): ?string
    {
        if (! self::active()) {
            return null;
        }

        return self::forParent(self::parentKeyFor($product))['title'];
    }

    public static function descriptionFor(ProductMaster $product): ?string
    {
        if (! self::active()) {
            return null;
        }

        return self::forParent(self::parentKeyFor($product))['description'];
    }

    /**
     * @return list<string>|null
     */
    public static function bulletsFor(ProductMaster $product): ?array
    {
        if (! self::active()) {
            return null;
        }

        return self::forParent(self::parentKeyFor($product))['bullets'];
    }

    public static function titleGapMessage(): string
    {
        return self::active()
            ? 'Title missing in Variation Master'
            : 'Title missing in Title Master';
    }

    public static function descriptionGapMessage(): string
    {
        return self::active()
            ? 'Description missing in Variation Master'
            : 'Description missing in Description Master';
    }

    public static function titleForSku(string $sku): ?string
    {
        if (! self::active()) {
            return null;
        }
        $product = ProductMaster::query()->where('sku', $sku)->first();
        if ($product) {
            return self::titleFor($product);
        }

        return self::forParent(ListingManagerFamily::parentKey($sku))['title'];
    }

    public static function descriptionForSku(string $sku): ?string
    {
        if (! self::active()) {
            return null;
        }
        $product = ProductMaster::query()->where('sku', $sku)->first();
        if ($product) {
            return self::descriptionFor($product);
        }

        return self::forParent(ListingManagerFamily::parentKey($sku))['description'];
    }

    public static function preferDescription(ProductMaster $product, string $fallback): string
    {
        return self::descriptionFor($product) ?? $fallback;
    }
}
