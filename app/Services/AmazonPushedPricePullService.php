<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use Illuminate\Support\Facades\Log;

/**
 * After a successful S PRC / Push Prc write to Amazon, update Price immediately
 * (the pushed listing/sale price), then confirm with a live SP-API pull.
 * Cron retries leftovers every minute if Amazon's GET is still stale.
 *
 * The Price column is the price Amazon.com shows: the active Sales Price when
 * one is set, otherwise Your Price. A pushed S PRC must not replace that.
 */
class AmazonPushedPricePullService
{
    public const DELAY_MINUTES = 2;

    public const MAX_ATTEMPTS = 6;

    public const LIVE_MATCH_TOLERANCE = 0.05;

    public function scheduleAfterPush(string $gridSku, ?string $sellerSku = null): void
    {
        $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', $gridSku)));
        if ($sku === '') {
            return;
        }

        $row = AmazonDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($row->value)
            ? $row->value
            : (json_decode($row->value ?? '{}', true) ?? []);

        $value['PRICE_PULL_STATUS'] = 'pending';
        $value['PRICE_PULL_DUE_AT'] = now()->addMinutes(self::DELAY_MINUTES)->toDateTimeString();
        $value['PRICE_PULL_ATTEMPTS'] = 0;
        $seller = trim((string) $sellerSku);
        if ($seller !== '') {
            $value['PRICE_PULL_SELLER_SKU'] = $seller;
        }
        unset($value['PRICE_PULLED_AT'], $value['PRICE_PULLED_VALUE']);

        $row->value = $value;
        $row->save();
    }

    /**
     * Write the just-pushed listing price into Price now, then try a live GET.
     *
     * @return array{price: float, from_live: bool, wrote: bool}
     */
    public function confirmAfterPush(string $gridSku, ?string $sellerSku, float $listingPrice): array
    {
        $listingPrice = round($listingPrice, 2);
        $out = ['price' => $listingPrice, 'from_live' => false, 'wrote' => false];
        if ($listingPrice < 0.01) {
            return $out;
        }

        $seller = trim((string) $sellerSku);
        $writeSku = $seller !== '' ? $seller : $gridSku;
        $out['wrote'] = $this->writeDatasheetPrice($gridSku, $writeSku, $listingPrice);

        try {
            $apiSku = $seller !== ''
                ? $seller
                : (string) (AmazonDatasheet::resolveSellerMskuByProductKey($gridSku) ?: $gridSku);
            $live = $this->currentListingPrice(app(AmazonSpApiService::class)->getListingsItemFullDetails($apiSku), $listingPrice);
            $persist = self::livePriceToPersist($live, $listingPrice);
            if ($persist !== null) {
                $this->writeDatasheetPrice($gridSku, $apiSku, $persist);
                $out['price'] = $persist;
                $out['from_live'] = true;
                $out['wrote'] = true;
                if (abs($persist - $listingPrice) <= self::LIVE_MATCH_TOLERANCE) {
                    $this->markPulled($gridSku, $apiSku, $persist);

                    return $out;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Amazon immediate price pull after push failed', [
                'sku' => $gridSku,
                'seller_sku' => $seller,
                'error' => $e->getMessage(),
            ]);
        }

        $this->scheduleAfterPush($gridSku, $seller !== '' ? $seller : null);

        return $out;
    }

    /**
     * Last Dil / Push Prc Sale from amazon_data_view.value.
     *
     * @param  array<string, mixed>  $dv
     */
    public static function pushedSaleFromValue(array $dv): ?float
    {
        $sale = AmazonSpApiService::lastPushedSaleBusinessMin($dv)['sale'] ?? null;

        return ($sale !== null && $sale > 0) ? round((float) $sale, 2) : null;
    }

    /**
     * Price Amazon.com shows. An active Sales Price is the customer price.
     * Your Price is only used when there is no sale.
     */
    public static function customerPrice(?float $yourPrice, ?float $salePrice): ?float
    {
        $sale = ($salePrice !== null && $salePrice > 0) ? round($salePrice, 2) : 0.0;
        $your = ($yourPrice !== null && $yourPrice > 0) ? round($yourPrice, 2) : 0.0;
        if ($sale > 0) {
            return $sale;
        }
        if ($your > 0) {
            return $your;
        }

        return null;
    }

    /**
     * Merchant listings "price" is Amazon's current price for that SKU.
     * Keep it when it disagrees with the S PRC we pushed.
     */
    public static function listingsReportPriceToWrite(?float $reportPrice, ?float $pushedSale): ?float
    {
        $report = ($reportPrice !== null && $reportPrice > 0) ? round($reportPrice, 2) : null;
        if ($report !== null) {
            return $report;
        }

        return ($pushedSale !== null && $pushedSale > 0) ? round($pushedSale, 2) : null;
    }

    /**
     * Persist the live Amazon customer price, including when it is not the S PRC.
     */
    public static function livePriceToPersist(?float $live, ?float $expectedPushed): ?float
    {
        if ($live === null || $live < 0.01) {
            return null;
        }

        return round($live, 2);
    }

    /**
     * @return array<string, float> SKU keys (upper, spaced, compact) → pushed Sale
     */
    public function pushedSaleLookup(): array
    {
        $map = [];
        AmazonDataView::query()
            ->where(function ($q) {
                $q->where('value', 'like', '%AMAZON_PUSHED_SALE%')
                    ->orWhere('value', 'like', '%SPRICE_PUSHED_VALUE%');
            })
            ->get(['sku', 'value'])
            ->each(function (AmazonDataView $row) use (&$map) {
                $dv = is_array($row->value)
                    ? $row->value
                    : (json_decode((string) $row->value, true) ?: []);
                $sale = self::pushedSaleFromValue(is_array($dv) ? $dv : []);
                if ($sale === null) {
                    return;
                }
                $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $row->sku)));
                if ($sku === '') {
                    return;
                }
                $map[$sku] = $sale;
                $spaced = AmazonDatasheet::normalizeSkuSpaces($sku);
                if ($spaced !== '') {
                    $map[$spaced] = $sale;
                }
                $compact = AmazonDatasheet::normalizeSkuForLookup($sku);
                if ($compact !== '') {
                    $map[$compact] = $sale;
                }
            });

        return $map;
    }

    /**
     * @param  array<string, float>  $lookup
     */
    public static function lookupPushedSale(array $lookup, string $sku): ?float
    {
        $raw = strtoupper(trim(str_replace("\xc2\xa0", ' ', $sku)));
        foreach ([$raw, AmazonDatasheet::normalizeSkuSpaces($raw), AmazonDatasheet::normalizeSkuForLookup($raw)] as $key) {
            if ($key !== '' && isset($lookup[$key]) && $lookup[$key] > 0) {
                return round((float) $lookup[$key], 2);
            }
        }

        return null;
    }

    /**
     * Price stays the Amazon customer price. Do not rewrite it to S PRC.
     */
    public function repairPriceIfStale(string $gridSku, ?string $sellerSku, float $expected): bool
    {
        return false;
    }

    /**
     * Price stays the Amazon customer price. Do not rewrite it to S PRC.
     */
    public function restoreClobberedPushedSales(int $limit = 250): int
    {
        return 0;
    }

    /**
     * @return array{due: int, pulled: int, failed: int, retried: int, restored: int}
     */
    public function pullDue(int $limit = 30, int $delayMs = 500): array
    {
        $stats = [
            'due' => 0,
            'pulled' => 0,
            'failed' => 0,
            'retried' => 0,
            'restored' => 0,
        ];

        $due = $this->dueRows($limit);
        $stats['due'] = $due->count();
        if ($due->isEmpty()) {
            return $stats;
        }

        $api = app(AmazonSpApiService::class);

        foreach ($due as $row) {
            $gridSku = (string) $row->sku;
            $value = is_array($row->value)
                ? $row->value
                : (json_decode($row->value ?? '{}', true) ?? []);
            $sellerSku = trim((string) ($value['PRICE_PULL_SELLER_SKU'] ?? ''));
            if ($sellerSku === '') {
                $sellerSku = (string) (AmazonDatasheet::resolveSellerMskuByProductKey($gridSku) ?: $gridSku);
            }

            $expected = self::pushedSaleFromValue(is_array($value) ? $value : []);
            $details = $api->getListingsItemFullDetails($sellerSku);
            $live = $this->currentListingPrice($details, $expected);
            $price = self::livePriceToPersist($live, $expected);

            if ($price === null && $expected !== null) {
                $attempts = ((int) ($value['PRICE_PULL_ATTEMPTS'] ?? 0)) + 1;
                $value['PRICE_PULL_ATTEMPTS'] = $attempts;
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $this->markPulled($gridSku, $sellerSku, $expected, $row, $value);
                    $stats['pulled']++;
                } else {
                    $value['PRICE_PULL_STATUS'] = 'pending';
                    $value['PRICE_PULL_DUE_AT'] = now()->addMinutes(2)->toDateTimeString();
                    $row->value = $value;
                    $row->save();
                    $stats['retried']++;
                }
                $this->pause($delayMs);

                continue;
            }

            if ($price === null) {
                $attempts = ((int) ($value['PRICE_PULL_ATTEMPTS'] ?? 0)) + 1;
                $value['PRICE_PULL_ATTEMPTS'] = $attempts;
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $value['PRICE_PULL_STATUS'] = 'failed';
                    $stats['failed']++;
                    Log::warning('Amazon pushed-price pull: no listing price after retries', [
                        'sku' => $gridSku,
                        'seller_sku' => $sellerSku,
                        'attempts' => $attempts,
                    ]);
                } else {
                    $value['PRICE_PULL_STATUS'] = 'pending';
                    $value['PRICE_PULL_DUE_AT'] = now()->addMinutes(2)->toDateTimeString();
                    $stats['retried']++;
                }
                $row->value = $value;
                $row->save();
                $this->pause($delayMs);

                continue;
            }

            if (! $this->writeDatasheetPrice($gridSku, $sellerSku, $price)) {
                $value['PRICE_PULL_STATUS'] = 'failed';
                $value['PRICE_PULL_ATTEMPTS'] = ((int) ($value['PRICE_PULL_ATTEMPTS'] ?? 0)) + 1;
                $row->value = $value;
                $row->save();
                $stats['failed']++;
                $this->pause($delayMs);

                continue;
            }

            $this->markPulled($gridSku, $sellerSku, $price, $row, $value);
            $stats['pulled']++;

            Log::info('Amazon pushed-price pull: Price column updated', [
                'sku' => $gridSku,
                'seller_sku' => $sellerSku,
                'price' => $price,
            ]);

            $this->pause($delayMs);
        }

        return $stats;
    }

    /**
     * Pull live listing Price now for specific grid SKUs (after a successful push).
     *
     * @param  list<string>  $skus
     * @return list<array{success:bool,sku:string,price:?float,message:string}>
     */
    public function pullSkusNow(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            return strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $s)));
        }, $skus), static fn ($s) => $s !== '')));
        $skus = array_slice($skus, 0, 50);
        $out = [];
        if ($skus === []) {
            return $out;
        }

        $api = app(AmazonSpApiService::class);
        foreach ($skus as $gridSku) {
            $row = AmazonDataView::query()
                ->where(function ($q) use ($gridSku) {
                    $q->where('sku', $gridSku)
                        ->orWhereRaw('UPPER(TRIM(REPLACE(sku, UNHEX(\'C2A0\'), \' \'))) = ?', [$gridSku]);
                })
                ->first();
            $value = $row
                ? (is_array($row->value) ? $row->value : (json_decode($row->value ?? '{}', true) ?? []))
                : [];
            if (! is_array($value)) {
                $value = [];
            }
            $sellerSku = trim((string) ($value['PRICE_PULL_SELLER_SKU'] ?? ''));
            if ($sellerSku === '') {
                $sellerSku = (string) (AmazonDatasheet::resolveSellerMskuByProductKey($gridSku) ?: $gridSku);
            }

            try {
                $details = $api->getListingsItemFullDetails($sellerSku);
                $expected = self::pushedSaleFromValue($value);
                $price = self::livePriceToPersist($this->currentListingPrice($details, $expected), $expected);
                if ($price === null || ! $this->writeDatasheetPrice($gridSku, $sellerSku, $price)) {
                    $out[] = [
                        'success' => false,
                        'sku' => $gridSku,
                        'price' => null,
                        'message' => 'Live Amazon price not found',
                    ];
                    $this->pause(400);

                    continue;
                }

                if ($row) {
                    $this->markPulled($gridSku, $sellerSku, $price, $row, $value);
                }

                $out[] = [
                    'success' => true,
                    'sku' => $gridSku,
                    'price' => $price,
                    'message' => 'Pulled live price $'.number_format($price, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('Amazon pushed-price pull-now failed', [
                    'sku' => $gridSku,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $gridSku,
                    'price' => null,
                    'message' => $e->getMessage(),
                ];
            }

            $this->pause(400);
        }

        return $out;
    }

    /**
     * @return \Illuminate\Support\Collection<int, AmazonDataView>
     */
    private function dueRows(int $limit)
    {
        $now = now()->toDateTimeString();

        return AmazonDataView::query()
            ->where('value->PRICE_PULL_STATUS', 'pending')
            ->where('value->PRICE_PULL_DUE_AT', '<=', $now)
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    /**
     * @param  array<string, mixed>  $value
     */
    private function markPulled(string $gridSku, string $sellerSku, float $price, ?AmazonDataView $row = null, array $value = []): void
    {
        if ($row === null) {
            $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', $gridSku)));
            $row = AmazonDataView::firstOrNew(['sku' => $sku]);
            $value = is_array($row->value)
                ? $row->value
                : (json_decode($row->value ?? '{}', true) ?? []);
        }
        if (! is_array($value)) {
            $value = [];
        }

        $value['PRICE_PULL_STATUS'] = 'pulled';
        $value['PRICE_PULLED_AT'] = now()->toDateTimeString();
        $value['PRICE_PULLED_VALUE'] = $price;
        if ($sellerSku !== '') {
            $value['PRICE_PULL_SELLER_SKU'] = $sellerSku;
        }
        $row->value = $value;
        if (! $row->exists && $row->sku === null) {
            $row->sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', $gridSku)));
        }
        $row->save();
    }

    private function currentListingPrice(array $details, ?float $expectedSale = null): ?float
    {
        $your = isset($details['your_price']) ? (float) $details['your_price'] : 0;
        $sale = isset($details['sale_price']) ? (float) $details['sale_price'] : 0;

        return self::customerPrice($your > 0 ? $your : null, $sale > 0 ? $sale : null);
    }

    private function writeDatasheetPrice(string $gridSku, string $sellerSku, float $price): bool
    {
        $normGrid = strtoupper(trim(str_replace("\xc2\xa0", ' ', $gridSku)));
        $normSeller = strtoupper(trim(str_replace("\xc2\xa0", ' ', $sellerSku)));
        $compact = AmazonDatasheet::normalizeSkuForLookup($gridSku);

        $candidates = AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) use ($normGrid, $normSeller, $compact) {
                $q->whereRaw('UPPER(TRIM(REPLACE(sku, UNHEX(\'C2A0\'), \' \'))) = ?', [$normGrid]);
                if ($normSeller !== '' && $normSeller !== $normGrid) {
                    $q->orWhereRaw('UPPER(TRIM(REPLACE(sku, UNHEX(\'C2A0\'), \' \'))) = ?', [$normSeller]);
                }
                if ($compact !== '') {
                    $q->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(TRIM(COALESCE(sku,'')), UNHEX('C2A0'), ' '), ' ', '')) = ?",
                        [$compact]
                    );
                }
            })
            ->get();

        $sheet = AmazonDatasheet::pickBestForProductSku($gridSku, $candidates);
        if (! $sheet) {
            Log::warning('Amazon pushed-price pull: no amazon_datsheets row', [
                'sku' => $gridSku,
                'seller_sku' => $sellerSku,
            ]);

            return false;
        }

        $sheet->price = $price;
        $sheet->save();

        return true;
    }

    private function pause(int $delayMs): void
    {
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
}
