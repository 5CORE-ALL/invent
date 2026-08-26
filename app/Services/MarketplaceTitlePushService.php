<?php

namespace App\Services;

use App\Models\AliexpressMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Services\Support\Concerns\HandlesMarketplaceApiExceptions;
use App\Services\Support\MarketplaceCharacterLimits;

/**
 * Centralized Title Master API push (Shopify reference: credential guard, logging, structured result).
 *
 * @return array{success: bool, message: string, endpoint?: string|null}
 */
class MarketplaceTitlePushService
{
    use HandlesMarketplaceApiExceptions;

  /** @var callable|null */
    private $dobaPusher;

    public function __construct(?callable $dobaPusher = null)
    {
        $this->dobaPusher = $dobaPusher;
    }

    /**
     * @return array{success: bool, message: string, endpoint?: string|null}
     */
    public function push(string $marketplace, string $sku, string $title, ?string $titleType = null): array
    {
        $marketplace = strtolower(trim($marketplace));
        $sku = trim($sku);
        $title = MarketplaceCharacterLimits::truncateTitle($title, $marketplace, $titleType);

        if ($sku === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.', 'endpoint' => null];
        }

        $endpoint = null;

        try {
            return match ($marketplace) {
                'amazon' => $this->wrapArray(
                    app(AmazonSpApiService::class)->updateAmazonTitle($sku, $title),
                    'AmazonSpApiService::updateAmazonTitle'
                ),
                'temu' => $this->wrapArray(
                    app(TemuApiService::class)->updateTitle($sku, $title),
                    'TemuApiService::updateTitle'
                ),
                'temu2' => $this->wrapArray(
                    app(Temu2ApiService::class)->updateTitle($sku, $title),
                    'Temu2ApiService::updateTitle'
                ),
                'reverb' => $this->wrapArray(
                    app(ReverbApiService::class)->updateTitle($sku, $title),
                    'ReverbApiService::updateTitle'
                ),
                'wayfair' => $this->wrapArray(
                    app(WayfairApiService::class)->updateTitle($sku, $title),
                    'WayfairApiService::updateTitle'
                ),
                'walmart' => $this->wrapArray(
                    app(WalmartService::class)->updateTitle($sku, $title),
                    'WalmartService::updateTitle'
                ),
                'shopify', 'shopify_main' => $this->wrapBool(
                    app(ShopifyApiService::class)->updateTitle($sku, $title),
                    'ShopifyApiService::updateTitle',
                    'Main Shopify title update failed.'
                ),
                'shopify_pls' => $this->wrapBool(
                    app(ShopifyPLSApiService::class)->updateTitle($sku, $title),
                    'ShopifyPLSApiService::updateTitle',
                    'Shopify PLS title update failed.'
                ),
                'doba' => $this->pushDoba($sku, $title),
                'ebay', 'ebay1' => $this->pushEbay($sku, $title, 1),
                'ebay2' => $this->pushEbay($sku, $title, 2),
                'ebay3' => $this->pushEbay($sku, $title, 3),
                'macy', 'macys' => $this->wrapArray(
                    app(MacysApiService::class)->updateTitle($sku, $title),
                    'MacysApiService::updateTitle'
                ),
                'bestbuy' => $this->wrapArray(
                    app(BestBuyApiService::class)->updateTitle($sku, $title),
                    'BestBuyApiService::updateTitle'
                ),
                'newegg' => $this->wrapArray(
                    app(NeweggApiService::class)->updateTitle($sku, $title),
                    'NeweggApiService::updateTitle'
                ),
                'topdawg' => $this->wrapArray(
                    app(TopDawgApiService::class)->updateTitle($sku, $title),
                    'TopDawgApiService::updateTitle'
                ),
                'shopify_b5c' => $this->wrapBool(
                    app(\App\Services\ShopifyB5CApiService::class)->updateTitle($sku, $title),
                    'ShopifyB5CApiService::updateTitle',
                    'Business 5Core Shopify title update failed.'
                ),
                'purchasing_power' => $this->wrapArray(
                    app(PurchasingPowerApiService::class)->updateTitle($sku, $title),
                    'PurchasingPowerApiService::updateTitle'
                ),
                'alibaba' => $this->pushAlibaba($sku, $title),
                'faire' => $this->wrapArray(
                    app(FaireService::class)->updateTitle($sku, $title),
                    'FaireService::updateTitle'
                ),
                'shein' => $this->wrapArray(
                    app(SheinApiService::class)->updateTitle($sku, $title),
                    'SheinApiService::updateTitle'
                ),
                'aliexpress' => $this->pushAliexpress($sku, $title),
                'tiktok' => $this->wrapArray(
                    app(TikTokShopService::class)->updateTitle($sku, $title),
                    'TikTokShopService::updateTitle'
                ),
                'tiktok2' => $this->wrapArray(
                    app(TikTok2ShopService::class)->updateTitle($sku, $title),
                    'TikTok2ShopService::updateTitle'
                ),
                default => ['success' => false, 'message' => "Title API push not supported for marketplace: {$marketplace}", 'endpoint' => null],
            };
        } catch (\Throwable $e) {
            return array_merge(
                $this->handleMarketplaceThrowable('Title push', $sku, $e, ['marketplace' => $marketplace]),
                ['endpoint' => $endpoint]
            );
        }
    }

    /**
     * @param  array{success?: bool, message?: string}|mixed  $res
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function wrapArray(mixed $res, string $endpoint): array
    {
        if (! is_array($res)) {
            return [
                'success' => (bool) $res,
                'message' => $res ? 'OK' : 'Update failed',
                'endpoint' => $endpoint,
            ];
        }

        return [
            'success' => (bool) ($res['success'] ?? false),
            'message' => (string) ($res['message'] ?? (($res['success'] ?? false) ? 'OK' : 'Unknown error')),
            'endpoint' => $endpoint,
        ];
    }

    /**
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function wrapBool(bool $ok, string $endpoint, string $failMessage): array
    {
        return [
            'success' => $ok,
            'message' => $ok ? 'OK' : $failMessage,
            'endpoint' => $endpoint,
        ];
    }

    /**
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function pushEbay(string $sku, string $title, int $account): array
    {
        $metricClass = match ($account) {
            2 => Ebay2Metric::class,
            3 => Ebay3Metric::class,
            default => EbayMetric::class,
        };
        $serviceClass = match ($account) {
            2 => Ebay2ApiService::class,
            3 => EbayThreeApiService::class,
            default => EbayApiService::class,
        };
        $endpoint = $serviceClass.'::updateTitle';

        $metric = $metricClass::query()
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first();

        if (! $metric || ! $metric->item_id) {
            return [
                'success' => false,
                'message' => "eBay {$account} listing not found for this SKU.",
                'endpoint' => $endpoint,
            ];
        }

        $res = app($serviceClass)->updateTitle($metric->item_id, $title, (string) ($metric->sku ?? ''));

        return $this->wrapArray($res, $endpoint);
    }

    /**
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function pushDoba(string $sku, string $title): array
    {
        $endpoint = 'DobaApiService::updateTitle';

        if (app(DobaApiService::class)->updateTitle($sku, $title)) {
            return ['success' => true, 'message' => 'OK', 'endpoint' => $endpoint];
        }

        if ($this->dobaPusher !== null) {
            $ok = ($this->dobaPusher)($sku, $title);

            return [
                'success' => (bool) $ok,
                'message' => $ok ? 'OK' : 'Doba title update failed (legacy fallback).',
                'endpoint' => 'DobaApiService::updateTitle (legacy)',
            ];
        }

        return ['success' => false, 'message' => 'Doba title update failed.', 'endpoint' => $endpoint];
    }

    /**
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function pushAliexpress(string $sku, string $title): array
    {
        $endpoint = 'AliExpressApiService::updateTitle';
        $row = AliexpressMetric::query()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->first();

        $productId = $row?->product_id;
        if (! $productId) {
            return [
                'success' => false,
                'message' => 'AliExpress product_id not found for SKU. Sync aliexpress_metric first.',
                'endpoint' => $endpoint,
            ];
        }

        return $this->wrapArray(
            app(AliExpressApiService::class)->updateTitle((string) $productId, $title),
            $endpoint
        );
    }

    /**
     * @return array{success: bool, message: string, endpoint: string}
     */
    private function pushAlibaba(string $sku, string $title): array
    {
        $endpoint = 'AlibabaApiService::updateTitle';
        $productId = $sku;

        if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_metrics')) {
            $row = \Illuminate\Support\Facades\DB::table('alibaba_metrics')
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                ->first();
            if ($row && ! empty($row->product_id)) {
                $productId = (string) $row->product_id;
            }
        }

        return $this->wrapArray(
            app(AlibabaApiService::class)->updateTitle($productId, $title),
            $endpoint
        );
    }
}
