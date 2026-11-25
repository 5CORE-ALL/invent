<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\AmazonOrder;
use App\Models\BestBuyOrderMetric;
use App\Models\DobaDailyData;
use App\Models\Ebay1OrderMetric;
use App\Models\Ebay2OrderMetric;
use App\Models\Ebay3OrderMetric;
use App\Models\FaireOrderMetric;
use App\Models\MacyOrderMetric;
use App\Models\NeweggOrderMetric;
use App\Models\PurchasingPowerSale;
use App\Models\ReverbOrderMetric;
use App\Models\SheinOrderMetric;
use App\Models\Temu2Order;
use App\Models\TemuOrder;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Models\TopDawgOrderMetric;
use App\Models\WayfairDailyData;
use App\Services\ShipmentTrackingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Second attempt at a shipment status: ask the marketplace the order came from.
 *
 * The carrier lookup (USPS / UPS / FedEx / GOFO) is tried first. When it gives nothing,
 * the order is read from the marketplace: first what we already stored, then, when allowed, a live
 * fetch of that one order through the channel's own API. Only a status the marketplace itself calls
 * delivered (or in transit / out for delivery) is used. "Shipped" and "Fulfilled" only say a label
 * was bought, so they never turn into a tracking status.
 *
 * eBay's order API has no delivered state, so eBay orders return null here.
 */
class ChannelOrderDeliveryStatus
{
    /** @var array<string, class-string> */
    protected const DETAIL_SERVICES = [
        'ebay1' => Ebay1OrderDetailService::class,
        'ebay2' => Ebay2OrderDetailService::class,
        'ebay3' => Ebay3OrderDetailService::class,
        'amazon' => AmazonOrderDetailService::class,
        'temu' => TemuOrderDetailService::class,
        'temu2' => Temu2OrderDetailService::class,
        'newegg' => NeweggOrderDetailService::class,
        'reverb' => ReverbOrderDetailService::class,
        'aliexpress' => AliexpressOrderDetailService::class,
        'alibaba' => AlibabaOrderDetailService::class,
        'faire' => FaireOrderDetailService::class,
        'shein' => SheinOrderDetailService::class,
        'bestbuy' => BestBuyOrderDetailService::class,
        'macy' => MacyOrderDetailService::class,
        'topdawg' => TopDawgOrderDetailService::class,
        'wayfair' => WayfairOrderDetailService::class,
        'doba' => DobaOrderDetailService::class,
        'purchasingpower' => PurchasingPowerOrderDetailService::class,
    ];

    /**
     * slug => [model, order id columns, status columns]
     *
     * @var array<string, array{0: class-string, 1: list<string>, 2: list<string>}>
     */
    protected const SOURCES = [
        'ebay1' => [Ebay1OrderMetric::class, ['order_id'], ['status']],
        'ebay2' => [Ebay2OrderMetric::class, ['order_id'], ['status']],
        'ebay3' => [Ebay3OrderMetric::class, ['order_id'], ['status']],
        'amazon' => [AmazonOrder::class, ['amazon_order_id'], ['status']],
        'temu' => [TemuOrder::class, ['parent_order_sn', 'order_sn'], ['parent_order_status_text', 'order_status_text']],
        'temu2' => [Temu2Order::class, ['parent_order_sn', 'order_sn'], ['parent_order_status_text', 'order_status_text']],
        'newegg' => [NeweggOrderMetric::class, ['order_id'], ['status']],
        'reverb' => [ReverbOrderMetric::class, ['order_id'], ['status']],
        'aliexpress' => [AliexpressOrderMetric::class, ['order_id'], ['status']],
        'alibaba' => [AlibabaOrderMetric::class, ['order_id'], ['status']],
        'faire' => [FaireOrderMetric::class, ['order_id'], ['status']],
        'shein' => [SheinOrderMetric::class, ['order_id'], ['status']],
        'bestbuy' => [BestBuyOrderMetric::class, ['order_id', 'channel_order_id'], ['status']],
        'macy' => [MacyOrderMetric::class, ['order_id', 'channel_order_id'], ['status']],
        'topdawg' => [TopDawgOrderMetric::class, ['order_id', 'order_number'], ['status']],
        'wayfair' => [WayfairDailyData::class, ['po_number'], ['status']],
        'doba' => [DobaDailyData::class, ['platform_order_no', 'order_no'], ['order_status']],
        'purchasingpower' => [PurchasingPowerSale::class, ['order_number', 'order_id'], ['status']],
        'tiktok' => [TiktokOrder::class, ['order_id'], ['order_status', 'line_status']],
        'tiktok2' => [Tiktok2Order::class, ['order_id'], ['order_status', 'line_status']],
    ];

    /** Live API calls made by this process, so one request or sync run cannot spend minutes on them. */
    protected int $liveCalls = 0;

    public function __construct(protected int $maxLiveCalls = 6)
    {
    }

    public function setMaxLiveCalls(int $max): void
    {
        $this->maxLiveCalls = max(0, $max);
    }

    /**
     * @return array{status: string, detail: string, source: string}|null
     */
    public function lookup(string $slug, string $orderId, bool $allowLive = true): ?array
    {
        $slug = strtolower(trim($slug));
        $orderId = trim($orderId);
        if ($slug === '' || $orderId === '' || ! isset(self::SOURCES[$slug])) {
            return null;
        }

        $stored = $this->fromStoredOrder($slug, $orderId);
        if ($stored !== null) {
            return $stored;
        }

        if (! $allowLive || $this->liveCalls >= $this->maxLiveCalls) {
            return null;
        }
        $serviceClass = self::DETAIL_SERVICES[$slug] ?? null;
        if ($serviceClass === null || ! class_exists($serviceClass)) {
            return null;
        }

        try {
            $service = app($serviceClass);
            if (! method_exists($service, 'fetchAndPersistOrderDetail')) {
                return null;
            }
            $this->liveCalls++;
            $res = $service->fetchAndPersistOrderDetail($orderId);
            if (is_array($res) && empty($res['success'])) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::info('ChannelOrderDeliveryStatus live fetch failed', [
                'slug' => $slug,
                'order' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->fromStoredOrder($slug, $orderId, true);
    }

    /**
     * @return array{status: string, detail: string, source: string}|null
     */
    protected function fromStoredOrder(string $slug, string $orderId, bool $fresh = false): ?array
    {
        [$model, $idColumns, $statusColumns] = self::SOURCES[$slug];

        try {
            $table = (new $model())->getTable();
            if (! Schema::hasTable($table)) {
                return null;
            }
            $query = $model::query()->where(function ($q) use ($idColumns, $table, $orderId): void {
                foreach ($idColumns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $q->orWhere($column, $orderId);
                    }
                }
            });
            $rows = $query->limit(25)->get();
        } catch (\Throwable $e) {
            return null;
        }

        $best = null;
        foreach ($rows as $row) {
            foreach ($statusColumns as $column) {
                $text = trim((string) ($row->{$column} ?? ''));
                $mapped = $this->statusFromMarketplaceText($text);
                if ($mapped === null) {
                    continue;
                }
                if ($best === null || $this->rank($mapped) > $this->rank($best['status'])) {
                    $best = [
                        'status' => $mapped,
                        'detail' => 'Marketplace order status: '.$text.($fresh ? ' (live)' : ''),
                        'source' => $slug,
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * Only words that really mean "the buyer has it, or it is on the way".
     */
    public function statusFromMarketplaceText(string $text): ?string
    {
        $key = strtolower(str_replace([' ', '-', '_'], '', trim($text)));
        if ($key === '') {
            return null;
        }

        if (in_array($key, [
            'delivered', 'completed', 'complete', 'received', 'finish', 'finished',
            'buyeracceptgoods', 'tradefinished', 'orderdelivered', 'deliveredtobuyer',
        ], true)) {
            return ShipmentTrackingService::STATUS_DELIVERED;
        }
        if ($key === 'outfordelivery') {
            return ShipmentTrackingService::STATUS_OUT_FOR_DELIV;
        }
        if (in_array($key, ['intransit', 'ontheway'], true)) {
            return ShipmentTrackingService::STATUS_IN_TRANSIT;
        }

        return null;
    }

    protected function rank(string $status): int
    {
        return match ($status) {
            ShipmentTrackingService::STATUS_DELIVERED => 3,
            ShipmentTrackingService::STATUS_OUT_FOR_DELIV => 2,
            ShipmentTrackingService::STATUS_IN_TRANSIT => 1,
            default => 0,
        };
    }
}
