<?php

namespace App\Services\Marketplaces;

use App\Models\SkuImage;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ReverbService implements MarketplaceInterface
{
    public function __construct(
        protected ReverbApiService $reverbApi
    ) {}

    /**
     * Pushes the image to Reverb by public URL: finds the listing for the product SKU, fetches current
     * gallery URLs, appends this file’s URL, then PUTs the listing (Reverb fetches the image from your APP_URL).
     *
     * Listing resolution matches Title Master: both use {@see \App\Services\ReverbApiService::getListingIdBySku}
     * (reverb_products / my/listings). Title Master uses {@see \App\Services\ReverbApiService::updateTitle} (PUT title only);
     * this flow uses {@see \App\Services\ReverbApiService::appendImageUrlToListingBySku} (merge + PUT photos).
     *
     * @return array{success: bool, data: array<string, mixed>, message: string}
     */
    public function uploadImage(SkuImage $image): array
    {
        $image->loadMissing('product');
        $product = $image->product;
        if (! $product) {
            return [
                'success' => false,
                'data' => [],
                'message' => 'Product not found for this image.',
            ];
        }

        $sku = trim((string) ($product->sku ?? ''));
        if ($sku === '') {
            return [
                'success' => false,
                'data' => [],
                'message' => 'Product has no SKU; Reverb listings are matched by SKU.',
            ];
        }

        $path = (string) ($image->file_path ?? '');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return [
                'success' => false,
                'data' => [],
                'message' => 'Image file is missing in storage (public disk).',
            ];
        }

        $publicUrl = $this->publicUrlForStoragePath($path);
        if (! str_starts_with($publicUrl, 'https://') && ! str_starts_with($publicUrl, 'http://')) {
            return [
                'success' => false,
                'data' => ['path' => $path],
                'message' => 'Could not build a public URL for the image. Check APP_URL, php artisan storage:link, and optional REVERB_SKU_IMAGE_PUBLIC_BASE_URL.',
            ];
        }

        $host = strtolower((string) (parse_url($publicUrl, PHP_URL_HOST) ?? ''));
        $isNonRoutableHost = $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.local')
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.');
        if ($isNonRoutableHost) {
            return [
                'success' => false,
                'data' => [
                    'image_url' => $publicUrl,
                    'path' => $path,
                    'hint' => 'In .env set REVERB_SKU_IMAGE_PUBLIC_BASE_URL=https://YOUR-PUBLIC-SITE.com (no trailing slash), same host where /storage/... works from the internet. Or set APP_URL to that HTTPS URL. Then: php artisan config:clear. Title Master only sends text in the API; Reverb must download each image URL.',
                ],
                'message' => 'Reverb cannot download images from '.$host.' (not reachable from the internet).',
            ];
        }

        if (str_starts_with($publicUrl, 'http://')) {
            Log::warning('Reverb image push uses HTTP; Reverb may require a publicly accessible HTTPS image URL.');
        }

        $result = $this->reverbApi->appendImageUrlToListingBySku($sku, $publicUrl);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'data' => [
                'listing_id' => $result['listing_id'] ?? null,
                'sku' => $sku,
                'image_url' => $publicUrl,
            ],
            'message' => (string) ($result['message'] ?? 'Reverb image push finished.'),
        ];
    }

    /**
     * Absolute URL for a file on the public disk. Uses REVERB_SKU_IMAGE_PUBLIC_BASE_URL when set
     * so Reverb can fetch images even when APP_URL is local (title push still uses API-only data).
     */
    private function publicUrlForStoragePath(string $path): string
    {
        $base = (string) (config('services.reverb.sku_image_public_base_url') ?? '');
        $base = rtrim($base, '/');
        if ($base !== '') {
            return $base.'/storage/'.ltrim(str_replace('\\', '/', $path), '/');
        }

        return URL::to(Storage::disk('public')->url($path));
    }
}
