<?php

namespace App\Support\Marketplace;

use App\Models\AmazonListingRaw;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download Listing Manager images once and serve them from this app.
 */
class ListingManagerImageStore
{
    public const DISK = 'public';

    public const DIR = 'listing-manager/images';

    public const INDEX_DIR = 'listing-manager/image-index';

    public static function isStored(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        return str_contains($url, '/listing-manager/media/')
            || str_contains($url, '/storage/listing-manager/images/');
    }

    public static function displayUrl(string $filename): string
    {
        return '/listing-manager/media/'.rawurlencode($filename);
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    public static function localizeMany(array $urls, ?string $sku = null): array
    {
        $out = [];
        $sources = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $url) && ! self::isStored($url)) {
                $sources[] = $url;
            }
            $local = self::localize($url);
            if ($local !== '' && ! in_array($local, $out, true)) {
                $out[] = $local;
            }
        }
        if ($sku && $out !== []) {
            self::rememberSku($sku, $out, $sources);
        }

        return $out;
    }

    public static function localize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (self::isStored($url)) {
            return $url;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $hash = sha1(self::canonicalUrl($url));
        $existing = self::findFile($hash);
        if ($existing !== null) {
            return self::displayUrl($existing);
        }

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (compatible; 5CoreListingManager/1.0)',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ];
            $response = null;
            try {
                $response = Http::timeout(25)->withHeaders($headers)->get($url);
            } catch (\Throwable) {
                $response = null;
            }
            if ($response === null || ! $response->successful()) {
                $response = Http::timeout(25)->withoutVerifying()->withHeaders($headers)->get($url);
            }
            if (! $response->successful()) {
                Log::warning('ListingManager image store HTTP '.$response->status(), ['url' => $url]);

                return $url;
            }

            $body = $response->body();
            if (strlen($body) < 32 || strlen($body) > 8 * 1024 * 1024) {
                return $url;
            }

            $mime = strtolower(trim((string) $response->header('Content-Type')));
            $mime = trim(explode(';', $mime)[0] ?? $mime);
            if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
                return $url;
            }

            $filename = $hash.'.'.self::extension($url, $mime);
            Storage::disk(self::DISK)->put(self::DIR.'/'.$filename, $body);

            return self::displayUrl($filename);
        } catch (\Throwable $e) {
            Log::warning('ListingManager image store failed: '.$e->getMessage(), ['url' => $url]);

            return $url;
        }
    }

    /**
     * @return list<string>
     */
    public static function cachedForSku(string $sku): array
    {
        $index = self::readIndex($sku);
        $images = [];
        foreach ($index['images'] as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (self::isStored($url) && ! self::storedFileExists($url)) {
                continue;
            }
            if (! in_array($url, $images, true)) {
                $images[] = $url;
            }
        }

        return $images;
    }

    /**
     * @return list<string>
     */
    public static function sourceUrlsForSku(string $sku): array
    {
        $index = self::readIndex($sku);

        return $index['source_urls'];
    }

    /**
     * @param  list<string>  $images
     * @param  list<string>  $sources
     */
    public static function rememberSku(string $sku, array $images, array $sources = []): void
    {
        $sku = trim($sku);
        if ($sku === '' || $images === []) {
            return;
        }

        $index = self::readIndex($sku);
        foreach ($images as $url) {
            $url = trim((string) $url);
            if ($url !== '' && ! in_array($url, $index['images'], true)) {
                $index['images'][] = $url;
            }
        }
        foreach ($sources as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https?://#i', $url) && ! self::isStored($url) && ! in_array($url, $index['source_urls'], true)) {
                $index['source_urls'][] = $url;
            }
        }
        $index['sku'] = $sku;
        $index['cached_at'] = now()->toIso8601String();

        Storage::disk('local')->put(self::indexPath($sku), json_encode($index, JSON_UNESCAPED_SLASHES));

        $thumb = $index['images'][0] ?? null;
        if ($thumb && Schema::hasTable('amazon_listings_raw') && Schema::hasColumn('amazon_listings_raw', 'thumbnail_image')) {
            AmazonListingRaw::query()
                ->where('seller_sku', $sku)
                ->update(['thumbnail_image' => $thumb]);
        }
    }

    public static function forgetSku(string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        Storage::disk('local')->delete(self::indexPath($sku));
    }

    public static function localUrlIfCached(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (self::isStored($url)) {
            return $url;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $existing = self::findFile(sha1(self::canonicalUrl($url)));

        return $existing !== null ? self::displayUrl($existing) : null;
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    public static function applyToList(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $mapped = self::localUrlIfCached($url) ?? $url;
            if (! in_array($mapped, $out, true)) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * Marketplace APIs need a public URL. Prefer the original CDN copy.
     *
     * @param  list<mixed>  $images
     * @param  list<mixed>  $sources
     * @return list<string>
     */
    public static function publishUrls(array $images, array $sources = []): array
    {
        $publicSources = [];
        foreach ($sources as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https?://#i', $url) && ! self::isStored($url)) {
                $publicSources[] = $url;
            }
        }
        $sourceByHash = [];
        foreach ($publicSources as $url) {
            $sourceByHash[sha1(self::canonicalUrl($url))] = $url;
        }

        $out = [];
        foreach ($images as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (self::isStored($url)) {
                $hash = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
                if (isset($sourceByHash[$hash])) {
                    $url = $sourceByHash[$hash];
                }
            }
            if (str_starts_with($url, '/')) {
                $url = rtrim((string) config('app.url'), '/').$url;
            }
            $out[] = $url;
        }
        if ($out === []) {
            return array_values(array_unique($publicSources));
        }

        return array_values(array_unique($out));
    }

    /**
     * Swap links whose file type the marketplace rejects for a public JPEG copy served by this app.
     * Links that cannot be converted are dropped.
     *
     * @param  list<mixed>  $urls
     * @param  list<string>  $allowedExtensions
     * @return list<string>
     */
    public static function withAllowedFormats(array $urls, array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif']): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if ($ext !== '' && ! in_array($ext, $allowedExtensions, true)) {
                $url = self::jpegCopyUrl($url);
            }
            if ($url !== '' && ! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return $out;
    }

    public static function jpegCopyUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            $url = rtrim((string) config('app.url'), '/').$url;
        }
        $stem = '';
        if (self::isStored($url)) {
            $stem = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        }
        if (! preg_match('/^[a-f0-9]{40}$/', $stem)) {
            $stem = sha1(self::canonicalUrl($url));
        }
        $name = $stem.'.jpg';
        $relative = self::DIR.'/'.$name;
        $publicUrl = rtrim((string) config('app.url'), '/').self::displayUrl($name);
        if (Storage::disk(self::DISK)->exists($relative)) {
            return $publicUrl;
        }

        $raw = null;
        if (self::isStored($url)) {
            $file = basename((string) parse_url($url, PHP_URL_PATH));
            if (Storage::disk(self::DISK)->exists(self::DIR.'/'.$file)) {
                $raw = Storage::disk(self::DISK)->get(self::DIR.'/'.$file);
            }
        }
        if ($raw === null) {
            try {
                $response = Http::timeout(30)->withoutVerifying()->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; 5CoreListingManager/1.0)',
                ])->get($url);
                $raw = $response->successful() ? $response->body() : null;
            } catch (\Throwable) {
                $raw = null;
            }
        }
        $jpeg = is_string($raw) && strlen($raw) > 32 ? self::toJpeg($raw) : null;
        if ($jpeg === null) {
            return '';
        }
        Storage::disk(self::DISK)->put($relative, $jpeg);

        return $publicUrl;
    }

    private static function toJpeg(string $raw): ?string
    {
        if (str_starts_with($raw, "\xFF\xD8\xFF")) {
            return $raw;
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $image = @imagecreatefromstring($raw);
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $canvas = imagecreatetruecolor($width, $height);
                imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));
                imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
                ob_start();
                imagejpeg($canvas, null, 90);
                $jpeg = ob_get_clean();
                imagedestroy($image);
                imagedestroy($canvas);
                if (is_string($jpeg) && str_starts_with($jpeg, "\xFF\xD8")) {
                    return $jpeg;
                }
            }
        }
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($raw);
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
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

    public static function serve(string $file): BinaryFileResponse
    {
        $file = basename($file);
        if (! preg_match('/^[a-f0-9]{40}\.[a-z0-9]{2,5}$/', $file)) {
            abort(404);
        }
        $relative = self::DIR.'/'.$file;
        if (! Storage::disk(self::DISK)->exists($relative)) {
            abort(404);
        }

        return response()->file(Storage::disk(self::DISK)->path($relative));
    }

    /**
     * @return array{images: list<string>, source_urls: list<string>}
     */
    private static function readIndex(string $sku): array
    {
        $empty = ['images' => [], 'source_urls' => []];
        $sku = trim($sku);
        if ($sku === '' || ! Storage::disk('local')->exists(self::indexPath($sku))) {
            return $empty;
        }
        $decoded = json_decode((string) Storage::disk('local')->get(self::indexPath($sku)), true);
        if (! is_array($decoded)) {
            return $empty;
        }

        return [
            'images' => array_values(array_filter(array_map('strval', $decoded['images'] ?? []))),
            'source_urls' => array_values(array_filter(array_map('strval', $decoded['source_urls'] ?? []))),
        ];
    }

    private static function indexPath(string $sku): string
    {
        return self::INDEX_DIR.'/'.sha1(strtoupper(trim($sku))).'.json';
    }

    private static function canonicalUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return strtolower((string) ($parts['scheme'] ?? 'https')).'://'.strtolower((string) $parts['host']).$path.$query;
    }

    private static function findFile(string $hash): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $name = $hash.'.'.$ext;
            if (Storage::disk(self::DISK)->exists(self::DIR.'/'.$name)) {
                return $name;
            }
        }

        return null;
    }

    private static function storedFileExists(string $url): bool
    {
        if (preg_match('~/listing-manager/media/([^/?#]+)~', $url, $m)) {
            return Storage::disk(self::DISK)->exists(self::DIR.'/'.basename(rawurldecode($m[1])));
        }
        if (preg_match('~/storage/listing-manager/images/([^/?#]+)~', $url, $m)) {
            return Storage::disk(self::DISK)->exists(self::DIR.'/'.basename(rawurldecode($m[1])));
        }

        return true;
    }

    private static function extension(string $url, string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (isset($map[$mime])) {
            return $map[$mime];
        }
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        if (preg_match('/\.(jpe?g|png|webp|gif)$/', $path, $m)) {
            return $m[1] === 'jpeg' ? 'jpg' : $m[1];
        }

        return 'jpg';
    }
}
