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

    /** A+ (HTML) description of the parent, used instead of the text description on marketplaces that accept it. */
    public const APLUS_KEY = 'variation_listing_aplus';

    /** Parent gallery (public URLs) shared by every child of the parent. */
    public const IMAGES_KEY = 'variation_listing_images';

    /** Most parent photos kept per parent. */
    public const MAX_IMAGES = 20;

    private static int $depth = 0;

    /** @var list<string> marketplace of each active variation run (innermost last) */
    private static array $channels = [];

    /** @var array<string, array{title: string, description: string, bullets: list<string>, aplus: string, images: list<string>}> */
    private static array $cache = [];

    public static function activate(string $channel = ''): void
    {
        self::$depth++;
        self::$channels[] = strtolower(trim($channel));
    }

    public static function deactivate(): void
    {
        self::$depth = max(0, self::$depth - 1);
        array_pop(self::$channels);
    }

    public static function currentChannel(): string
    {
        return self::$channels === [] ? '' : (string) end(self::$channels);
    }

    /**
     * Marketplaces whose listing description is HTML, so a parent A+ page can replace the text.
     * Amazon is not here on purpose: each Amazon child keeps its own content and images.
     */
    public static function channelSupportsAplus(string $channel): bool
    {
        return str_starts_with(strtolower(trim($channel)), 'ebay');
    }

    public static function plainText(string $html): string
    {
        $html = preg_replace('#<\s*(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', str_replace(["\r\n", "\r"], "\n", $text)) ?? $text;
        $text = preg_replace("/ ?\n ?/", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * The description a listing sends for this parent: the A+ page when one is saved and the
     * marketplace takes HTML, otherwise the text description (the A+ text when no text exists).
     *
     * @param  array{description: string, aplus: string}  $pack
     */
    public static function pickDescription(array $pack, string $channel): string
    {
        $text = (string) $pack['description'];
        $aplus = (string) $pack['aplus'];
        if ($aplus !== '') {
            if (self::channelSupportsAplus($channel)) {
                return $aplus;
            }
            if ($text === '') {
                return self::plainText($aplus);
            }
        }

        return $text;
    }

    /**
     * Parent gallery from Variation Master (public URLs), or [] when none was uploaded.
     *
     * @return list<string>
     */
    public static function parentImagesFor(ProductMaster $product): array
    {
        return self::forParent(self::parentKeyFor($product))['images'];
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
     * @return array{title: string, description: string, bullets: list<string>, aplus: string, images: list<string>}
     */
    public static function forParent(string $parent): array
    {
        $parent = ListingManagerFamily::normalizeParentKey($parent);
        if ($parent === '') {
            return ['title' => '', 'description' => '', 'bullets' => [], 'aplus' => '', 'images' => []];
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

        $images = [];
        $rawImages = $values[self::IMAGES_KEY] ?? [];
        foreach (is_array($rawImages) ? $rawImages : [] as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https?://#i', $url) && ! in_array($url, $images, true)) {
                $images[] = $url;
            }
        }

        $pack = [
            'title' => trim($title),
            'description' => $description,
            'bullets' => $bullets,
            'aplus' => trim((string) ($values[self::APLUS_KEY] ?? '')),
            'images' => $images,
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

        return self::pickDescription(self::forParent(self::parentKeyFor($product)), self::currentChannel());
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

        return self::pickDescription(self::forParent(ListingManagerFamily::parentKey($sku)), self::currentChannel());
    }

    public static function preferDescription(ProductMaster $product, string $fallback): string
    {
        return self::descriptionFor($product) ?? $fallback;
    }
}
