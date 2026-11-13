<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\DobaMetric;
use App\Services\DobaApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Doba "Listed" is doba_metrics.item_id (non-empty). Doba has no single-item lookup that tells "not found"
 * from an API error, so the whole goods/detail catalog is read (100 products per page, every page must
 * answer) and compared with the stored rows. A row whose SKU code and item number are both missing from a
 * completely read catalog loses its item_id and the SKU shows on Missing L.
 *
 * Nothing is cleared when a page could not be read, when the catalog has fewer than 20 SKUs, or when it would
 * remove more than 80% of the stored item ids.
 */
class DobaStaleListingVerifier implements StaleListingVerifier
{
    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 200;

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('doba_metrics')) {
            $out['message'] = 'Doba: doba_metrics table not found.';

            return $out;
        }
        $api = app(DobaApiService::class);
        if (! $api->isConfigured()) {
            $out['message'] = 'Doba: API credentials missing, nothing checked.';

            return $out;
        }

        $rows = DobaMetric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->orderBy('id')
            ->get(['id', 'sku', 'item_id'])
            ->filter(fn ($row) => stripos((string) $row->sku, 'PARENT') === false)
            ->values();
        $out['checked'] = $rows->count();
        if ($rows->isEmpty()) {
            $out['ok'] = true;
            $out['message'] = 'Doba: no listed SKU with an item id to check.';

            return $out;
        }

        try {
            $live = $this->readCatalog($api);
        } catch (\Throwable $e) {
            Log::warning('DobaStaleListingVerifier: catalog read failed', ['error' => $e->getMessage()]);
            $out['errors'] = $rows->count();
            $out['message'] = 'Doba catalog could not be read completely, nothing removed ('.$e->getMessage().').';

            return $out;
        }

        $liveSkus = $live['skus'];
        $liveItems = $live['items'];
        if (count($liveSkus) < 20) {
            $out['errors'] = $rows->count();
            $out['message'] = 'Doba catalog returned only '.count($liveSkus).' SKU(s), too few to trust - nothing removed.';

            return $out;
        }

        $stale = $rows->filter(function ($row) use ($liveSkus, $liveItems) {
            return ! isset($liveSkus[self::normalizeSku((string) $row->sku)])
                && ! isset($liveItems[self::normalizeSku((string) $row->item_id)]);
        })->values();

        $storedIds = $rows->pluck('item_id')->map(fn ($id) => self::normalizeSku((string) $id))->unique();
        $staleIds = $stale->pluck('item_id')->map(fn ($id) => self::normalizeSku((string) $id))->unique();
        if ($staleIds->count() > (int) floor($storedIds->count() * 0.8) && $staleIds->count() > 0) {
            $out['errors'] = $stale->count();
            $out['kept'] = $rows->count() - $stale->count();
            $out['message'] = "Doba's catalog (".count($liveSkus)." SKUs) disagrees with {$staleIds->count()} of {$storedIds->count()} stored item ids - nothing removed.";

            return $out;
        }

        foreach ($stale as $row) {
            try {
                DobaMetric::query()->whereKey($row->id)->update(['item_id' => null]);
                $out['cleared']++;
                Log::info('DobaStaleListingVerifier: SKU is no longer in the Doba catalog, item_id cleared', ['sku' => $row->sku, 'item_id' => $row->item_id]);
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('DobaStaleListingVerifier: update failed', ['sku' => $row->sku, 'item_id' => $row->item_id, 'error' => $e->getMessage()]);
            }
        }

        $out['kept'] = max(0, $rows->count() - $stale->count());
        $out['ok'] = true;
        $out['message'] = "Checked {$out['checked']} listed SKU(s) against the Doba catalog (".count($liveSkus)." SKUs): {$out['cleared']} no longer on Doba"
            .($out['cleared'] > 0 ? ' (now Missing L)' : '').", {$out['kept']} still listed, {$out['errors']} could not be updated.";

        return $out;
    }

    /**
     * Every SKU code and item number in the live catalog. Throws unless the last page was a short or empty page.
     *
     * @return array{skus: array<string, true>, items: array<string, true>}
     */
    private function readCatalog(DobaApiService $api): array
    {
        $skus = [];
        $items = [];
        $complete = false;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $rows = $this->readPage($api, $page);
            foreach ($rows as $product) {
                if (! is_array($product)) {
                    continue;
                }
                foreach ((array) ($product['skus'] ?? []) as $sku) {
                    if (! is_array($sku)) {
                        continue;
                    }
                    $code = self::normalizeSku((string) ($sku['skuCode'] ?? ''));
                    if ($code !== '') {
                        $skus[$code] = true;
                    }
                    foreach ((array) ($sku['stocks'] ?? []) as $stock) {
                        $itemNo = is_array($stock) ? self::normalizeSku((string) ($stock['itemNo'] ?? '')) : '';
                        if ($itemNo !== '') {
                            $items[$itemNo] = true;
                        }
                    }
                }
            }
            if (count($rows) < self::PAGE_SIZE) {
                $complete = true;
                break;
            }
            usleep(300000);
        }

        if (! $complete) {
            throw new \RuntimeException('catalog is longer than '.self::MAX_PAGES.' pages');
        }

        return ['skus' => $skus, 'items' => $items];
    }

    /**
     * One goods/detail page with retries; an API error throws instead of looking like "no more pages".
     *
     * @return list<array<string, mixed>>
     */
    private function readPage(DobaApiService $api, int $page): array
    {
        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = $api->goodsDetailRaw($page, self::PAGE_SIZE);
            } catch (\Throwable $e) {
                $last = ['errors' => $e->getMessage()];
                sleep(2 * $attempt);

                continue;
            }
            $rows = $response['businessData']['data']['dsGoodsDetailResultVOS'] ?? null;
            if (is_array($rows)) {
                return $rows;
            }
            $last = $response;
            sleep(2 * $attempt);
        }

        $msg = (string) ($last['responseMessage'] ?? $last['message'] ?? $last['errors'] ?? 'unexpected response');

        throw new \RuntimeException('goods/detail page '.$page.': '.mb_substr($msg, 0, 200));
    }

    private static function normalizeSku(string $value): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $value)));
    }
}
