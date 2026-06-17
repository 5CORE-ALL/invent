<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Services\Crm\ShopifyCustomerClassifier;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ShopifyDashboardController extends Controller
{
    public function __construct(
        protected ShopifyCustomerClassifier $customerClassifier
    ) {}

    public function index(): View
    {
        return view('crm.shopify.dashboard', [
            'marketplaceChannels' => $this->customerClassifier->marketplaceChannelOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        try {
            return $this->buildDataResponse($request);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function buildDataResponse(Request $request): JsonResponse
    {
        [$start, $end] = $this->dateRange($request);

        $baseOrders = $this->filteredOrderQuery($request, $start, $end);

        $hasCancelledAt  = $this->hasCancelledAt();
        $hasFinancial    = $this->hasFinancialStatus();

        $totalOrders    = (clone $baseOrders)->count();
        $cancelledCount = $hasCancelledAt
            ? (clone $baseOrders)->whereNotNull('shopify_orders.cancelled_at')->count()
            : 0;

        $revenueQuery = (clone $baseOrders);
        if ($hasCancelledAt) {
            $revenueQuery->whereNull('shopify_orders.cancelled_at');
        }
        $activeOrderCount = $revenueQuery->count();
        $revenue = (float) (clone $revenueQuery)->sum('shopify_orders.total_price');

        // Distinct customers who actually have orders in the selected range
        $customersWithOrders = (clone $baseOrders)
            ->whereNotNull('shopify_orders.shopify_customer_id')
            ->distinct()
            ->count('shopify_orders.shopify_customer_id');

        // All-time count for the current type/channel filter (no date restriction)
        $totalCustomers = $this->filteredCustomerQuery($request)->count();
        $newCustomers   = $this->filteredCustomerQuery($request)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return response()->json([
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
            ],
            'summary' => [
                'total_orders'          => $totalOrders,
                'active_orders'         => $activeOrderCount,
                'cancelled_orders'      => $cancelledCount,
                'revenue'               => round($revenue, 2),
                'average_order_value'   => $activeOrderCount > 0 ? round($revenue / $activeOrderCount, 2) : 0,
                'total_customers'       => $totalCustomers,
                'new_customers'         => $newCustomers,
                'customers_with_orders' => $customersWithOrders,
            ],
            'trend'          => $this->orderTrend(clone $baseOrders, $hasCancelledAt),
            'customer_types' => $this->customerTypeBreakdown($request),
            'marketplaces'   => $this->marketplacePerformance(clone $baseOrders, $hasCancelledAt),
            'statuses'       => $this->statusBreakdown(clone $baseOrders, $hasFinancial),
            'top_customers'  => $this->topCustomers(clone $baseOrders, $hasCancelledAt),
            'health'         => $this->dataHealth($request, $hasCancelledAt),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function hasCancelledAt(): bool
    {
        static $v = null;
        return $v ??= Schema::hasColumn('shopify_orders', 'cancelled_at');
    }

    protected function hasFinancialStatus(): bool
    {
        static $v = null;
        return $v ??= Schema::hasColumn('shopify_orders', 'financial_status');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function dateRange(Request $request): array
    {
        $end   = Carbon::parse($request->input('end_date')   ?: now())->endOfDay();
        $start = Carbon::parse($request->input('start_date') ?: now()->subDays(29))->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    protected function filteredOrderQuery(Request $request, Carbon $start, Carbon $end)
    {
        $query = ShopifyOrder::query()
            ->leftJoin('shopify_customers as sc', 'shopify_orders.shopify_customer_id', '=', 'sc.shopify_customer_id')
            ->whereBetween('shopify_orders.order_date', [$start, $end]);

        $customerType = trim((string) $request->input('customer_type', ''));
        if ($customerType !== '') {
            if ($customerType === 'unknown') {
                $query->where(function ($q) {
                    $q->where('sc.customer_type', 'unknown')->orWhereNull('sc.customer_type');
                });
            } else {
                $query->where('sc.customer_type', $customerType);
            }
        }

        $channel = trim((string) $request->input('marketplace_channel', ''));
        if ($channel !== '') {
            $query->where('sc.marketplace_channel', $channel);
        }

        return $query;
    }

    protected function filteredCustomerQuery(Request $request)
    {
        $query = ShopifyCustomer::query();

        $customerType = trim((string) $request->input('customer_type', ''));
        if ($customerType !== '') {
            if ($customerType === 'unknown') {
                $query->where(function ($q) {
                    $q->where('customer_type', 'unknown')->orWhereNull('customer_type');
                });
            } else {
                $query->where('customer_type', $customerType);
            }
        }

        $channel = trim((string) $request->input('marketplace_channel', ''));
        if ($channel !== '') {
            $query->where('marketplace_channel', $channel);
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Report sections
    // -------------------------------------------------------------------------

    protected function orderTrend($orders, bool $hasCancelledAt): array
    {
        $revenueExpr = $hasCancelledAt
            ? 'SUM(CASE WHEN shopify_orders.cancelled_at IS NULL THEN COALESCE(shopify_orders.total_price,0) ELSE 0 END)'
            : 'SUM(COALESCE(shopify_orders.total_price,0))';

        return $orders
            ->selectRaw('DATE(shopify_orders.order_date) as bucket')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("{$revenueExpr} as revenue")
            ->groupByRaw('DATE(shopify_orders.order_date)')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($row) => [
                'date'    => (string) $row->bucket,
                'orders'  => (int) $row->orders_count,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    protected function customerTypeBreakdown(Request $request): array
    {
        return $this->filteredCustomerQuery($request)
            ->selectRaw("COALESCE(customer_type, 'unknown') as label")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw("COALESCE(customer_type, 'unknown')")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst((string) $row->label),
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    protected function marketplacePerformance($orders, bool $hasCancelledAt): array
    {
        $revenueExpr = $hasCancelledAt
            ? "SUM(CASE WHEN shopify_orders.cancelled_at IS NULL THEN COALESCE(shopify_orders.total_price,0) ELSE 0 END)"
            : "SUM(COALESCE(shopify_orders.total_price,0))";

        $channelExpr = "COALESCE(sc.marketplace_channel, CASE WHEN sc.customer_type = 'direct' THEN 'direct' ELSE 'unknown' END)";

        return $orders
            ->selectRaw("{$channelExpr} as channel")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COUNT(DISTINCT shopify_orders.shopify_customer_id) as customers_count')
            ->selectRaw("{$revenueExpr} as revenue")
            ->groupByRaw($channelExpr)
            ->orderByDesc('revenue')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'channel'   => (string) $row->channel,
                'label'     => $row->channel === 'direct'
                    ? 'Direct'
                    : ($this->customerClassifier->channelLabel((string) $row->channel) ?? ucwords(str_replace('-', ' ', (string) $row->channel))),
                'orders'    => (int) $row->orders_count,
                'customers' => (int) $row->customers_count,
                'revenue'   => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    protected function statusBreakdown($orders, bool $hasFinancial): array
    {
        $statusExpr = $hasFinancial
            ? "COALESCE(shopify_orders.financial_status, shopify_orders.order_status, 'unknown')"
            : "COALESCE(shopify_orders.order_status, 'unknown')";

        return $orders
            ->selectRaw("{$statusExpr} as label")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(COALESCE(shopify_orders.total_price,0)) as revenue')
            ->groupByRaw($statusExpr)
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label'   => ucwords(str_replace(['_', '-'], ' ', (string) $row->label)),
                'orders'  => (int) $row->total,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    protected function topCustomers($orders, bool $hasCancelledAt): array
    {
        $revenueExpr = $hasCancelledAt
            ? 'SUM(CASE WHEN shopify_orders.cancelled_at IS NULL THEN COALESCE(shopify_orders.total_price,0) ELSE 0 END)'
            : 'SUM(COALESCE(shopify_orders.total_price,0))';

        return $orders
            ->select([
                'sc.id as sc_id',
                'sc.customer_id',
                'sc.shopify_customer_id as sc_shopify_id',
                'sc.email',
                'sc.first_name',
                'sc.last_name',
                'sc.customer_type',
                'sc.marketplace_channel',
            ])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("{$revenueExpr} as revenue")
            ->selectRaw('MAX(shopify_orders.order_date) as last_order_date')
            ->whereNotNull('sc.id')
            ->groupBy([
                'sc.id',
                'sc.customer_id',
                'sc.shopify_customer_id',
                'sc.email',
                'sc.first_name',
                'sc.last_name',
                'sc.customer_type',
                'sc.marketplace_channel',
            ])
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $name = trim(implode(' ', array_filter([
                    (string) ($row->first_name ?? ''),
                    (string) ($row->last_name ?? ''),
                ])));

                return [
                    'name'               => $name !== '' ? $name : ($row->email ?: 'Shopify #'.$row->sc_shopify_id),
                    'email'              => $row->email,
                    'crm_customer_id'    => $row->customer_id,
                    'customer_type'      => $row->customer_type ?: 'unknown',
                    'marketplace_channel'=> $row->marketplace_channel,
                    'orders'             => (int) $row->orders_count,
                    'revenue'            => round((float) $row->revenue, 2),
                    'last_order_date'    => $row->last_order_date,
                ];
            })
            ->values()
            ->all();
    }

    protected function dataHealth(Request $request, bool $hasCancelledAt): array
    {
        $customers = $this->filteredCustomerQuery($request);

        return [
            'last_order_sync'         => ShopifyOrder::query()->max('last_synced_at'),
            'last_customer_sync'      => ShopifyCustomer::query()->max('last_synced_at'),
            'total_customers'         => ShopifyCustomer::query()->count(),
            'unknown_customers'       => (clone $customers)->where(function ($q) {
                $q->where('customer_type', 'unknown')->orWhereNull('customer_type');
            })->count(),
            'unlinked_customers'      => (clone $customers)->whereNull('customer_id')->count(),
            'missing_email'           => (clone $customers)->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            })->count(),
            'manual_overrides'        => (clone $customers)->where('classification_overridden', true)->count(),
            'orders_without_customer' => ShopifyOrder::query()->whereNull('shopify_customer_id')->count(),
        ];
    }
}
