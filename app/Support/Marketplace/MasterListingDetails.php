<?php

namespace App\Support\Marketplace;

use App\Models\CategoryMaster;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CategoryMasters\ShopifyCategoryFetcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Master listing details for marketplaces that have no listing API.
 *
 * Everything comes from our masters (Title / Description / Image / Dim-Wt / Category Masters and the
 * Shopify catalog), so the sheet can be exported and completed offline, then uploaded to the marketplace.
 */
class MasterListingDetails
{
    private const LB_TO_KG = 0.45359237;

    private const IN_TO_CM = 2.54;

    /**
     * API marketplaces that still export a master listing sheet from Missing L.
     *
     * @return array<string, string> slug => label
     */
    public static function apiChannels(): array
    {
        return [
            'alibaba' => 'Alibaba',
            'aliexpress' => 'Aliexpress',
            'wayfair' => 'Wayfair',
            'topdawg' => 'TopDawg',
            'shein' => 'Shein',
        ];
    }

    public static function supports(string $channel): bool
    {
        $key = ListingChannelCounts::normalize($channel);

        return SheetListingCatalog::has($key) || isset(self::apiChannels()[$key]);
    }

    public static function label(string $channel): string
    {
        $key = ListingChannelCounts::normalize($channel);
        if (SheetListingCatalog::has($key)) {
            return SheetListingCatalog::label($key);
        }

        return self::apiChannels()[$key] ?? $channel;
    }

    public static function routePattern(): string
    {
        return SheetListingCatalog::routePattern().'|'.implode('|', array_keys(self::apiChannels()));
    }

    /**
     * CSV / table columns in order: key => header.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'sku' => 'SKU',
            'parent' => 'Parent',
            'title' => 'Title (75)',
            'bullet1' => 'Bullet 1',
            'bullet2' => 'Bullet 2',
            'bullet3' => 'Bullet 3',
            'bullet4' => 'Bullet 4',
            'bullet5' => 'Bullet 5',
            'description' => 'Description',
            'images' => 'All Image Shopify Link',
            'aplus' => 'A+',
            'aplus_html' => 'A+ HTML',
            'weight_kg' => 'Weight (kg)',
            'length_cm' => 'Length (cm)',
            'width_cm' => 'Width (cm)',
            'height_cm' => 'Height (cm)',
            'weight_lb' => 'Weight (lb)',
            'length_in' => 'Length (in)',
            'width_in' => 'Width (in)',
            'height_in' => 'Height (in)',
            'shopify_category' => 'Shopify Category',
            'shopify_category_id' => 'Shopify Category ID',
            'price' => 'Price',
            'inventory' => 'Inventory',
        ];
    }

    /**
     * Missing L SKUs of a channel: CP Master REQ with INV > 0 that are not listed there yet (same rule as the
     * listing pages and /missing-listing).
     *
     * @return list<array{sku: string, parent: string, inv: float}>
     */
    public static function missingSkus(string $channel): array
    {
        $out = [];
        foreach (AutomatedListingPage::rows($channel, false, false) as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            if ((float) ($row->INV ?? 0) <= 0) {
                continue;
            }
            if (strtoupper((string) ($row->nr_req ?? 'REQ')) === 'NR') {
                continue;
            }
            if (! empty($row->page_created) || ! empty($row->out_of_stock) || (string) ($row->listed ?? '') === 'Listed') {
                continue;
            }
            $out[] = [
                'sku' => $sku,
                'parent' => trim((string) ($row->parent ?? $row->Parent ?? '')),
                'inv' => (float) ($row->INV ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Details for a batch of SKUs, read fresh from the masters.
     * With $refreshCategory the Shopify category is asked from Shopify again; otherwise the saved one is used and
     * only SKUs that never had it looked up are fetched.
     *
     * @param  list<string>  $skus
     * @return array{rows: list<array<string, mixed>>, message: string}
     */
    public static function fetch(array $skus, bool $refreshCategory = false): array
    {
        $skus = array_values(array_unique(array_filter(array_map(static fn ($s) => trim((string) $s), $skus), static fn ($s) => $s !== '')));
        if ($skus === []) {
            return ['rows' => [], 'message' => ''];
        }

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(static fn ($row) => self::key((string) $row->sku));
        $shopify = ShopifySku::mapByProductSkus($skus);

        $message = self::syncShopifyCategories($skus, $products, $refreshCategory);
        $categories = Schema::hasTable('category_masters')
            ? CategoryMaster::query()->whereIn('sku', $skus)->get()->keyBy(static fn ($row) => self::key((string) $row->sku))
            : collect();

        $rows = [];
        foreach ($skus as $sku) {
            $product = $products->get(self::key($sku));
            $pack = [];
            try {
                $pack = ListingManagerMasterLoader::contentPack($sku);
            } catch (\Throwable $e) {
                Log::warning('MasterListingDetails content failed: '.$e->getMessage(), ['sku' => $sku]);
            }
            $shopifyRow = $shopify->get($sku);
            $category = $categories->get(self::key($sku));

            $rows[] = array_merge(
                [
                    'sku' => $sku,
                    'parent' => trim((string) ($product->parent ?? '')),
                    'title' => self::title75($pack),
                    'description' => self::description($pack),
                    'images' => implode(', ', self::images($sku, $shopifyRow)),
                    'aplus' => trim((string) ($pack['description_aplus'] ?? '')),
                    'aplus_html' => trim((string) ($pack['description_aplus'] ?? '')),
                    'shopify_category' => trim((string) ($category->category_shopify ?? '')),
                    'shopify_category_id' => trim((string) ($category->category_shopify_id ?? '')),
                    'price' => self::price($shopifyRow, $product),
                    'inventory' => self::inventory($sku, $shopifyRow),
                ],
                self::bullets($product),
                self::package($sku, $product)
            );
        }

        return ['rows' => $rows, 'message' => $message];
    }

    /**
     * Title Master 75-character title (the 60-character one only when 75 is empty; both fit a 75 limit).
     *
     * @param  array<string, mixed>  $pack
     */
    private static function title75(array $pack): string
    {
        $byKey = [];
        foreach ((array) ($pack['titles'] ?? []) as $row) {
            $byKey[(string) ($row['key'] ?? '')] = trim((string) ($row['value'] ?? ''));
        }
        foreach (['title75', 'title60'] as $key) {
            if (($byKey[$key] ?? '') !== '') {
                return $byKey[$key];
            }
        }

        return '';
    }

    /**
     * Bullet Points Master, one column per bullet (bullet1..bullet5), kept in position.
     *
     * @return array<string, string>
     */
    private static function bullets(?ProductMaster $product): array
    {
        $out = [];
        for ($i = 1; $i <= 5; $i++) {
            $out['bullet'.$i] = $product ? trim((string) ($product->getAttribute('bullet'.$i) ?? '')) : '';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    private static function description(array $pack): string
    {
        $text = trim((string) ($pack['description_text'] ?? ''));
        if ($text !== '') {
            return $text;
        }

        // No text in Description Master: use the A+ copy (text only), then the bullet points.
        $aplus = ListingManagerMasterLoader::plainText((string) ($pack['description_aplus'] ?? ''));
        if (mb_strlen($aplus) >= 40) {
            return $aplus;
        }
        $bullets = array_values(array_filter(array_map('trim', (array) ($pack['bullets'] ?? []))));
        if ($bullets !== []) {
            return implode("\n", array_map(static fn ($b) => '- '.ltrim($b, "-• \t"), $bullets));
        }

        return trim($aplus);
    }

    /**
     * Every Shopify image link we hold for the SKU (Image Master keeps the pulled Shopify photos),
     * plus the catalog image from the Shopify variant.
     *
     * @return list<string>
     */
    private static function images(string $sku, ?ShopifySku $shopifyRow): array
    {
        $urls = [];
        try {
            $urls = ListingManagerAmazonHydrator::imageMasterCurrentUrls($sku);
        } catch (\Throwable $e) {
            Log::warning('MasterListingDetails images failed: '.$e->getMessage(), ['sku' => $sku]);
        }
        $catalog = trim((string) ($shopifyRow->image_src ?? ''));
        if ($catalog !== '' && preg_match('#^https?://#i', $catalog) && ! in_array($catalog, $urls, true)) {
            array_unshift($urls, $catalog);
        }

        return array_values(array_unique(array_filter(array_map('trim', $urls))));
    }

    private static function price(?ShopifySku $shopifyRow, ?ProductMaster $product): string
    {
        if ($shopifyRow && $shopifyRow->price !== null && (float) $shopifyRow->price > 0) {
            return number_format((float) $shopifyRow->price, 2, '.', '');
        }
        $values = self::values($product);
        foreach (['price', 'map', 'sprice', 'lp'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                return number_format((float) $values[$key], 2, '.', '');
            }
        }

        return '';
    }

    private static function inventory(string $sku, ?ShopifySku $shopifyRow): string
    {
        $qty = null;
        try {
            $qty = ListingManagerAmazonHydrator::shopifyQuantity($sku, false);
        } catch (\Throwable) {
        }
        if ($qty === null && $shopifyRow) {
            $qty = (int) ($shopifyRow->available_to_sell ?? $shopifyRow->inv ?? 0);
        }

        return $qty === null ? '' : (string) max(0, (int) $qty);
    }

    /**
     * Package weight and size in both kg / cm and lb / in. The Dim/Wt Master value is used as stored when
     * both units are filled in; otherwise the other unit is converted.
     *
     * @return array<string, string>
     */
    private static function package(string $sku, ?ProductMaster $product): array
    {
        $pkg = ['length' => '', 'width' => '', 'height' => '', 'weight_lb' => '', 'weight_oz' => ''];
        try {
            $pkg = ListingManagerAmazonHydrator::dimWtPackage($sku);
        } catch (\Throwable $e) {
            Log::warning('MasterListingDetails dim/wt failed: '.$e->getMessage(), ['sku' => $sku]);
        }
        $values = self::values($product);
        $num = static function (mixed $raw): ?float {
            if (is_string($raw)) {
                $raw = str_replace(',', '', trim($raw));
            }

            return is_numeric($raw) && (float) $raw > 0 ? (float) $raw : null;
        };

        $inch = [
            'length' => $num($pkg['length'] ?? null),
            'width' => $num($pkg['width'] ?? null),
            'height' => $num($pkg['height'] ?? null),
        ];
        $lb = (float) ($pkg['weight_lb'] ?? 0) + ((float) ($pkg['weight_oz'] ?? 0) / 16);
        $lb = $lb > 0 ? $lb : null;

        $cm = [
            'length' => $num($values['l_cm'] ?? null) ?? ($inch['length'] !== null ? $inch['length'] * self::IN_TO_CM : null),
            'width' => $num($values['w_cm'] ?? null) ?? ($inch['width'] !== null ? $inch['width'] * self::IN_TO_CM : null),
            'height' => $num($values['h_cm'] ?? null) ?? ($inch['height'] !== null ? $inch['height'] * self::IN_TO_CM : null),
        ];
        $kg = $num($values['wt_act_kg'] ?? null) ?? ($lb !== null ? $lb * self::LB_TO_KG : null);
        if ($lb === null && $kg !== null) {
            $lb = $kg / self::LB_TO_KG;
        }
        foreach (['length', 'width', 'height'] as $dim) {
            if ($inch[$dim] === null && $cm[$dim] !== null) {
                $inch[$dim] = $cm[$dim] / self::IN_TO_CM;
            }
        }

        $fmt = static fn (?float $n, int $dec): string => $n === null ? '' : rtrim(rtrim(number_format($n, $dec, '.', ''), '0'), '.');

        return [
            'weight_kg' => $fmt($kg, 3),
            'length_cm' => $fmt($cm['length'], 2),
            'width_cm' => $fmt($cm['width'], 2),
            'height_cm' => $fmt($cm['height'], 2),
            'weight_lb' => $fmt($lb, 3),
            'length_in' => $fmt($inch['length'], 2),
            'width_in' => $fmt($inch['width'], 2),
            'height_in' => $fmt($inch['height'], 2),
        ];
    }

    /**
     * Brings category_masters.category_shopify up to date for these SKUs (live from Shopify).
     *
     * @param  list<string>  $skus
     * @param  \Illuminate\Support\Collection<string, ProductMaster>  $products
     */
    private static function syncShopifyCategories(array $skus, $products, bool $force): string
    {
        if (! Schema::hasTable('category_masters') || ! Schema::hasColumn('category_masters', 'category_shopify_synced_at')) {
            return '';
        }

        $done = $force
            ? collect()
            : CategoryMaster::query()->whereIn('sku', $skus)->whereNotNull('category_shopify_synced_at')->get(['sku'])
                ->keyBy(static fn ($row) => self::key((string) $row->sku));
        $todo = [];
        foreach ($skus as $sku) {
            if ($done->has(self::key($sku))) {
                continue;
            }
            $todo[] = $sku;
        }
        if ($todo === []) {
            return '';
        }

        try {
            $fetched = app(ShopifyCategoryFetcher::class)->fetch($todo);
        } catch (\Throwable $e) {
            Log::warning('MasterListingDetails Shopify category failed: '.$e->getMessage());

            return 'Shopify category could not be refreshed.';
        }
        if (! ($fetched['ok'] ?? false)) {
            return (string) ($fetched['message'] ?? 'Shopify category could not be refreshed.');
        }

        $now = now();
        foreach ($todo as $sku) {
            $hit = $fetched['found'][$sku] ?? null;
            $parent = trim((string) ($products->get(self::key($sku))->parent ?? ''));
            try {
                CategoryMaster::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'parent' => $parent !== '' ? $parent : null,
                        'category_shopify' => ($hit['category'] ?? '') !== '' ? $hit['category'] : null,
                        'category_shopify_id' => ($hit['id'] ?? '') !== '' ? $hit['id'] : null,
                        'category_shopify_synced_at' => $now,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('MasterListingDetails category save failed: '.$e->getMessage(), ['sku' => $sku]);
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(?ProductMaster $product): array
    {
        if (! $product) {
            return [];
        }
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        return is_array($values) ? $values : [];
    }

    private static function key(string $sku): string
    {
        return strtolower(trim(str_replace("\u{00a0}", ' ', $sku)));
    }
}
