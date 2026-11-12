<?php

namespace App\Services;

use App\Models\AlibabaPricingPrice;
use App\Support\PushedListingPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Push and pull together, same idea as AmazonPushedPricePullService.
 *
 *  - afterPush(): right after Alibaba accepts a price, read the live listing price and write it into the Price column.
 *  - pullDue():   Alibaba reviews price changes, so the live price usually still shows the old one right after the push.
 *                 Pushed SKUs are re-read on a schedule until Alibaba shows the pushed price (or the window ends).
 *
 * Nothing needs a migration: the due list comes from the push-meta columns (sprice_pushed_at / sprice_pushed_value),
 * the retry state lives in the cache.
 */
class AlibabaPushedPricePullService
{
    /** Hours after a push during which the live price is still re-read. */
    public const WINDOW_HOURS = 12;

    /** Minutes between re-reads of one SKU. Slower once the first attempts did not match. */
    public const RETRY_MINUTES = 10;

    public const SLOW_RETRY_MINUTES = 60;

    public const FAST_ATTEMPTS = 3;

    private const CHANNEL = 'alibaba';

    /** State is per listing when a product id is known; the SKU-only key is the one used before listings were tracked. */
    private static function stateKey(string $sku, string $productId = ''): string
    {
        $productId = trim($productId);

        return 'alibaba-pull-state:'.md5(strtoupper(trim($sku)).($productId !== '' ? '|'.$productId : ''));
    }

    /** Alibaba product ids pushed for a SKU inside the re-read window (a SKU can be live on several products). */
    private static function listingsKey(string $sku): string
    {
        return 'alibaba-pull-listings:'.md5(strtoupper(trim($sku)));
    }

    /** @return list<string> */
    private function listings(string $sku): array
    {
        try {
            $v = Cache::get(self::listingsKey($sku));

            return is_array($v) ? array_values(array_filter(array_map('strval', $v), static fn ($p) => $p !== '')) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function registerListing(string $sku, string $productId): void
    {
        $productId = trim($productId);
        if ($productId === '') {
            return;
        }
        try {
            $ids = $this->listings($sku);
            if (! in_array($productId, $ids, true)) {
                $ids[] = $productId;
            }
            Cache::put(self::listingsKey($sku), $ids, now()->addHours(self::WINDOW_HOURS + 6));
        } catch (Throwable $e) {
            // Only decides which listings are re-read.
        }
    }

    /**
     * Pull the live price for a SKU that was just pushed.
     *
     * @return array{success:bool,price:?float,matched:bool,message:string}
     */
    public function afterPush(string $sku, float $pushed, string $productId = ''): array
    {
        $sku = trim($sku);
        $productId = trim($productId);
        $out = ['success' => false, 'price' => null, 'matched' => false, 'message' => ''];
        if ($sku === '' || ! ($pushed > 0)) {
            return $out;
        }

        $this->registerListing($sku, $productId);
        $out = $this->pullOne($sku, $pushed, $productId);
        $this->remember($sku, $out['matched'], 1, $this->pushedAtFor($sku), $productId);

        return $out;
    }

    /**
     * Re-read the live price of recently pushed SKUs whose Price column still differs from the push.
     *
     * @return array{due:int,pulled:int,matched:int,failed:int}
     */
    public function pullDue(int $limit = 30, int $delayMs = 400): array
    {
        $stats = ['due' => 0, 'pulled' => 0, 'matched' => 0, 'failed' => 0];
        if (! $this->hasMetaColumns()) {
            return $stats;
        }

        $rows = AlibabaPricingPrice::query()
            ->where('sprice_status', 'pushed')
            ->whereNotNull('sprice_pushed_at')
            ->where('sprice_pushed_at', '>=', now()->subHours(self::WINDOW_HOURS)->toDateTimeString())
            ->where('sprice_pushed_value', '>', 0)
            ->orderByDesc('sprice_pushed_at')
            ->get(['sku', 'price', 'sprice_pushed_value', 'sprice_pushed_at']);

        $due = [];
        foreach ($rows as $row) {
            $pushed = (float) $row->sprice_pushed_value;
            $pushedAt = (string) $row->sprice_pushed_at;
            // A SKU pushed to several listings is re-read per listing; a SKU-only push has one entry ('').
            $productIds = $this->listings((string) $row->sku);
            foreach ($productIds === [] ? [''] : $productIds as $productId) {
                $state = $this->state((string) $row->sku, $productId);
                // Done for this push: Alibaba showed the pushed price.
                if (($state['pushed_at'] ?? null) === $pushedAt && ! empty($state['matched'])) {
                    continue;
                }
                // Price column already is the pushed price. Only meaningful for a SKU-level read: with several listings
                // the shared pricing row says nothing about one listing.
                if ($productId === '' && PushedListingPrice::same((float) $row->price, $pushed)) {
                    continue;
                }
                $attempts = ($state['pushed_at'] ?? null) === $pushedAt ? (int) ($state['attempts'] ?? 0) : 0;
                $wait = $attempts >= self::FAST_ATTEMPTS ? self::SLOW_RETRY_MINUTES : self::RETRY_MINUTES;
                $last = ($state['pushed_at'] ?? null) === $pushedAt ? (int) ($state['last'] ?? 0) : 0;
                if ($last > 0 && $last > now()->subMinutes($wait)->getTimestamp()) {
                    continue;
                }
                $due[] = [$row, $pushed, $attempts, $productId];
                if (count($due) >= $limit) {
                    break 2;
                }
            }
        }

        $stats['due'] = count($due);
        foreach ($due as [$row, $pushed, $attempts, $productId]) {
            $res = $this->pullOne((string) $row->sku, $pushed, $productId);
            $this->remember((string) $row->sku, $res['matched'], $attempts + 1, (string) $row->sprice_pushed_at, $productId);
            if ($res['success']) {
                $stats['pulled']++;
                if ($res['matched']) {
                    $stats['matched']++;
                }
            } else {
                $stats['failed']++;
            }
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return $stats;
    }

    /**
     * @return array{success:bool,price:?float,matched:bool,message:string}
     */
    private function pullOne(string $sku, float $pushed, string $productId = ''): array
    {
        try {
            $key = strtoupper(trim($sku));
            $row = app(ChannelPushedPricePullService::class)
                ->pullSkus(
                    self::CHANNEL,
                    [$sku],
                    [$key => $pushed],
                    trim($productId) !== '' ? [$key => trim($productId)] : []
                )[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('[AlibabaPushedPricePull] pull failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'price' => null, 'matched' => false, 'message' => $e->getMessage()];
        }

        $price = isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : null;
        $ok = ! empty($row['success']) && $price !== null && $price > 0;

        return [
            'success' => $ok,
            'price' => $ok ? round($price, 2) : null,
            'matched' => $ok && PushedListingPrice::same($price, $pushed),
            'message' => (string) ($row['message'] ?? ''),
        ];
    }

    private function pushedAtFor(string $sku): string
    {
        if (! $this->hasMetaColumns()) {
            return '';
        }
        try {
            return (string) AlibabaPricingPrice::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                ->value('sprice_pushed_at');
        } catch (Throwable $e) {
            return '';
        }
    }

    /** @return array<string, mixed> */
    private function state(string $sku, string $productId = ''): array
    {
        try {
            $v = Cache::get(self::stateKey($sku, $productId));

            return is_array($v) ? $v : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function remember(string $sku, bool $matched, int $attempts, string $pushedAt, string $productId = ''): void
    {
        try {
            Cache::put(self::stateKey($sku, $productId), [
                'pushed_at' => $pushedAt,
                'matched' => $matched,
                'attempts' => $attempts,
                'last' => now()->getTimestamp(),
            ], now()->addHours(self::WINDOW_HOURS + 6));
        } catch (Throwable $e) {
            // The state only spaces out the re-reads.
        }
    }

    private function hasMetaColumns(): bool
    {
        try {
            return Schema::hasTable('alibaba_pricing_prices')
                && Schema::hasColumn('alibaba_pricing_prices', 'sprice_pushed_at');
        } catch (Throwable $e) {
            return false;
        }
    }
}
