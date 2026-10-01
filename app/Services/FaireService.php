<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\Concerns\ResolvesBulletPointIdentifier;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\SavesMarketplaceImageMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class FaireService
{
    use ResolvesBulletPointIdentifier;
    use SavesMarketplaceVideoMetrics;
    use SavesMarketplaceImageMetrics;
    use VideoMasterMarketplaceMethods;

    protected $clientId;
    protected $clientSecret;
    protected $redirectUrl;
    protected $refreshToken;
    protected $region;
    protected $marketplaceId;
    protected $awsAccessKey;
    protected $awsSecretKey;
    protected $endpoint;

    public function __construct()
    {
        $this->clientId     = config('services.faire.app_id');
        $this->clientSecret = config('services.faire.app_secret');
        $this->redirectUrl  = config('services.faire.redirect_url');
    }

    public function getInventory()
    {
    }

    public function getProductIdBySku(string $sku): ?string
    {
        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');

        if (! $token) {
            Log::warning('Faire product lookup skipped: token not configured', ['sku' => $sku]);
            return null;
        }

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Accept' => 'application/json',
        ];

        try {
            $res = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(45)
                ->get("{$baseUrl}/products", ['sku' => $sku, 'limit' => 50]);

            if (! $res->successful()) {
                Log::warning('Faire product lookup failed', [
                    'sku' => $sku,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                return null;
            }

            $data = $res->json();
            $products = $data['products'] ?? $data['data'] ?? [];
            foreach ($products as $product) {
                $candidateSku = $product['sku'] ?? $product['external_sku'] ?? null;
                if ($candidateSku && strcasecmp((string) $candidateSku, $sku) === 0) {
                    return (string) ($product['id'] ?? '');
                }
            }

            if (! empty($products[0]['id'])) {
                return (string) $products[0]['id'];
            }
        } catch (\Throwable $e) {
            Log::error('Faire product lookup exception', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function faireMetricsTables(): array
    {
        $tables = [];
        foreach (['faire_metrics', 'faire_metric'] as $table) {
            if (Schema::hasTable($table)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    protected function resolveFaireProductId(string $identifier): ?string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        foreach ($this->faireMetricsTables() as $table) {
            $row = $this->findMetricRowBySkuOrAlternateIds($table, $identifier, ['product_id', 'faire_product_id']);
            if (! $row) {
                continue;
            }
            $id = trim((string) ($row->product_id ?? $row->faire_product_id ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return $this->getProductIdBySku($identifier) ?: null;
    }

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateTitle(string $sku, string $title): array
    {
        Log::info('🚀 Faire title update started', ['sku' => $sku]);

        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');

        if (! $token) {
            Log::error('❌ Faire push failed', ['sku' => $sku, 'error' => 'API token is missing']);
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $productId = $this->resolveFaireProductId($sku);
        if (! $productId) {
            Log::error('❌ Faire push failed', ['sku' => $sku, 'error' => 'Product not found by SKU']);
            return ['success' => false, 'message' => "Faire product not found for SKU {$sku}"];
        }

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payloads = [
            ['name' => $title, 'title' => $title],
            ['product_name' => $title, 'title' => $title],
        ];

        try {
            $res = null;
            foreach ($payloads as $payload) {
                $res = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(45)
                    ->patch("{$baseUrl}/products/{$productId}", $payload);

                if ($res->successful()) {
                    Log::info('✅ Faire title updated', ['sku' => $sku, 'product_id' => $productId]);
                    return ['success' => true, 'message' => 'Faire title updated', 'response' => $res->json()];
                }
            }

            Log::error('❌ Faire push failed', ['sku' => $sku, 'status' => $res?->status(), 'error' => $res?->body()]);
            return ['success' => false, 'message' => 'Faire update failed: ' . ($res?->body() ?? 'Unknown error')];
        } catch (\Throwable $e) {
            Log::error('❌ Faire push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');

        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and bullet points are required.'];
        }

        $productId = $this->resolveFaireProductId($identifier);
        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        // Faire has no bullet field: bullets become a marked block at the top of the (plain-text)
        // description, and the first bullet doubles as the ≤75-char short description.
        $lines = array_values(array_filter(array_map(
            static fn ($l) => trim(html_entity_decode(strip_tags((string) $l), ENT_QUOTES, 'UTF-8')),
            preg_split('/\r\n|\r|\n/', $bulletPoints) ?: []
        ), static fn ($l) => $l !== ''));
        if ($lines === []) {
            return ['success' => false, 'message' => 'No bullet points to send.'];
        }
        $live = $this->liveProductText($productId, $token);
        $description = self::mergeBulletsIntoDescription(self::plainText((string) ($live['description'] ?? '')), $lines);

        $result = $this->patchProductText($productId, $token, $description, self::shortDescription($lines[0]));
        if ($result['success']) {
            $result['message'] = 'Faire bullet points written to the description (Faire has no separate bullet field).';
        }

        return $result;
    }

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');
        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }
        $plain = self::plainText($description);
        if (trim($identifier) === '' || $plain === '') {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and description are required.'];
        }
        $productId = $this->resolveFaireProductId($identifier);
        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        // Keep a bullet block that an earlier push placed at the top of the live description.
        $live = $this->liveProductText($productId, $token);
        $existingBullets = self::bulletsFromDescription((string) ($live['description'] ?? ''));
        $body = $existingBullets !== [] && self::bulletsFromDescription($plain) === []
            ? self::mergeBulletsIntoDescription($plain, $existingBullets)
            : $plain;

        // Always resend a ≤75-char short description. Faire re-validates the stored
        // short description on every text patch, and an existing over-limit value
        // fails the update with "short description cannot have more than 75 characters".
        $short = trim((string) ($live['short_description'] ?? ''));
        $result = $this->patchProductText($productId, $token, $body, self::shortDescription($short !== '' ? $short : $plain));
        if ($result['success']) {
            $result['message'] = 'Faire description updated.';
        }

        return $result;
    }

    public const FAIRE_SHORT_DESCRIPTION_MAX = 75;

    private const BULLETS_HEADER = 'Highlights:';

    /**
     * Faire renders descriptions as plain text, so HTML from the editor is flattened to lines.
     */
    public static function plainText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li[^>]*>#i', '• ', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]*\n[ \t]*/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** Slice that fits Faire's short description limit in both characters and UTF-8 bytes. */
    public static function shortDescription(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', self::plainText($text)) ?? '');
        $text = ltrim($text, "•-* \t");
        $max = self::FAIRE_SHORT_DESCRIPTION_MAX;
        if (mb_strlen($text) > $max) {
            $cut = mb_substr($text, 0, $max);
            $space = mb_strrpos($cut, ' ');
            if ($space !== false && $space >= 40) {
                $cut = mb_substr($cut, 0, $space);
            }
            $text = rtrim($cut, " ,;:-");
        }
        while ($text !== '' && strlen($text) > $max) {
            $text = mb_substr($text, 0, mb_strlen($text) - 1);
        }

        return $text;
    }

    /**
     * @param  list<string>  $lines
     */
    public static function mergeBulletsIntoDescription(string $description, array $lines): string
    {
        $rest = self::stripBulletsBlock($description);
        $lines = array_values(array_filter(array_map(static fn ($l) => trim((string) $l), $lines), static fn ($l) => $l !== ''));
        if ($lines === []) {
            return $rest;
        }
        $block = self::BULLETS_HEADER."\n".implode("\n", array_map(static fn ($l) => '• '.ltrim($l, "• -*"), $lines));

        return trim($block.($rest !== '' ? "\n\n".$rest : ''));
    }

    /** @return list<string> */
    public static function bulletsFromDescription(string $description): array
    {
        $description = str_replace(["\r\n", "\r"], "\n", $description);
        if (! preg_match('/(?:^|\n)'.preg_quote(self::BULLETS_HEADER, '/').'\n((?:• [^\n]*\n?)+)/u', $description, $m)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/\n/', trim((string) $m[1])) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '•'));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    private static function stripBulletsBlock(string $description): string
    {
        $description = str_replace(["\r\n", "\r"], "\n", $description);
        $rest = preg_replace('/(?:^|\n)'.preg_quote(self::BULLETS_HEADER, '/').'\n(?:• [^\n]*\n?)+\n?/u', "\n", $description) ?? $description;

        return trim($rest);
    }

    /**
     * @return array{description?: string, short_description?: string}
     */
    private function liveProductText(string $productId, string $token): array
    {
        try {
            $res = Http::withoutVerifying()
                ->withHeaders([
                    'X-FAIRE-ACCESS-TOKEN' => $token,
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->get("https://www.faire.com/external-api/v2/products/{$productId}");
            if (! $res->successful()) {
                return [];
            }
            $json = $res->json();

            return is_array($json) ? array_intersect_key($json, ['description' => 1, 'short_description' => 1]) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{success:bool,message:string,response?:mixed}
     */
    private function patchProductText(string $productId, string $token, string $description, ?string $shortDescription): array
    {
        $payload = ['description' => $description];
        if ($shortDescription !== null && $shortDescription !== '') {
            $payload['short_description'] = self::shortDescription($shortDescription);
        }

        try {
            $res = Http::withoutVerifying()
                ->withHeaders([
                    'X-FAIRE-ACCESS-TOKEN' => $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(45)
                ->patch("https://www.faire.com/external-api/v2/products/{$productId}", $payload);

            if ($res->successful()) {
                return ['success' => true, 'message' => 'Faire product text updated', 'response' => $res->json()];
            }

            return ['success' => false, 'message' => 'Faire update failed: '.$res->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');
        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $productId = $this->resolveFaireProductId($identifier);
        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        $primary = $videos[0];
        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payloadAttempts = [
            ['video_url' => $primary, 'video_urls' => $videos],
            ['videos' => $videos, 'product_video_url' => $primary],
        ];

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $lastMessage = 'Faire video update failed.';
        foreach ($payloadAttempts as $payload) {
            try {
                $res = Http::withoutVerifying()->withHeaders($headers)->timeout(45)->patch("{$baseUrl}/products/{$productId}", $payload);
                if ($res->successful()) {
                    $sku = trim($identifier);
                    $this->saveVideoUrlsToMetricsRow('faire_metrics', $sku, $videos);

                    return ['success' => true, 'message' => 'Faire product video updated.', 'normalized_urls' => $videos];
                }
                $lastMessage = 'Faire update failed: '.$res->body();
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if (trim($identifier) === '' || $images === []) {
            return ['success' => false, 'message' => 'SKU (or Faire product id) and at least one image URL are required.'];
        }

        foreach ($images as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
            }
        }

        $token = config('services.faire.bearer_token')
            ?? config('services.faire.access_token')
            ?? config('services.faire.token');
        if (! $token) {
            return ['success' => false, 'message' => 'Faire API token is missing'];
        }

        $productId = $this->resolveFaireProductId($identifier);
        if (! $productId) {
            return ['success' => false, 'message' => 'Faire product not found for SKU or marketplace product id.'];
        }

        $headers = [
            'X-FAIRE-ACCESS-TOKEN' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $payloadAttempts = [
            ['images' => array_map(fn ($url) => ['url' => $url], $images)],
            ['image_url' => $images[0], 'images' => array_map(fn ($url) => ['url' => $url], $images)],
            ['image_url' => $images[0], 'image_urls' => $images, 'images' => $images],
        ];

        $baseUrl = 'https://www.faire.com/external-api/v2';
        $lastMessage = 'Faire image update failed.';
        foreach ($payloadAttempts as $payload) {
            try {
                $res = Http::withoutVerifying()->withHeaders($headers)->timeout(45)->patch("{$baseUrl}/products/{$productId}", $payload);
                if ($res->successful()) {
                    $this->saveFaireImageMetrics(trim($identifier), $images);

                    return ['success' => true, 'message' => 'Faire product images updated.', 'normalized_urls' => $images];
                }
                $lastMessage = 'Faire update failed: '.$res->body();
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        try {
            $res = Http::withoutVerifying()->withHeaders($headers)->timeout(60)->post(
                "{$baseUrl}/products/{$productId}/images",
                ['images' => array_map(fn ($url) => ['url' => $url], $images)]
            );
            if ($res->successful()) {
                $this->saveFaireImageMetrics(trim($identifier), $images);

                return ['success' => true, 'message' => 'Faire product images updated.', 'normalized_urls' => $images];
            }
            $lastMessage = 'Faire update failed: '.$res->body();
        } catch (\Throwable $e) {
            $lastMessage = $e->getMessage();
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  list<string>  $images
     */
    protected function saveFaireImageMetrics(string $sku, array $images): void
    {
        foreach ($this->faireMetricsTables() as $table) {
            $this->saveImageUrlsToMetricsRow($table, $sku, $images);
        }
    }

}
