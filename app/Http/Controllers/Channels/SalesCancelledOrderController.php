<?php

namespace App\Http\Controllers\Channels;

use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\VeeqoApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Cancelled Orders — marketplace cancelled / refunded / voided orders.
 * Same row shape as /sales-order-fulfillment All Order.
 */
class SalesCancelledOrderController extends SalesOrderFulfillmentController
{
    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $scoChannels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();

        $tz = self::SOF_TIMEZONE;

        return view('channels.sales_cancelled_order', [
            'scoChannels' => $scoChannels,
            'scoDateFrom' => now($tz)->subDays(30)->toDateString(),
            'scoDateTo' => now($tz)->toDateString(),
        ]);
    }

    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->cancelledOrdersQuery($slug), $slug),
                true,
                true
            );
            $rows = array_values(array_filter(
                $rows,
                fn (array $row) => $this->orderRowIsCancelledForPage($row)
            ));

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
                'message' => 'Failed to load cancelled orders.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'amount_total' => 0,
            ], 500);
        }
    }

    /**
     * Marketplace orders whose status or payload looks cancelled / refunded / voided.
     */
    protected function cancelledOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'ebay1', 'ebay2', 'ebay3' => $this->applyEbayCancelledFilter($base),
            'amazon' => $this->applyAmazonCancelledFilter($base),
            'newegg' => $base->where(function (Builder $q) {
                $q->whereIn('status', ['4', 4])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%VOID%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%']);
            }),
            'temu', 'temu2' => $base->where(function (Builder $q) {
                foreach (['parent_order_status_text', 'order_status_text'] as $col) {
                    foreach (['%CANCEL%', '%REFUND%', '%VOID%'] as $needle) {
                        $q->orWhereRaw("UPPER(TRIM(COALESCE({$col}, ''))) LIKE ?", [$needle]);
                    }
                }
            }),
            'doba' => $this->applyCancelledLikeFilter($base, 'order_status'),
            'tiktok', 'tiktok2' => $this->applyCancelledLikeFilter($base, 'order_status'),
            default => $this->applyCancelledLikeFilter($base, 'status'),
        };
    }

    protected function applyCancelledLikeFilter(Builder $query, string $column): Builder
    {
        $table = $query->getModel()->getTable();
        if (! Schema::hasColumn($table, $column)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($column) {
            foreach (['%CANCEL%', '%REFUND%', '%VOID%'] as $i => $needle) {
                $sql = "UPPER(TRIM(COALESCE(`{$column}`, ''))) LIKE ?";
                if ($i === 0) {
                    $q->whereRaw($sql, [$needle]);
                } else {
                    $q->orWhereRaw($sql, [$needle]);
                }
            }
        });
    }

    protected function applyEbayCancelledFilter(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            if (Schema::hasColumn($table, 'status')) {
                $q->whereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%VOID%']);
            }
            if (Schema::hasColumn($table, 'import_status')) {
                $q->orWhereRaw("UPPER(TRIM(COALESCE(import_status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(import_status, ''))) LIKE ?", ['%REFUND%']);
            }
            if (Schema::hasColumn($table, 'raw_payload')) {
                $q->orWhereRaw(
                    "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.cancelStatus.cancelState')), ''))) IN (?, ?, ?, ?)",
                    ['CANCELED', 'CANCELLED', 'CANCEL_REQUESTED', 'CANCELLATION_REQUESTED']
                )->orWhereRaw(
                    "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.orderPaymentStatus')), JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.paymentSummary.paymentStatus')), ''))) IN (?, ?)",
                    ['FULLY_REFUNDED', 'REFUNDED']
                );
            }
        });
    }

    protected function applyAmazonCancelledFilter(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            if (Schema::hasColumn($table, 'status')) {
                $q->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['CANCELED', 'CANCELLED'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%']);
            }
            if (Schema::hasColumn($table, 'raw_data')) {
                $q->orWhereRaw(
                    "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.OrderStatus')), ''))) IN (?, ?)",
                    ['CANCELED', 'CANCELLED']
                );
            }
        });
    }

    /**
     * Keep cancelled / refunded / voided rows after collect (payload + status).
     *
     * @param  array<string, mixed>  $row
     */
    protected function orderRowIsCancelledForPage(array $row): bool
    {
        if ($this->orderRowIsCancelled($row)) {
            return true;
        }

        foreach (['status', 'status_label', 'import_status'] as $key) {
            $u = strtoupper(str_replace(['-', ' '], '_', trim((string) ($row[$key] ?? ''))));
            if ($u === '') {
                continue;
            }
            if (str_contains($u, 'REFUND') || str_contains($u, 'VOID')) {
                return true;
            }
        }

        return false;
    }
}
