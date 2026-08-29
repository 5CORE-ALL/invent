<?php

namespace App\Http\Controllers\Channels;

use App\Models\ShopifySku;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\VeeqoApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Sales Loss Order — all marketplace orders.
 * Same row shape as /sales-order-fulfillment All Order, without the 30-day default.
 */
class SalesLossOrderController extends SalesOrderFulfillmentController
{
    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $sloChannels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();

        $tz = self::SOF_TIMEZONE;

        return view('channels.sales_loss_order', [
            'sloChannels' => $sloChannels,
            'sloDateFrom' => now($tz)->subDays(30)->toDateString(),
            'sloDateTo' => now($tz)->toDateString(),
        ]);
    }

    /**
     * All marketplace orders (every status).
     * Same 30-day default as /sales-order-fulfillment All Order.
     */
    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->allOrdersQuery($slug), $slug),
                false,
                true
            );

            $amountTotal = 0.0;
            $channels = [];
            foreach ($rows as $row) {
                if (is_numeric($row['amount'] ?? null)) {
                    $amountTotal += (float) $row['amount'];
                }
                $slug = (string) ($row['mm_slug'] ?? '');
                if ($slug !== '') {
                    $channels[$slug] = true;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'channel_count' => count($channels),
                'amount_total' => round($amountTotal, 2),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load sales loss orders.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'amount_total' => 0,
            ], 500);
        }
    }

    /**
     * Last 7 Eastern days of marketplace orders, lowest SKU-site profit first.
     */
    public function lossMakingData(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast7Days($this->allOrdersQuery($slug), $slug),
                false,
                true
            );
            $rows = $this->sortRowsByLowestProfit($rows);

            $lossCount = 0;
            foreach ($rows as $row) {
                if ($this->rowIsLossMaking($row)) {
                    $lossCount++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'loss_count' => $lossCount,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load loss-making orders.',
                'data' => [],
                'count' => 0,
                'loss_count' => 0,
            ], 500);
        }
    }

    /**
     * Inclusive last 7 Eastern calendar days (today and the 6 days before).
     */
    protected function scopedToLast7Days(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        $tz = self::SOF_TIMEZONE;
        $from = now($tz)->subDays(6)->startOfDay();
        $to = now($tz)->endOfDay();

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function sortRowsByLowestProfit(array $rows): array
    {
        usort($rows, function (array $a, array $b) {
            $an = $this->sortableProfitValue($a);
            $bn = $this->sortableProfitValue($b);
            if ($an === null && $bn === null) {
                return 0;
            }
            if ($an === null) {
                return 1;
            }
            if ($bn === null) {
                return -1;
            }

            return $an <=> $bn;
        });

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function sortableProfitValue(array $row): ?float
    {
        if (is_numeric($row['npft_pct'] ?? null)) {
            return (float) $row['npft_pct'];
        }
        if (is_numeric($row['gpft_pct'] ?? null)) {
            return (float) $row['gpft_pct'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIsLossMaking(array $row): bool
    {
        $npft = $row['npft_pct'] ?? null;
        $gpft = $row['gpft_pct'] ?? null;

        return (is_numeric($npft) && (float) $npft < 0)
            || (is_numeric($gpft) && (float) $gpft < 0);
    }

    /**
     * Cancelled / refunded / voided / lost orders for a marketplace.
     */
    protected function lossOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            // Newegg: 4 = Voided
            'newegg' => $base->where(function (Builder $q) {
                $q->whereIn('status', ['4', 4])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%VOID%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%']);
            }),
            'temu', 'temu2' => $base->where(function (Builder $q) {
                foreach (['parent_order_status_text', 'order_status_text'] as $col) {
                    foreach (['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'] as $needle) {
                        $q->orWhereRaw("UPPER(TRIM(COALESCE({$col}, ''))) LIKE ?", [$needle]);
                    }
                }
            }),
            'doba' => $this->applyLossLikeFilter($base, 'order_status'),
            'aliexpress', 'alibaba' => $this->applyLossLikeFilter($base, 'status', ['%CLOSE%']),
            default => $this->applyLossLikeFilter($base, 'status'),
        };
    }

    /**
     * @param  list<string>  $extraNeedles
     */
    protected function applyLossLikeFilter(Builder $query, string $column, array $extraNeedles = []): Builder
    {
        $needles = array_merge(['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'], $extraNeedles);

        return $query->where(function (Builder $q) use ($column, $needles) {
            foreach ($needles as $i => $needle) {
                $sql = "UPPER(TRIM(COALESCE({$column}, ''))) LIKE ?";
                if ($i === 0) {
                    $q->whereRaw($sql, [$needle]);
                } else {
                    $q->orWhereRaw($sql, [$needle]);
                }
            }
        });
    }

    /**
     * Apply date_from / date_to only when the request sends them.
     * Unlike SOF, empty dates mean all historical rows.
     */
    protected function maybeScopeByRequestDates(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        $fromRaw = trim((string) request()->input('date_from', ''));
        $toRaw = trim((string) request()->input('date_to', ''));
        if ($fromRaw === '' && $toRaw === '') {
            return $query;
        }

        $tz = self::SOF_TIMEZONE;
        $from = $fromRaw !== ''
            ? $this->parseCaliforniaDateInput($fromRaw, now($tz)->subDays(30)->startOfDay())
            : now($tz)->subYears(20)->startOfDay();
        $to = $toRaw !== ''
            ? $this->parseCaliforniaDateInput($toRaw, now($tz)->endOfDay(), true)
            : now($tz)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * INV from Shopify plus DIL% = (shopify L30 / INV) × 100.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachInvToOrderRows(array $rows): array
    {
        $rows = parent::attachInvToOrderRows($rows);
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus(array_keys($skus));
        foreach ($rows as &$row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $shopify = $sku !== '' ? $shopifyBySku->get($sku) : null;
            $inv = (int) ($row['INV'] ?? 0);
            // Same as /amazon-tabulator-view: OV L30 = shopify_skus.quantity
            $l30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
            $row['l30'] = $l30;
            $row['dil'] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }
}
