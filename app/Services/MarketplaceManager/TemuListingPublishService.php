<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\TemuPricing;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Publish Missing L SKUs to Temu 1 via Open API, including independent (single) listings.
 */
class TemuListingPublishService extends Temu2ListingPublishService
{
    public function __construct(TemuApiService $api)
    {
        $this->api = $api;
    }

    protected function shopConfigKey(): string
    {
        return 'temu';
    }

    protected function shopLabel(): string
    {
        return 'Temu';
    }

    protected function credentialsHelp(): string
    {
        return 'Set TEMU_APP_KEY, TEMU_SECRET_KEY, and TEMU_ACCESS_TOKEN.';
    }

    protected function stdPriceHelp(): string
    {
        return 'Temu 1 Analytics (/new-temuone)';
    }

    protected function pricingTable(): string
    {
        return 'temu_pricing';
    }

    protected function metricsTable(): string
    {
        return 'temu_metrics';
    }

    protected function pricingClass(): string
    {
        return TemuPricing::class;
    }

    protected function metricClass(): string
    {
        return TemuMetric::class;
    }

    protected function dataViewClass(): string
    {
        return TemuDataView::class;
    }

    protected function costTemplateCacheKey(): string
    {
        return 'temu_cost_template_id_v1';
    }

    /**
     * @return list<string>
     */
    protected function listingCountCacheKeys(): array
    {
        return ['listing_channel_counts_v1:temu'];
    }

    /**
     * Temu 1's seller token is authorized for the older local add API.
     * temu.local.goods.v2.add returns 3000032 on this shop.
     *
     * @return list<string>
     */
    protected function goodsAddTypes(): array
    {
        return [
            'bg.local.goods.add',
            'temu.local.goods.add',
            'temu.local.goods.v2.add',
        ];
    }

    /**
     * Temu 1 rejects WebP file links. Convert the photo to JPEG and upload the bytes.
     * Temu 2 keeps the fileUrl upload.
     *
     * @return array{success: bool, url?: string, message: string}
     */
    protected function uploadImageToTemu2(string $imageUrl): array
    {
        $prepared = $this->prepareTemuImage($imageUrl);
        if ($prepared['bytes'] === null) {
            return [
                'success' => false,
                'message' => 'Could not read the listing photo as a JPEG or PNG. Temu rejects WebP links.',
            ];
        }

        $encoded = base64_encode($prepared['bytes']);
        $lastMsg = '';
        $remember = function (array $data) use (&$lastMsg): bool {
            $msg = trim((string) ($data['errorMsg'] ?? $data['message'] ?? ''));
            if ($this->isIpWhitelistMessage($msg)) {
                $lastMsg = $msg;

                return true;
            }
            if ($msg !== '' && ! $this->isSkippableImageError($msg) && ! $this->isApiAccessDenied($data)) {
                $lastMsg = $msg;
            }

            return false;
        };

        foreach ($prepared['file_urls'] as $url) {
            foreach ([
                ['fileUrl' => $url, 'scalingType' => 1, 'compressionType' => 0, 'formatConversionType' => 1],
                ['fileUrl' => $url],
            ] as $params) {
                $data = $this->api->callOpenApi('bg.local.goods.image.upload', $params, 60);
                $hosted = $this->extractUploadedImageUrl($data);
                if ($hosted !== null) {
                    return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
                }
                if ($remember($data)) {
                    return ['success' => false, 'message' => $lastMsg];
                }
            }
        }

        foreach (['image', 'imageBase64', 'fileBase64'] as $field) {
            $data = $this->api->callOpenApi('bg.local.goods.image.upload', [$field => $encoded], 90);
            $hosted = $this->extractUploadedImageUrl($data);
            if ($hosted !== null) {
                return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
            }
            if ($remember($data)) {
                return ['success' => false, 'message' => $lastMsg];
            }
        }

        return [
            'success' => false,
            'message' => $lastMsg !== '' ? $lastMsg : 'Temu image upload failed.',
        ];
    }

    /**
     * @return array{bytes: ?string, file_urls: list<string>}
     */
    private function prepareTemuImage(string $imageUrl): array
    {
        $raw = $this->readImageBytes($imageUrl);
        if ($raw === null) {
            return ['bytes' => null, 'file_urls' => []];
        }
        $jpeg = $this->binaryToJpeg($raw);
        if ($jpeg === null) {
            return ['bytes' => null, 'file_urls' => []];
        }

        $fileUrls = [];
        if ($this->isTemuFetchableImageUrl($imageUrl) && $this->bytesAreJpegOrPng($raw)) {
            $fileUrls[] = $imageUrl;
        }
        $saved = $this->storeJpegForTemu($imageUrl, $jpeg);
        if ($saved !== '') {
            $fileUrls[] = $saved;
        }

        return ['bytes' => $jpeg, 'file_urls' => array_values(array_unique($fileUrls))];
    }

    private function isSkippableImageError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'type not exists')
            || str_contains($message, 'type not exist')
            || str_contains($message, '.webp');
    }

    private function isTemuFetchableImageUrl(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));

        return (bool) preg_match('/\.(jpe?g|png)$/', $path);
    }

    private function bytesAreJpegOrPng(string $raw): bool
    {
        return str_starts_with($raw, "\xFF\xD8\xFF") || str_starts_with($raw, "\x89PNG\r\n\x1a\n");
    }

    private function readImageBytes(string $imageUrl): ?string
    {
        $file = $this->localImageFile($imageUrl);
        if ($file !== null) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && strlen($raw) > 32) {
                return $raw;
            }
        }

        try {
            $response = Http::timeout(60)->withoutVerifying()->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; 5CoreListingManager/1.0)',
                'Accept' => 'image/jpeg,image/png,image/webp,image/*',
            ])->get($imageUrl);
            if ($response->successful()) {
                $raw = $response->body();
                if (strlen($raw) > 32) {
                    return $raw;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function localImageFile(string $imageUrl): ?string
    {
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
        if (preg_match('#/listing-manager/media/([^/]+)$#', $path, $match)) {
            $relative = 'listing-manager/images/'.basename(rawurldecode($match[1]));
        } elseif (preg_match('#/storage/(.+)$#', $path, $match)) {
            $relative = ltrim(rawurldecode($match[1]), '/');
        } else {
            return null;
        }

        $candidates = [
            Storage::disk('public')->path($relative),
            storage_path('app/public/'.$relative),
            public_path('storage/'.$relative),
        ];
        foreach ($candidates as $full) {
            if (is_file($full) && is_readable($full)) {
                return $full;
            }
        }

        return null;
    }

    private function storeJpegForTemu(string $imageUrl, string $jpeg): string
    {
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
        if (! preg_match('#/listing-manager/media/([^/]+)$#', $path, $match)) {
            return '';
        }
        $jpgName = pathinfo(basename(rawurldecode($match[1])), PATHINFO_FILENAME).'.jpg';
        if (! preg_match('/^[a-f0-9]{40}\.jpg$/', $jpgName)) {
            return '';
        }
        Storage::disk('public')->put('listing-manager/images/'.$jpgName, $jpeg);

        return (string) preg_replace('#/listing-manager/media/[^/]+#', '/listing-manager/media/'.$jpgName, $imageUrl);
    }

    private function binaryToJpeg(string $raw): ?string
    {
        if (str_starts_with($raw, "\xFF\xD8\xFF")) {
            return $raw;
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $image = @imagecreatefromstring($raw);
            if ($image !== false) {
                return $this->gdToJpeg($image);
            }
        }
        if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg') && str_starts_with($raw, 'RIFF')) {
            $tmp = tempnam(sys_get_temp_dir(), 'temuimg');
            if (is_string($tmp)) {
                file_put_contents($tmp, $raw);
                $image = @imagecreatefromwebp($tmp);
                @unlink($tmp);
                if ($image !== false) {
                    return $this->gdToJpeg($image);
                }
            }
        }
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($raw);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(90);
                $blob = $im->getImageBlob();
                $im->clear();

                return is_string($blob) && $blob !== '' ? $blob : null;
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * @param  \GdImage|resource  $image
     */
    private function gdToJpeg($image): ?string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return null;
        }
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($image);

            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        return is_string($jpeg) && str_starts_with($jpeg, "\xFF\xD8") ? $jpeg : null;
    }
}
