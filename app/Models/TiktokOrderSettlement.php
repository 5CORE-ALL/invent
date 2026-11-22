<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TiktokOrderSettlement extends Model
{
    protected $table = 'tiktok_order_settlements';

    protected $guarded = [];

    protected $casts = [
        'settlement_amount' => 'decimal:2',
        'revenue_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'shipping_cost_amount' => 'decimal:2',
        'statement_time' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable('tiktok_order_settlements');
    }

    /**
     * Net settlement per order (ORDER + REFUND transactions summed).
     * Orders TikTok has not settled yet are absent from the result (no statement exists for them).
     *
     * @param  iterable<string>  $orderIds
     * @return array<string, float> order_id => settlement
     */
    public static function totalsByOrder(iterable $orderIds, string $channel = 'tiktok'): array
    {
        if (! static::tableReady()) {
            return [];
        }

        $ids = [];
        foreach ($orderIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk(array_keys($ids), 1000) as $chunk) {
            $rows = static::query()
                ->where('channel', $channel)
                ->whereIn('order_id', $chunk)
                ->whereNotNull('settlement_amount')
                ->selectRaw('order_id, SUM(settlement_amount) as total')
                ->groupBy('order_id')
                ->get();
            foreach ($rows as $row) {
                $out[(string) $row->order_id] = (float) $row->total;
            }
        }

        return $out;
    }
}
