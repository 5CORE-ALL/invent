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
 * Cancelled Orders — marketplace orders whose status is cancelled only.
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

            $rows = $this->presentCancelledRows($this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->cancelledOrdersQuery($slug), $slug),
                true,
                true
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
     * Marketplace orders whose stored status text contains cancel.
     */
    protected function cancelledOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'amazon' => $this->applyAmazonCancelledFilter($base),
            'temu', 'temu2' => $base->where(function (Builder $q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(parent_order_status_text, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(order_status_text, ''))) LIKE ?", ['%CANCEL%']);
            }),
            'doba', 'tiktok', 'tiktok2' => $this->applyCancelledLikeFilter($base, 'order_status'),
            default => $this->applyCancelledLikeFilter($base, 'status'),
        };
    }

    protected function applyCancelledLikeFilter(Builder $query, string $column): Builder
    {
        $table = $query->getModel()->getTable();
        if (! Schema::hasColumn($table, $column)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw("UPPER(TRIM(COALESCE(`{$column}`, ''))) LIKE ?", ['%CANCEL%']);
    }

    protected function applyAmazonCancelledFilter(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            if (Schema::hasColumn($table, 'status')) {
                $q->whereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%']);
            }
            if (Schema::hasColumn($table, 'raw_data')) {
                $q->orWhereRaw(
                    "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.OrderStatus')), ''))) LIKE ?",
                    ['%CANCEL%']
                );
            }
        });
    }

    /**
     * Drop non-cancel rows and put the marketplace cancel status back on the label.
     * collectOrderRows overwrites status_label with carrier text (Delivered, In Transit).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function presentCancelledRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! $this->orderRowIsCancelledForPage($row)) {
                continue;
            }
            $raw = trim((string) ($row['status'] ?? ''));
            $row['status_label'] = $raw !== '' ? $raw : 'Cancelled';
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Keep a row only when the marketplace order status is cancelled.
     * Ignore shipment overlays and refund/void flags.
     *
     * @param  array<string, mixed>  $row
     */
    protected function orderRowIsCancelledForPage(array $row): bool
    {
        $status = $this->normalizedCancelNeedle((string) ($row['status'] ?? ''));
        if ($status !== '' && str_contains($status, 'CANCEL')) {
            return true;
        }

        if ($status !== '') {
            return false;
        }

        $label = $this->normalizedCancelNeedle((string) ($row['status_label'] ?? ''));

        return $label !== '' && str_contains($label, 'CANCEL');
    }

    protected function normalizedCancelNeedle(string $raw): string
    {
        return strtoupper(str_replace(['-', ' '], '_', trim($raw)));
    }
}
