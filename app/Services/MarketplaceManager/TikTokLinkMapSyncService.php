<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\WarmTikTok2LiveListingsCache;
use App\Jobs\WarmTikTokLiveListingsCache;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Page-by-page TikTok / TikTok 2 listings sync (same UX pattern as Reverb/AliExpress).
 *
 * Modes:
 * - auto  — picks quick (changed since last sync) or full (rebuild) automatically
 * - quick — only products with update_time since last sync
 * - full  — entire ACTIVATE catalog
 */
class TikTokLinkMapSyncService
{
    private const MAX_PAGES = 500;

    /** Force a full catalog rebuild at least this often. */
    private const FULL_SYNC_EVERY_DAYS = 7;

    /** Overlap when doing quick sync so we do not miss edge updates. */
    private const QUICK_OVERLAP_SECONDS = 7200;

    public function __construct(
        public string $channel = 'tiktok',
    ) {
        $channel = strtolower(trim($channel));
        $this->channel = in_array($channel, ['tiktok', 'tiktok2'], true) ? $channel : 'tiktok';
    }

    public static function for(string $channel): self
    {
        return new self($channel);
    }

    protected function cacheKey(): string
    {
        return 'tiktok_link_map_sync_'.$this->channel;
    }

    protected function metaKey(): string
    {
        return 'tiktok_link_map_meta_'.$this->channel;
    }

    protected function productModel(): string
    {
        return $this->channel === 'tiktok2' ? TikTokProductTwo::class : TikTokProduct::class;
    }

    protected function table(): string
    {
        return $this->channel === 'tiktok2' ? 'tiktok_products_two' : 'tiktok_products';
    }

    protected function api(): TikTokShopService
    {
        return $this->channel === 'tiktok2'
            ? app(TikTok2ShopService::class)
            : app(TikTokShopService::class);
    }

    protected function label(): string
    {
        return $this->channel === 'tiktok2' ? 'TikTok 2' : 'TikTok Shop';
    }

    /**
     * @return array{last_sync_at: ?string, last_full_sync_at: ?string}
     */
    public function getMeta(): array
    {
        $meta = Cache::get($this->metaKey(), []);

        return [
            'last_sync_at' => is_array($meta) ? ($meta['last_sync_at'] ?? null) : null,
            'last_full_sync_at' => is_array($meta) ? ($meta['last_full_sync_at'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    protected function updateMeta(array $patch): void
    {
        $current = $this->getMeta();
        Cache::put($this->metaKey(), array_merge($current, $patch), now()->addDays(60));
    }

    /**
     * Decide quick vs full. Stored on progress for subsequent pages.
     *
     * @return array{mode: string, update_time_ge: ?int, reason: string}
     */
    public function resolveMode(string $requested = 'auto'): array
    {
        $requested = strtolower(trim($requested));
        if (! in_array($requested, ['auto', 'quick', 'full'], true)) {
            $requested = 'auto';
        }

        $linked = 0;
        if (Schema::hasTable($this->table())) {
            $linked = (int) ($this->productModel())::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->count();
        }

        $meta = $this->getMeta();
        $lastSync = $meta['last_sync_at'] ? strtotime((string) $meta['last_sync_at']) : false;
        $lastFull = $meta['last_full_sync_at'] ? strtotime((string) $meta['last_full_sync_at']) : false;

        if ($requested === 'full') {
            return ['mode' => 'full', 'update_time_ge' => null, 'reason' => 'manual full'];
        }

        $needsFull = $linked < 10
            || $lastFull === false
            || ($lastFull !== false && $lastFull < now()->subDays(self::FULL_SYNC_EVERY_DAYS)->getTimestamp());

        if ($requested === 'quick') {
            if ($lastSync === false) {
                return ['mode' => 'full', 'update_time_ge' => null, 'reason' => 'no prior sync — using full'];
            }
            $ge = max(1, $lastSync - self::QUICK_OVERLAP_SECONDS);

            return ['mode' => 'quick', 'update_time_ge' => $ge, 'reason' => 'manual quick'];
        }

        // auto
        if ($needsFull) {
            $why = $linked < 10
                ? 'few/no linked SKUs'
                : ($lastFull === false ? 'never full-synced' : 'full sync older than '.self::FULL_SYNC_EVERY_DAYS.' days');

            return ['mode' => 'full', 'update_time_ge' => null, 'reason' => $why];
        }

        $ge = max(1, (int) $lastSync - self::QUICK_OVERLAP_SECONDS);

        return ['mode' => 'quick', 'update_time_ge' => $ge, 'reason' => 'changed since last sync'];
    }

    /**
     * Sync one API page for UI progress. Pass page=1 with reset=true to start.
     *
     * @param  string  $mode  auto|quick|full
     * @param  string  $pageToken  Client-supplied TikTok next_page_token (survives cache wipes)
     * @return array{
     *     success: bool,
     *     message: string,
     *     page: int,
     *     total_page: ?int,
     *     total_count: ?int,
     *     page_upserted: int,
     *     total_upserted: int,
     *     done: bool,
     *     mode?: string,
     *     next_page_token?: string
     * }
     */
    public function syncPage(int $page = 1, int $pageSize = 50, bool $reset = false, string $mode = 'auto', string $pageToken = ''): array
    {
        if (! Schema::hasTable($this->table())) {
            return $this->fail($this->table().' table missing. Run migrations.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $label = $this->label();

        if ($reset) {
            $resolved = $this->resolveMode($mode);
            $this->resetProgress();
            $this->updateProgress([
                'mode' => $resolved['mode'],
                'update_time_ge' => $resolved['update_time_ge'],
                'mode_reason' => $resolved['reason'],
            ]);
        }

        $state = $this->getProgress();
        if (empty($state['mode'])) {
            $resolved = $this->resolveMode($mode);
            $this->updateProgress([
                'mode' => $resolved['mode'],
                'update_time_ge' => $resolved['update_time_ge'],
                'mode_reason' => $resolved['reason'],
            ]);
            $state = $this->getProgress();
        }
        $activeMode = (string) ($state['mode'] ?? 'full');
        $updateTimeGe = isset($state['update_time_ge']) && $state['update_time_ge']
            ? (int) $state['update_time_ge']
            : null;
        $modeReason = (string) ($state['mode_reason'] ?? '');

        $pageToken = trim($pageToken);
        if ($page <= 1) {
            $pageToken = '';
        } elseif ($pageToken === '') {
            $pageToken = trim((string) ($state['next_page_token'] ?? ''));
        }
        if ($page > 1 && $pageToken === '') {
            Log::warning('TikTok link map missing page token; restarting from page 1', [
                'channel' => $this->channel,
                'page' => $page,
            ]);

            return $this->syncPage(1, $pageSize, true, $mode, '');
        }

        $modeLabel = $activeMode === 'quick' ? 'quick (changed only)' : 'full catalog';
        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Fetching {$label} {$modeLabel} page {$page}…",
            'error' => false,
            'done' => false,
        ]);

        $api = $this->api();
        if (! $api->isAuthenticated()) {
            $access = (string) config('services.'.$this->channel.'.access_token', '');
            $refresh = (string) config('services.'.$this->channel.'.refresh_token', '');
            if ($access !== '') {
                $api->setTokens($access, $refresh !== '' ? $refresh : null);
            }
        }
        if (! $api->isAuthenticated()) {
            return $this->fail("{$label} is not connected. Authorize via Connect first.", $page, $state);
        }

        $filters = [];
        if ($activeMode === 'quick' && $updateTimeGe) {
            $filters['update_time_ge'] = $updateTimeGe;
        }

        try {
            @set_time_limit(90);
            $response = $api->getProducts($pageSize, $pageToken, 'ACTIVATE', null, $filters);
        } catch (\Throwable $e) {
            Log::error('TikTok link map page fetch failed', [
                'channel' => $this->channel,
                'page' => $page,
                'mode' => $activeMode,
                'error' => $e->getMessage(),
            ]);

            return $this->fail("{$label} API request failed: ".$e->getMessage(), $page, $state);
        }
        if (! is_array($response)) {
            return $this->fail(
                "Failed to fetch products from {$label} API (timeout or no response).",
                $page,
                $state
            );
        }
        if (isset($response['code']) && (int) $response['code'] !== 0) {
            $code = (int) $response['code'];
            $msg = (string) ($response['message'] ?? 'TikTok API error');
            // Quick filter not supported / empty — fall back to full once.
            if ($activeMode === 'quick' && $page === 1) {
                Log::warning('TikTok quick link map failed; falling back to full', [
                    'channel' => $this->channel,
                    'code' => $code,
                    'message' => $msg,
                ]);
                $this->updateProgress([
                    'mode' => 'full',
                    'update_time_ge' => null,
                    'mode_reason' => 'quick failed — auto full fallback',
                ]);

                return $this->syncPage(1, $pageSize, false, 'full');
            }
            if ($code === 999999 && ! str_contains(strtolower($msg), 'shop_cipher')) {
                $msg .= ' (often IP not allow-listed in TikTok Partner Center)';
            }

            return $this->fail("{$label} API: {$msg}", $page, $state);
        }

        $products = $this->extractProducts($response);
        $totalCount = $this->intOrNull($this->extractTotalCount($response));
        $nextToken = $this->extractNextPageToken($response);
        $pageUpserted = $this->upsertProducts($products);

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $itemCount = count($products);
        $totalPage = null;
        if ($totalCount !== null && $pageSize > 0) {
            $totalPage = (int) max(1, (int) ceil($totalCount / $pageSize));
        }

        $done = $nextToken === '' || $itemCount === 0 || $page >= self::MAX_PAGES
            || ($totalPage !== null && $page >= $totalPage);

        $message = $done
            ? "{$label} {$modeLabel}: updated {$totalUpserted} SKU link(s) ({$page} page(s)"
                .($totalCount !== null ? ", {$totalCount} products" : '')
                .($modeReason !== '' ? "; {$modeReason}" : '').').'
            : "{$modeLabel} page {$page}".($totalPage ? " of {$totalPage}" : '')
                .": {$pageUpserted} SKU link(s) saved…";

        $this->updateProgress([
            'running' => ! $done,
            'page' => $page,
            'total_page' => $totalPage,
            'total_count' => $totalCount,
            'total_upserted' => $totalUpserted,
            'next_page_token' => $done ? '' : $nextToken,
            'message' => $message,
            'done' => $done,
            'error' => false,
            'mode' => $activeMode,
        ]);

        if ($done) {
            $now = now()->toDateTimeString();
            $metaPatch = ['last_sync_at' => $now];
            if ($activeMode === 'full') {
                $metaPatch['last_full_sync_at'] = $now;
            }
            $this->updateMeta($metaPatch);

            try {
                if ($this->channel === 'tiktok2') {
                    WarmTikTok2LiveListingsCache::dispatch();
                } else {
                    WarmTikTokLiveListingsCache::dispatch();
                }
            } catch (\Throwable $e) {
                Log::warning('TikTok link map: cache warm dispatch failed', [
                    'channel' => $this->channel,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('TikTok link map sync finished', [
                'channel' => $this->channel,
                'mode' => $activeMode,
                'pages_synced' => $page,
                'upserted' => $totalUpserted,
                'total_count' => $totalCount,
            ]);
        }

        return [
            'success' => true,
            'message' => $message,
            'page' => $page,
            'total_page' => $totalPage,
            'total_count' => $totalCount,
            'page_upserted' => $pageUpserted,
            'total_upserted' => $totalUpserted,
            'done' => $done,
            'mode' => $activeMode,
            'next_page_token' => $done ? '' : $nextToken,
        ];
    }

    /**
     * Run all pages (cron / queue job). Uses auto mode by default.
     *
     * @return array{success: bool, message: string, upserted: int, pages: int, mode: string}
     */
    public function syncAll(string $mode = 'auto', int $pageSize = 50): array
    {
        $page = 1;
        $reset = true;
        $last = [
            'success' => false,
            'message' => 'No pages synced.',
            'total_upserted' => 0,
            'mode' => $mode,
        ];

        while ($page <= self::MAX_PAGES) {
            $last = $this->syncPage($page, $pageSize, $reset, $mode);
            $reset = false;
            if (empty($last['success']) || ! empty($last['done'])) {
                break;
            }
            $page++;
        }

        return [
            'success' => ! empty($last['success']),
            'message' => (string) ($last['message'] ?? ''),
            'upserted' => (int) ($last['total_upserted'] ?? 0),
            'pages' => (int) ($last['page'] ?? max(0, $page - 1)),
            'mode' => (string) ($last['mode'] ?? $mode),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        $defaults = [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_count' => null,
            'total_upserted' => 0,
            'next_page_token' => '',
            'message' => '',
            'done' => false,
            'error' => false,
            'mode' => null,
            'update_time_ge' => null,
            'mode_reason' => '',
        ];
        $cached = Cache::get($this->cacheKey());
        if (is_array($cached) && $cached !== []) {
            return array_merge($defaults, $cached);
        }
        $fromFile = $this->readProgressFile();
        if (is_array($fromFile) && $fromFile !== []) {
            return array_merge($defaults, $fromFile);
        }

        return $defaults;
    }

    public function resetProgress(): void
    {
        $payload = [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_count' => null,
            'total_upserted' => 0,
            'next_page_token' => '',
            'message' => '',
            'done' => false,
            'error' => false,
            'mode' => null,
            'update_time_ge' => null,
            'mode_reason' => '',
        ];
        Cache::put($this->cacheKey(), $payload, 3600);
        $this->writeProgressFile($payload);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    protected function updateProgress(array $patch): void
    {
        $current = $this->getProgress();
        $merged = array_merge($current, $patch, [
            'updated_at' => now()->toDateTimeString(),
        ]);
        Cache::put($this->cacheKey(), $merged, 3600);
        $this->writeProgressFile($merged);
    }

    protected function progressFilePath(): string
    {
        return storage_path('app/'.$this->cacheKey().'.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readProgressFile(): ?array
    {
        $path = $this->progressFilePath();
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) @file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function writeProgressFile(array $payload): void
    {
        try {
            $dir = dirname($this->progressFilePath());
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($this->progressFilePath(), json_encode($payload));
        } catch (\Throwable) {
            // Best-effort; client page_token is the primary pagination source.
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function extractProducts(array $response): array
    {
        $candidates = [
            $response['products'] ?? null,
            $response['data']['products'] ?? null,
            $response['data']['data']['products'] ?? null,
        ];
        foreach ($candidates as $products) {
            if (is_array($products) && $products !== []) {
                return array_values(array_filter($products, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractTotalCount(array $response): mixed
    {
        return $response['total_count']
            ?? $response['data']['total_count']
            ?? $response['data']['data']['total_count']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractNextPageToken(array $response): string
    {
        $candidates = [
            $response['next_page_token'] ?? null,
            $response['page_token'] ?? null,
            $response['nextPageToken'] ?? null,
            $response['data']['next_page_token'] ?? null,
            $response['data']['page_token'] ?? null,
            $response['data']['nextPageToken'] ?? null,
            $response['data']['data']['next_page_token'] ?? null,
        ];
        foreach ($candidates as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    protected function upsertProducts(array $products): int
    {
        $model = $this->productModel();
        $hasSkuIdCol = Schema::hasColumn($this->table(), 'sku_id');
        $upserted = 0;

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $productId = $product['id'] ?? $product['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            foreach ($this->expandProductSkuRows($product) as $row) {
                $normalizedSku = strtoupper(trim((string) $row['sku']));
                if ($normalizedSku === '') {
                    continue;
                }

                $update = [
                    'product_id' => (string) $productId,
                    'price' => $row['price'],
                ];
                if ($row['stock'] !== null) {
                    $update['stock'] = $row['stock'];
                }
                if ($hasSkuIdCol && ! empty($row['sku_id'])) {
                    $update['sku_id'] = (string) $row['sku_id'];
                }

                /** @var TikTokProduct|TikTokProductTwo|null $existing */
                $existing = $model::query()->where('sku', $normalizedSku)->first();
                if ($existing) {
                    $same = (string) $existing->product_id === (string) $update['product_id']
                        && (float) $existing->price === (float) $update['price']
                        && (! array_key_exists('stock', $update) || (int) $existing->stock === (int) $update['stock'])
                        && (! array_key_exists('sku_id', $update) || (string) ($existing->sku_id ?? '') === (string) $update['sku_id']);
                    if ($same) {
                        continue;
                    }
                    $existing->fill($update);
                    $existing->save();
                } else {
                    $model::query()->create(array_merge(['sku' => $normalizedSku], $update));
                }
                $upserted++;
            }
        }

        return $upserted;
    }

    /**
     * @return list<array{sku: string, sku_id: ?string, price: float, stock: ?int}>
     */
    protected function expandProductSkuRows(array $product): array
    {
        $rows = [];
        $skus = $product['skus'] ?? null;

        if (is_array($skus) && $skus !== []) {
            foreach ($skus as $skuNode) {
                if (! is_array($skuNode)) {
                    continue;
                }
                $sellerSku = trim((string) ($skuNode['seller_sku'] ?? $skuNode['sku'] ?? ''));
                if ($sellerSku === '') {
                    continue;
                }
                $skuId = trim((string) ($skuNode['id'] ?? $skuNode['sku_id'] ?? ''));
                $rows[] = [
                    'sku' => $sellerSku,
                    'sku_id' => $skuId !== '' ? $skuId : null,
                    'price' => $this->extractPriceFromSkuNode($skuNode, $product),
                    'stock' => $this->extractStockFromSkuNode($skuNode),
                ];
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $sku = trim((string) (
            $product['seller_sku']
            ?? $product['sku']
            ?? $product['product_sku']
            ?? ''
        ));
        if ($sku === '') {
            return [];
        }

        return [[
            'sku' => $sku,
            'sku_id' => null,
            'price' => $this->extractPriceFromSkuNode([], $product),
            'stock' => null,
        ]];
    }

    protected function extractPriceFromSkuNode(array $skuNode, array $product): float
    {
        $priceNode = $skuNode['price'] ?? null;
        $candidates = [];
        if (is_array($priceNode)) {
            $candidates[] = $priceNode['sale_price']
                ?? $priceNode['tax_exclusive_price']
                ?? $priceNode['amount']
                ?? $priceNode['price']
                ?? null;
        } elseif (is_numeric($priceNode)) {
            $candidates[] = $priceNode;
        }
        $candidates[] = $skuNode['sale_price'] ?? $skuNode['price_amount'] ?? null;
        $candidates[] = $product['sale_price'] ?? $product['price'] ?? null;

        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $price = (float) $value;
            if ($price > 0) {
                return $price;
            }
        }

        return 0.0;
    }

    protected function extractStockFromSkuNode(array $skuNode): ?int
    {
        $stock = 0;
        $found = false;
        $inventory = $skuNode['inventory'] ?? null;
        if (is_array($inventory)) {
            $found = true;
            if (array_is_list($inventory)) {
                foreach ($inventory as $invRow) {
                    if (is_array($invRow)) {
                        $stock += (int) ($invRow['quantity'] ?? $invRow['available_quantity'] ?? 0);
                    }
                }
            } else {
                $stock += (int) ($inventory['quantity'] ?? $inventory['available_quantity'] ?? 0);
            }
        }
        if (isset($skuNode['stock_infos']) && is_array($skuNode['stock_infos'])) {
            $found = true;
            foreach ($skuNode['stock_infos'] as $info) {
                if (is_array($info)) {
                    $stock += (int) ($info['available_stock'] ?? $info['quantity'] ?? 0);
                }
            }
        }
        if (isset($skuNode['quantity']) || isset($skuNode['available_stock'])) {
            $found = true;
            $stock += (int) ($skuNode['quantity'] ?? $skuNode['available_stock'] ?? 0);
        }

        return $found ? $stock : null;
    }

    protected function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function fail(string $message, int $page = 1, array $state = []): array
    {
        $this->updateProgress([
            'running' => false,
            'message' => $message,
            'error' => true,
            'done' => true,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'page' => $page,
            'total_page' => null,
            'total_count' => null,
            'page_upserted' => 0,
            'total_upserted' => (int) ($state['total_upserted'] ?? 0),
            'done' => true,
            'mode' => $state['mode'] ?? null,
        ];
    }
}
