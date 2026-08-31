<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh amazon_listing_statuses SKU ↔ ASIN link map from amazon_listings_raw + optional SP-API pull.
 */
class AmazonLinkMapSyncService
{
    private const CACHE_KEY = 'amazon_link_map_sync';

    public function __construct(
        protected AmazonSpApiService $amazonApi
    ) {}

    /**
     * @return array{success: bool, message: string, total_upserted?: int, done?: bool}
     */
    public function syncAll(): array
    {
        $page = 1;
        $totalUpserted = 0;
        do {
            $result = $this->syncPage($page, 200, $page === 1);
            if (empty($result['success'])) {
                return $result;
            }
            $totalUpserted = (int) ($result['total_upserted'] ?? $totalUpserted);
            if (! empty($result['done'])) {
                break;
            }
            $page++;
        } while ($page <= 500);

        return [
            'success' => true,
            'message' => "Link map sync finished ({$totalUpserted} row(s) touched).",
            'total_upserted' => $totalUpserted,
            'done' => true,
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     page: int,
     *     total_page: ?int,
     *     page_upserted: int,
     *     total_upserted: int,
     *     done: bool
     * }
     */
    public function syncPage(int $page = 1, int $pageSize = 200, bool $reset = false): array
    {
        if (! Schema::hasTable('amazon_listing_statuses')) {
            return $this->fail('amazon_listing_statuses table missing.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(500, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
            // Prefer existing amazon_listings_raw (scheduled report) — fills "Not on Amazon" gaps quickly.
            // Full SP-API / report refresh is optional and can be slow; skip on UI sync unless table empty.
            if ($this->amazonApi->isConfigured() && Schema::hasTable('amazon_listings_raw')) {
                $rawCount = (int) DB::table('amazon_listings_raw')->count();
                if ($rawCount === 0) {
                    $this->syncFromListingsOrInventory();
                }
            } elseif ($this->amazonApi->isConfigured()) {
                $this->syncFromListingsOrInventory();
            }
            $imported = $this->upsertFromListingsRaw();
            $this->updateProgress([
                'running' => true,
                'page' => 1,
                'message' => "Imported/updated {$imported} SKU(s) from amazon_listings_raw into amazon_listing_statuses…",
                'total_upserted' => $imported,
            ]);
        }

        $state = $this->getProgress();
        if (! empty($state['done']) && $page > 1) {
            return array_merge($state, ['success' => true]);
        }

        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Enriching amazon_listing_statuses page {$page}…",
        ]);

        $query = AmazonListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id');

        $totalCount = (clone $query)->count();
        $totalPage = max(1, (int) ceil($totalCount / $pageSize));
        $rows = $query->forPage($page, $pageSize)->get();

        $pageUpserted = 0;
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = AmazonListingStatusHelper::valueArray($row);
            $asin = AmazonListingStatusHelper::resolveAsin($row);
            if ($asin === '') {
                $asin = $this->lookupAsinFromListingsRaw($sku);
            }
            $dirty = false;
            if ($asin !== '' && strtoupper((string) ($value['asin'] ?? '')) !== $asin) {
                $value['asin'] = $asin;
                $value['buyer_link'] = $value['buyer_link']
                    ?? 'https://www.amazon.com/dp/'.$asin;
                $value['listed'] = $value['listed'] ?? 'Listed';
                $dirty = true;
            }
            if ($dirty) {
                $row->value = $value;
                $row->save();
            } else {
                $row->touch();
            }
            $pageUpserted++;
        }

        $baseUpserted = (int) ($state['total_upserted'] ?? 0);
        // On page 1 the import already counted raw upserts; enrichment pages add enrich count.
        $totalUpserted = $page === 1 ? max($baseUpserted, $pageUpserted) : ($baseUpserted + $pageUpserted);
        $done = $page >= $totalPage || $rows->isEmpty();
        $message = $done
            ? "Link map ready: {$totalCount} SKU(s) in amazon_listing_statuses (page {$page}/{$totalPage})."
            : "Page {$page} of {$totalPage}: enriched {$pageUpserted} SKU link(s)…";

        $this->updateProgress([
            'running' => ! $done,
            'page' => $page,
            'total_page' => $totalPage,
            'total_count' => $totalCount,
            'total_upserted' => $totalUpserted,
            'message' => $message,
            'done' => $done,
            'error' => false,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'page' => $page,
            'total_page' => $totalPage,
            'page_upserted' => $pageUpserted,
            'total_upserted' => $totalUpserted,
            'done' => $done,
        ];
    }

    /**
     * Map one Shopify SKU to Amazon instantly: local catalog, then SP-API, then optional ASIN paste.
     *
     * @return array{success: bool, needs_id?: bool, product_id?: string, source?: string, message: string, id_label?: string}
     */
    public function linkSku(string $sku, ?string $asinOverride = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.', 'id_label' => 'Amazon ASIN'];
        }
        if (! Schema::hasTable('amazon_listing_statuses')) {
            return ['success' => false, 'message' => 'amazon_listing_statuses table missing.', 'id_label' => 'Amazon ASIN'];
        }

        $asinOverride = strtoupper(trim((string) $asinOverride));
        if ($asinOverride !== '' && ! preg_match('/^[A-Z0-9]{10}$/', $asinOverride)) {
            return [
                'success' => false,
                'needs_id' => true,
                'message' => 'ASIN must be 10 letters/numbers (example B0XXXXXXXX).',
                'id_label' => 'Amazon ASIN',
            ];
        }

        $asin = $asinOverride;
        $source = $asin !== '' ? 'manual' : '';
        $title = null;
        $quantity = null;
        $status = null;

        if ($asin === '') {
            $raw = $this->lookupRawRowForSku($sku);
            if ($raw) {
                $asin = $this->asinFromRawRow($raw);
                if ($asin !== '') {
                    $source = 'amazon_listings_raw';
                    $title = trim((string) ($raw->item_name ?? '')) ?: null;
                    if (isset($raw->quantity) && $raw->quantity !== null && $raw->quantity !== '') {
                        $quantity = (int) $raw->quantity;
                    }
                }
            }
        }

        $live = null;
        if ($asin === '' && $this->amazonApi->isConfigured()) {
            $live = $this->amazonApi->lookupListingBySellerSku($sku);
            if (is_array($live)) {
                $asin = strtoupper(trim((string) ($live['asin'] ?? '')));
                $title = $title ?? ($live['title'] ?? null);
                $quantity = $quantity ?? ($live['quantity'] ?? null);
                $status = $live['status'] ?? null;
                if ($asin !== '') {
                    $source = 'sp-api';
                } elseif (trim((string) ($live['seller_sku'] ?? '')) !== '') {
                    $source = 'sp-api';
                }
            }
        }

        $asinOk = $asin !== '' && (bool) preg_match('/^[A-Z0-9]{10}$/', $asin);
        if (! $asinOk && $source !== 'sp-api') {
            return [
                'success' => false,
                'needs_id' => true,
                'message' => 'This SKU is not in the Amz catalog yet. Paste the 10-character ASIN to map it, or list the SKU on Amazon first.',
                'id_label' => 'Amazon ASIN',
            ];
        }

        $this->persistLinkedSku($sku, $asinOk ? $asin : '', [
            'title' => $title,
            'quantity' => $quantity,
            'status' => $status,
        ]);

        $productId = $asinOk ? $asin : ('AMZ:'.$sku);

        return [
            'success' => true,
            'product_id' => $productId,
            'source' => $source,
            'message' => $asinOk
                ? 'Linked '.$sku.' to Amz ASIN '.$asin.'.'
                : 'Linked '.$sku.' to the Amz seller SKU (ASIN not in the API response).',
            'id_label' => 'Amazon ASIN',
        ];
    }

    /**
     * @param  array{title?: ?string, quantity?: int|null, status?: ?string}  $meta
     */
    public function persistLinkedSku(string $sku, string $asin, array $meta = []): void
    {
        $asin = strtoupper(trim($asin));
        $existing = AmazonListingStatus::query()
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();

        $value = $existing ? AmazonListingStatusHelper::valueArray($existing) : [];
        if (preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            $value['asin'] = $asin;
            $value['product_id'] = $asin;
            $value['buyer_link'] = $value['buyer_link'] ?? ('https://www.amazon.com/dp/'.$asin);
        } else {
            $value['product_id'] = $value['product_id'] ?? ('AMZ:'.$sku);
        }
        $value['listed'] = $value['listed'] ?? 'Listed';
        $status = strtolower(trim((string) ($meta['status'] ?? '')));
        if ($status !== '') {
            $value['listing_status'] = in_array($status, ['buyable', 'discoverable'], true) ? 'active' : $status;
        } else {
            $value['listing_status'] = $value['listing_status'] ?? 'active';
        }
        if (! empty($meta['title'])) {
            $value['title'] = $meta['title'];
        }
        if (isset($meta['quantity']) && $meta['quantity'] !== null && $meta['quantity'] !== '') {
            $value['quantity'] = (int) $meta['quantity'];
        }
        $value['linked_at'] = now()->toDateTimeString();

        if ($existing) {
            $existing->sku = $sku;
            $existing->value = $value;
            $existing->save();
        } else {
            AmazonListingStatus::query()->create([
                'sku' => $sku,
                'value' => $value,
            ]);
        }
    }

    /**
     * Create/update amazon_listing_statuses from amazon_listings_raw (source of Active Amazon listings).
     */
    public function upsertFromListingsRaw(): int
    {
        if (! Schema::hasTable('amazon_listings_raw') || ! Schema::hasTable('amazon_listing_statuses')) {
            return 0;
        }

        $upserted = 0;
        DB::table('amazon_listings_raw')
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$upserted) {
                foreach ($rows as $raw) {
                    $sku = trim((string) ($raw->seller_sku ?? ''));
                    if ($sku === '') {
                        continue;
                    }

                    $asin = $this->asinFromRawRow($raw);
                    if ($asin === '') {
                        continue;
                    }

                    $existing = AmazonListingStatus::query()
                        ->where('sku', $sku)
                        ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                        ->first();

                    $value = $existing ? AmazonListingStatusHelper::valueArray($existing) : [];
                    $value['asin'] = $asin;
                    $value['buyer_link'] = $value['buyer_link'] ?? ('https://www.amazon.com/dp/'.$asin);
                    $value['listed'] = $value['listed'] ?? 'Listed';

                    $rawData = [];
                    if (! empty($raw->raw_data)) {
                        $decoded = is_array($raw->raw_data)
                            ? $raw->raw_data
                            : json_decode((string) $raw->raw_data, true);
                        $rawData = is_array($decoded) ? $decoded : [];
                    }
                    $status = strtolower(trim((string) ($rawData['status'] ?? '')));
                    if ($status !== '') {
                        $value['listing_status'] = $status;
                        if ($status === 'active') {
                            $value['listed'] = 'Listed';
                        }
                    }

                    if (isset($raw->quantity) && $raw->quantity !== null && $raw->quantity !== '') {
                        $value['quantity'] = (int) $raw->quantity;
                    } elseif (isset($rawData['quantity']) && $rawData['quantity'] !== '') {
                        $value['quantity'] = (int) $rawData['quantity'];
                    }
                    if (! empty($raw->your_price)) {
                        $value['price'] = $raw->your_price;
                    } elseif (! empty($rawData['price'])) {
                        $value['price'] = $rawData['price'];
                    }
                    if (! empty($raw->item_name)) {
                        $value['title'] = $raw->item_name;
                    }
                    if (! empty($raw->thumbnail_image)) {
                        $value['image'] = $raw->thumbnail_image;
                    }

                    if ($existing) {
                        $existing->sku = $sku;
                        $existing->value = $value;
                        $existing->save();
                    } else {
                        AmazonListingStatus::query()->create([
                            'sku' => $sku,
                            'value' => $value,
                        ]);
                    }
                    $upserted++;
                }
            });

        return $upserted;
    }

    protected function syncFromListingsOrInventory(): void
    {
        $this->updateProgress([
            'running' => true,
            'page' => 1,
            'message' => 'Refreshing Amazon listings / inventory from SP-API…',
        ]);

        try {
            if (Schema::hasTable('amazon_listings_raw')) {
                Artisan::call('app:fetch-amazon-listings');
            } else {
                $this->amazonApi->getinventory();
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonLinkMapSyncService: listings refresh failed', ['error' => $e->getMessage()]);
        }
    }

    protected function lookupRawRowForSku(string $sku): ?object
    {
        if (! Schema::hasTable('amazon_listings_raw')) {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $sku,
            strtoupper($sku),
            ShopifySku::normalizeSkuForShopifyLookup($sku),
        ])));

        $row = DB::table('amazon_listings_raw')
            ->where(function ($q) use ($candidates) {
                $q->whereIn('seller_sku', $candidates);
                foreach ($candidates as $candidate) {
                    $q->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [strtoupper($candidate)]);
                }
            })
            ->orderByDesc('id')
            ->first();

        return $row ?: null;
    }

    protected function lookupAsinFromListingsRaw(string $sku): string
    {
        if (! Schema::hasTable('amazon_listings_raw')) {
            return '';
        }

        $row = DB::table('amazon_listings_raw')
            ->where('seller_sku', $sku)
            ->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [strtoupper($sku)])
            ->orderByDesc('id')
            ->first(['asin1', 'raw_data']);

        return $row ? $this->asinFromRawRow($row) : '';
    }

    protected function asinFromRawRow(object $raw): string
    {
        $asin = strtoupper(trim((string) ($raw->asin1 ?? '')));
        if (preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return $asin;
        }

        $rawData = [];
        if (! empty($raw->raw_data)) {
            $decoded = is_array($raw->raw_data)
                ? $raw->raw_data
                : json_decode((string) $raw->raw_data, true);
            $rawData = is_array($decoded) ? $decoded : [];
        }
        foreach (['asin1', 'asin', 'ASIN'] as $key) {
            $candidate = strtoupper(trim((string) ($rawData[$key] ?? '')));
            if (preg_match('/^[A-Z0-9]{10}$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    public function getProgress(): array
    {
        return Cache::get(self::CACHE_KEY, [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_count' => 0,
            'total_upserted' => 0,
            'message' => '',
            'done' => false,
            'error' => false,
        ]);
    }

    protected function updateProgress(array $patch): void
    {
        Cache::put(self::CACHE_KEY, array_merge($this->getProgress(), $patch), now()->addHours(2));
    }

    protected function resetProgress(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     page: int,
     *     total_page: ?int,
     *     page_upserted: int,
     *     total_upserted: int,
     *     done: bool
     * }
     */
    protected function fail(string $message): array
    {
        $this->updateProgress([
            'running' => false,
            'done' => true,
            'error' => true,
            'message' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'page' => 0,
            'total_page' => null,
            'page_upserted' => 0,
            'total_upserted' => 0,
            'done' => true,
        ];
    }
}
