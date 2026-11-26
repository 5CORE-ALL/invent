<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pending-order counts per site, taken hourly and once a day at 3:30 PM Ohio time.
 *
 * @property string $kind 'hourly' | 'daily'
 * @property \Carbon\Carbon $captured_at stored in the app timezone (UTC)
 * @property int $total_orders
 * @property array<string, array{label: string, orders: int}>|null $counts
 */
class OrderFulfillmentPendingSnapshot extends Model
{
    public const KIND_HOURLY = 'hourly';

    public const KIND_DAILY = 'daily';

    /** Ohio is on Eastern time. */
    public const TIMEZONE = 'America/New_York';

    protected $table = 'order_fulfillment_pending_snapshots';

    protected $fillable = ['kind', 'snapshot_date', 'captured_at', 'total_orders', 'counts'];

    protected $casts = [
        'snapshot_date' => 'date:Y-m-d',
        'captured_at' => 'datetime',
        'total_orders' => 'integer',
        'counts' => 'array',
    ];
}
