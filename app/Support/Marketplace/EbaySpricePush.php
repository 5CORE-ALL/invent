<?php

namespace App\Support\Marketplace;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\ShopifySku;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;

/**
 * eBay 1/2/3 Push Prc (same flow as Amazon): only the S PRC saved for the SKU is pushed.
 */
final class EbaySpricePush
{
    private const MAP = [
        'ebay1' => [EbayMetric::class, EbayDataView::class, EbayApiService::class],
        'ebay2' => [Ebay2Metric::class, EbayTwoDataView::class, Ebay2ApiService::class],
        'ebay3' => [Ebay3Metric::class, EbayThreeDataView::class, EbayThreeApiService::class],
    ];

    public static function supports(string $channel): bool
    {
        return isset(self::MAP[$channel]);
    }

    /**
     * Returns a block reason, or null when the push may go ahead.
     */
    public static function blockReason(?float $savedSprice, float $price, ?object $metric, ?float $inv = null): ?string
    {
        if ($price <= 0) {
            return 'Invalid price';
        }
        if ($inv !== null && $inv <= 0) {
            return 'INV 0';
        }
        if ($savedSprice === null || $savedSprice <= 0) {
            return 'No saved S PRC';
        }
        if (abs($savedSprice - $price) >= 0.005) {
            return 'Price $'.number_format($price, 2).' ≠ saved S PRC $'.number_format($savedSprice, 2);
        }
        if (! $metric || trim((string) ($metric->item_id ?? '')) === '') {
            return 'No eBay item_id';
        }
        if (EbayListingEnded::isEnded($metric->listing_status ?? null)) {
            return 'Ended listing';
        }

        return null;
    }

    public static function savedSprice(string $channel, string $sku): ?float
    {
        $viewClass = self::MAP[$channel][1] ?? null;
        if (! $viewClass) {
            return null;
        }
        $row = $viewClass::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first(['value']);
        $value = $row ? $row->value : null;
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (! is_array($value) || ! isset($value['SPRICE']) || ! is_numeric($value['SPRICE'])) {
            return null;
        }

        return round((float) $value['SPRICE'], 2);
    }

    /**
     * Shopify INV, the same INV the eBay pages show (blue badge = INV > 0). Missing SKU counts as 0.
     */
    public static function inventory(string $sku): float
    {
        $row = ShopifySku::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first(['inv']);

        return (float) ($row->inv ?? 0);
    }

    public static function metric(string $channel, string $sku): ?object
    {
        $metricClass = self::MAP[$channel][0] ?? null;

        return $metricClass ? EbayListingEnded::preferredRow($metricClass, $sku) : null;
    }

    /**
     * Live eBay Price already equal to $price (no API call needed).
     */
    public static function liveMatches(?object $metric, float $price): bool
    {
        $live = (float) ($metric->ebay_price ?? 0);

        return $live > 0 && abs($live - $price) < 0.005;
    }

    /**
     * @return array{success: bool, message: string, ebay_price?: float}
     */
    public static function push(string $channel, string $sku, float $price): array
    {
        if (! self::supports($channel)) {
            return ['success' => false, 'message' => 'Unsupported eBay channel'];
        }
        $price = round($price, 2);
        $metric = self::metric($channel, $sku);
        $reason = self::blockReason(self::savedSprice($channel, $sku), $price, $metric, self::inventory($sku));
        if ($reason !== null) {
            return ['success' => false, 'message' => 'Not pushed: '.$reason];
        }

        $api = app(self::MAP[$channel][2]);
        $res = $api->reviseFixedPriceItem((string) $metric->item_id, $price, null, $sku);
        $ok = is_array($res) && ! empty($res['success']);
        $msg = is_array($res) ? (string) ($res['message'] ?? ($ok ? 'Price updated' : 'eBay update failed')) : 'eBay update failed';
        if (! $ok && is_array($res) && ! empty($res['errors'])) {
            $msg .= ': '.(is_string($res['errors']) ? $res['errors'] : json_encode($res['errors']));
        }

        if ($ok) {
            self::storeAcceptedPrice($metric, $price);
        }

        return $ok
            ? ['success' => true, 'message' => $msg, 'ebay_price' => $price]
            : ['success' => false, 'message' => $msg];
    }

    /**
     * eBay accepted the revise, so its listing price is now $price. Store it on this SKU's metric row,
     * otherwise the page keeps the SKU blue (old Price ≠ S PRC) and re-queues it on every reload.
     */
    private static function storeAcceptedPrice(object $metric, float $price): void
    {
        if (! $metric instanceof \Illuminate\Database\Eloquent\Model) {
            return;
        }
        try {
            $metric->ebay_price = $price;
            $metric->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('eBay S PRC push: could not store accepted price', [
                'sku' => $metric->sku ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
