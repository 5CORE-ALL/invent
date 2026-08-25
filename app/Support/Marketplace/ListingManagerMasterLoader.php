<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pull listing fields from Product Masters (Title, Image, Description, Reverb, Video, Bullets).
 */
class ListingManagerMasterLoader
{
    /**
     * @return array{
     *   success: bool,
     *   message: string,
     *   source: string,
     *   title?: string,
     *   description?: string,
     *   images?: list<string>,
     *   videos?: list<string>,
     *   bullets?: list<string>,
     *   upc?: string,
     *   brand?: string,
     *   manufacturer?: string,
     *   price?: float|null,
     *   quantity?: int|null,
     *   make?: string,
     *   model?: string,
     *   finish?: string,
     *   year?: string,
     *   condition_name?: string,
     *   shipping_profile_id?: string,
     *   package_length?: string,
     *   package_width?: string,
     *   package_height?: string,
     *   package_weight_lb?: string,
     *   package_weight_oz?: string
     * }
     */
    public static function load(string $sku, string $source, ?string $channelName = null): array
    {
        $sku = trim($sku);
        $source = strtolower(trim($source));
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.', 'source' => $source];
        }

        return match ($source) {
            'title', 'title_master' => self::title($sku, $channelName),
            'description', 'description_master' => self::description($sku),
            'images', 'image_master' => self::images($sku),
            'videos', 'video_master' => self::videos($sku),
            'bullets', 'bullet_points' => self::bullets($sku),
            'identifiers', 'product_master' => self::identifiers($sku),
            'pricing' => self::pricing($sku),
            'package' => self::package($sku),
            'reverb', 'reverb_listing_master' => self::reverb($sku),
            default => ['success' => false, 'message' => 'Unknown master source.', 'source' => $source],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function title(string $sku, ?string $channelName): array
    {
        $pm = self::productMaster($sku);
        $limit = (int) (ListingManagerAmazonHydrator::limitsForChannel($channelName)['title'] ?? 80);
        $candidates = $limit <= 80
            ? ['title80', 'title60', 'title100', 'title150']
            : ($limit <= 100 ? ['title100', 'title80', 'title150', 'title60'] : ['title150', 'title100', 'title80', 'title60']);
        $title = '';
        foreach ($candidates as $col) {
            $title = trim((string) ($pm[$col] ?? ''));
            if ($title !== '') {
                break;
            }
        }
        if ($title === '') {
            return ['success' => false, 'message' => 'No title found on Title Master for this SKU.', 'source' => 'title'];
        }

        return [
            'success' => true,
            'message' => 'Title loaded from Title Master.',
            'source' => 'title',
            'title' => $title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function description(string $sku): array
    {
        $html = ListingManagerAmazonHydrator::descriptionMaster($sku);
        if ($html === '') {
            $pm = self::productMaster($sku);
            $html = trim((string) ($pm['description_html'] ?? $pm['description_1500'] ?? $pm['product_description'] ?? ''));
        }
        if ($html === '') {
            return ['success' => false, 'message' => 'No description found on Description Master.', 'source' => 'description'];
        }

        return [
            'success' => true,
            'message' => 'Description loaded from Description Master.',
            'source' => 'description',
            'description' => $html,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function images(string $sku): array
    {
        $images = ListingManagerAmazonHydrator::imageMasterUrls($sku);
        if ($images === []) {
            return [
                'success' => false,
                'message' => 'No images found on Image Master for this SKU. Add photos on /image-master, then try again.',
                'source' => 'images',
                'images' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Loaded '.count($images).' image(s) from Image Master.',
            'source' => 'images',
            'images' => $images,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function videos(string $sku): array
    {
        $videos = [];
        if (Schema::hasTable('product_videos')) {
            $cols = ['video_path'];
            if (Schema::hasColumn('product_videos', 'cdn_url')) {
                $cols[] = 'cdn_url';
            }
            $rows = DB::table('product_videos')->where('sku', $sku)->orderBy('id')->get($cols);
            foreach ($rows as $row) {
                $url = Schema::hasColumn('product_videos', 'cdn_url')
                    ? trim((string) ($row->cdn_url ?? ''))
                    : '';
                if ($url === '') {
                    $path = trim((string) ($row->video_path ?? ''));
                    if ($path !== '') {
                        $url = str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));
                    }
                }
                if ($url !== '' && ! in_array($url, $videos, true)) {
                    $videos[] = $url;
                }
            }
        }

        $pm = self::productMaster($sku);
        foreach ([
            'video_product_overview', 'video_unboxing', 'video_how_to', 'video_setup',
            'video_troubleshooting', 'video_brand_story', 'video_product_benefits',
            'video1', 'video2', 'video3', 'video4', 'video5',
        ] as $col) {
            $url = trim((string) ($pm[$col] ?? ''));
            if ($url !== '' && ! in_array($url, $videos, true)) {
                $videos[] = $url;
            }
        }

        if ($videos === []) {
            return ['success' => false, 'message' => 'No videos found on Video Master for this SKU.', 'source' => 'videos', 'videos' => []];
        }

        return [
            'success' => true,
            'message' => 'Loaded '.count($videos).' video(s) from Video Master.',
            'source' => 'videos',
            'videos' => $videos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function bullets(string $sku): array
    {
        $pm = self::productMaster($sku);
        $bullets = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $b = trim((string) ($pm[$col] ?? ''));
            if ($b !== '') {
                $bullets[] = $b;
            }
        }
        if ($bullets === []) {
            return ['success' => false, 'message' => 'No bullet points found. Add them on /bullet-points.', 'source' => 'bullets', 'bullets' => []];
        }

        return [
            'success' => true,
            'message' => 'Loaded '.count($bullets).' bullet(s) from Bullet Points Master.',
            'source' => 'bullets',
            'bullets' => $bullets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function identifiers(string $sku): array
    {
        $pm = self::productMaster($sku);
        $values = self::values($pm);
        $upc = ListingManagerAmazonHydrator::upcFromCpMaster($sku, $pm);
        if ($upc === '') {
            $upc = trim((string) ($pm['upc'] ?? $pm['barcode'] ?? $values['upc'] ?? $values['gtin'] ?? ''));
        }
        $brand = trim((string) ($pm['brand'] ?? $values['brand'] ?? ''));
        $manufacturer = trim((string) ($pm['manufacturer'] ?? $values['manufacturer'] ?? $brand));
        if ($upc === '' && $brand === '') {
            return ['success' => false, 'message' => 'No identifier fields found on Product Master.', 'source' => 'identifiers'];
        }

        return [
            'success' => true,
            'message' => 'Identifiers loaded from Product Master.',
            'source' => 'identifiers',
            'upc' => $upc,
            'brand' => $brand !== '' ? $brand : '5 Core Inc',
            'manufacturer' => $manufacturer !== '' ? $manufacturer : '5 Core Inc',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pricing(string $sku): array
    {
        $pm = self::productMaster($sku);
        $values = self::values($pm);
        $price = null;
        foreach (['lp', 'price', 'map'] as $k) {
            if (isset($values[$k]) && is_numeric($values[$k]) && (float) $values[$k] > 0) {
                $price = (float) $values[$k];
                if ($k === 'lp' && isset($values['ship']) && is_numeric($values['ship'])) {
                    $price += (float) $values['ship'];
                }
                break;
            }
        }
        $qty = ListingManagerAmazonHydrator::shopifyQuantity($sku, false);

        if ($price === null && $qty === null) {
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            $price = $hydrated['price'];
            $qty = $hydrated['quantity'];
        }

        if ($price === null && $qty === null) {
            return ['success' => false, 'message' => 'No price or quantity found on Product Master / Shopify.', 'source' => 'pricing'];
        }

        return [
            'success' => true,
            'message' => 'Price and stock loaded from Product Master.',
            'source' => 'pricing',
            'price' => $price,
            'quantity' => $qty,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function package(string $sku): array
    {
        $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
        $has = trim((string) ($hydrated['package_length'] ?? '')) !== ''
            || trim((string) ($hydrated['package_weight_lb'] ?? '')) !== ''
            || trim((string) ($hydrated['package_weight_oz'] ?? '')) !== '';
        if (! $has) {
            return ['success' => false, 'message' => 'No package size or weight found on Product Master.', 'source' => 'package'];
        }

        return [
            'success' => true,
            'message' => 'Package details loaded from Product Master.',
            'source' => 'package',
            'package_length' => $hydrated['package_length'] ?? '',
            'package_width' => $hydrated['package_width'] ?? '',
            'package_height' => $hydrated['package_height'] ?? '',
            'package_weight_lb' => $hydrated['package_weight_lb'] ?? '',
            'package_weight_oz' => $hydrated['package_weight_oz'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reverb(string $sku): array
    {
        $pm = self::productMaster($sku);
        $values = self::values($pm);
        $payload = [
            'success' => true,
            'message' => 'Reverb details loaded from Reverb Listing Master.',
            'source' => 'reverb',
            'make' => trim((string) ($pm['reverb_make'] ?? $values['brand'] ?? '')),
            'model' => trim((string) ($pm['reverb_model'] ?? $sku)),
            'finish' => trim((string) ($pm['reverb_finish'] ?? '')),
            'year' => trim((string) ($pm['reverb_year'] ?? '')),
            'condition_name' => trim((string) ($pm['reverb_condition'] ?? $values['condition'] ?? '')),
            'shipping_profile_id' => trim((string) ($pm['reverb_shipping_profile_id'] ?? '')),
        ];
        if ($payload['make'] === '' && $payload['shipping_profile_id'] === '' && $payload['condition_name'] === '') {
            return ['success' => false, 'message' => 'No Reverb Listing Master fields found for this SKU.', 'source' => 'reverb'];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productMaster(string $sku): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }
        $row = DB::table('product_master')->where('sku', $sku)->first();

        return $row ? (array) $row : [];
    }

    /**
     * @param  array<string, mixed>  $pm
     * @return array<string, mixed>
     */
    private static function values(array $pm): array
    {
        $values = $pm['Values'] ?? $pm['values'] ?? [];
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        return is_array($values) ? $values : [];
    }
}
