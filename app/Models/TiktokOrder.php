<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TiktokOrder extends Model
{
    public const TZ = 'America/Los_Angeles';

    protected $table = 'tiktok_orders';

    protected $fillable = [
        'order_id',
        'line_item_id',
        'order_status',
        'line_status',
        'seller_sku',
        'product_id',
        'sku_id',
        'product_name',
        'quantity',
        'original_price',
        'sale_price',
        'seller_discount',
        'platform_discount',
        'currency',
        'order_amount',
        'fulfillment_type',
        'delivery_type',
        'shipping_provider',
        'buyer_nickname',
        'shop_region',
        'order_created_at',
        'order_updated_at',
        'rts_time',
        'delivery_time',
        'collection_time',
        'raw_json',
        'fetched_at',
        'line_item_id',
        'shopify_order_id',
        'import_status',
        'pushed_to_shopify_at',
        'tracking_pushed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'original_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'seller_discount' => 'decimal:2',
        'platform_discount' => 'decimal:2',
        'order_amount' => 'decimal:2',
        'order_created_at' => 'datetime',
        'order_updated_at' => 'datetime',
        'rts_time' => 'datetime',
        'delivery_time' => 'datetime',
        'collection_time' => 'datetime',
        'fetched_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'tracking_pushed_at' => 'datetime',
        'raw_json' => 'array',
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable('tiktok_orders');
    }

    public static function activeQuery(): Builder
    {
        return static::query()->where(function ($q) {
            $q->whereNull('order_status')
                ->orWhereRaw("UPPER(order_status) NOT IN ('CANCELLED', 'CANCELED')");
        });
    }

    /**
     * order_created_at is stored as UTC wall-clock (from API create_time).
     * Return latest as California Carbon.
     */
    public static function latestCreatedAt(): ?Carbon
    {
        if (! static::tableReady()) {
            return null;
        }

        $raw = static::query()->whereNotNull('order_created_at')->max('order_created_at');
        if (! $raw) {
            return null;
        }

        return Carbon::parse($raw, 'UTC')->timezone(self::TZ);
    }

    /**
     * Latest non-cancelled order as California Carbon.
     * Use this to anchor Y Sales so a cancelled row does not skip the last real sales day.
     */
    public static function latestActiveCreatedAt(): ?Carbon
    {
        if (! static::tableReady()) {
            return null;
        }

        $raw = static::activeQuery()->whereNotNull('order_created_at')->max('order_created_at');
        if (! $raw) {
            return null;
        }

        return Carbon::parse($raw, 'UTC')->timezone(self::TZ);
    }

    /**
     * Last N calendar days in California ending today (CA), as CA Carbon bounds.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function californiaDaysWindow(int $days = 30, ?Carbon $asOfCa = null): array
    {
        $asOf = ($asOfCa ?? Carbon::now(self::TZ))->timezone(self::TZ);
        $end = $asOf->copy()->endOfDay();
        $start = $asOf->copy()->subDays(max(1, $days) - 1)->startOfDay();

        return [$start, $end];
    }

    /**
     * Convert CA (or any) bounds to UTC datetime strings for querying order_created_at.
     *
     * @return array{0: string, 1: string}
     */
    public static function toUtcRange(Carbon $start, Carbon $end): array
    {
        return [
            $start->copy()->timezone('UTC')->format('Y-m-d H:i:s'),
            $end->copy()->timezone('UTC')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Sold qty by UPPER(seller_sku) for last N California calendar days (default 30).
     *
     * @return array<string, int>
     */
    public static function soldQtyL30(?array $skusUpper = null, int $days = 30): array
    {
        [$start, $end] = static::californiaDaysWindow($days);

        return static::soldQtyBySku($start, $end, $skusUpper);
    }

    /**
     * Sold qty by UPPER(seller_sku) between dates.
     * $start/$end may be CA or any TZ — compared in UTC against stored create times.
     *
     * @return array<string, int>
     */
    public static function soldQtyBySku(Carbon $start, Carbon $end, ?array $skusUpper = null): array
    {
        if (! static::tableReady()) {
            return [];
        }

        [$startUtc, $endUtc] = static::toUtcRange($start, $end);

        $q = static::activeQuery()
            ->whereBetween('order_created_at', [$startUtc, $endUtc])
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '');

        if ($skusUpper !== null && $skusUpper !== []) {
            $q->whereIn(\DB::raw('UPPER(TRIM(seller_sku))'), array_values(array_unique(array_map(
                fn ($s) => strtoupper(trim((string) $s)),
                $skusUpper
            ))));
        }

        $rows = $q->selectRaw('UPPER(TRIM(seller_sku)) as u_sku, SUM(quantity) as total_sold')
            ->groupByRaw('UPPER(TRIM(seller_sku))')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if (! empty($row->u_sku)) {
                $out[(string) $row->u_sku] = (int) $row->total_sold;
            }
        }

        return $out;
    }

    /**
     * Line sales (sale_price * quantity) between dates (any TZ → UTC).
     */
    public static function salesAmountBetween(Carbon $start, Carbon $end): float
    {
        if (! static::tableReady()) {
            return 0.0;
        }

        [$startUtc, $endUtc] = static::toUtcRange($start, $end);

        return (float) (static::activeQuery()
            ->whereBetween('order_created_at', [$startUtc, $endUtc])
            ->selectRaw('SUM(COALESCE(sale_price,0) * quantity) as total')
            ->value('total') ?? 0);
    }

    /**
     * Distinct order count between dates (any TZ → UTC).
     */
    public static function orderCountBetween(Carbon $start, Carbon $end): int
    {
        if (! static::tableReady()) {
            return 0;
        }

        [$startUtc, $endUtc] = static::toUtcRange($start, $end);

        return (int) static::activeQuery()
            ->whereBetween('order_created_at', [$startUtc, $endUtc])
            ->distinct('order_id')
            ->count('order_id');
    }

    /**
     * Active line rows for a California day window (for sales pages).
     */
    public static function linesInWindow(Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        if (! static::tableReady()) {
            return collect();
        }

        [$startUtc, $endUtc] = static::toUtcRange($start, $end);

        return static::activeQuery()
            ->whereBetween('order_created_at', [$startUtc, $endUtc])
            ->orderByDesc('order_created_at')
            ->get();
    }
}
