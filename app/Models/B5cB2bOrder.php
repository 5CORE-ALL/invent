<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B5cB2bOrder extends Model
{
    protected $table = 'b5c_b2b_orders';

    protected $fillable = [
        'store_order_id',
        'status',
        'customer_email',
        'customer_name',
        'currency',
        'total',
        'tracking_reference',
        'ordered_at',
        'shopify_order_id',
        'shopify_imported_at',
        'payload',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'ordered_at' => 'datetime',
        'shopify_imported_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Public Business 5 Core order code (B5-0009), not the numeric store id.
     */
    public function channelOrderNumber(): string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        foreach (['order_number', 'number', 'code', 'reference'] as $key) {
            $value = strtoupper(trim((string) ($payload[$key] ?? '')));
            if (preg_match('/^B5-\d+$/', $value) === 1) {
                return $value;
            }
        }

        $id = max(0, (int) $this->store_order_id);

        return 'B5-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * B5-0009 and #9 both resolve to store order id 9.
     */
    public static function storeIdFromSearch(string $search): ?int
    {
        $search = trim($search);
        if (preg_match('/^#?B5-0*(\d+)$/i', $search, $match) === 1) {
            return (int) $match[1];
        }
        if ($search !== '' && ctype_digit($search)) {
            return (int) $search;
        }

        return null;
    }
}
