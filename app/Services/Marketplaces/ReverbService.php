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

        $publicUrl = URL::to(Storage::disk('public')->url($path));
        if (! str_starts_with($publicUrl, 'https://') && ! str_starts_with($publicUrl, 'http://')) {
            return [
                'success' => false,
                'data' => ['path' => $path],
                'message' => 'Could not build a public URL for the image. Check APP_URL and storage symlink.',
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
}
