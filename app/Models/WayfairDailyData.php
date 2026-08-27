<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WayfairDailyData extends Model
{
    use HasFactory;

    protected $table = 'wayfair_daily_data';

    protected $fillable = [
        'po_number',
        'po_date',
        'period',
        'status',
        'sku',
        'quantity',
        'unit_price',
        'total_price',
        'estimated_ship_date',
        'customer_name',
        'customer_address1',
        'customer_address2',
        'customer_city',
        'customer_state',
        'customer_postal_code',
        'customer_country',
        'customer_phone',
        'ship_speed',
        'carrier_code',
        'warehouse_id',
        'warehouse_name',
        'event_id',
        'event_type',
        'event_name',
        'packing_slip_url',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'raw_payload',
    ];

    protected $casts = [
        'po_date' => 'date',
        'estimated_ship_date' => 'date',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'pushed_to_shopify_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function getOrderIdAttribute(): ?string
    {
        $po = $this->attributes['po_number'] ?? null;

        return $po !== null && $po !== '' ? (string) $po : null;
    }

    public function getOrderDateAttribute()
    {
        return $this->po_date;
    }

    public function getAmountAttribute()
    {
        if ($this->total_price !== null) {
            return $this->total_price;
        }
        if ($this->unit_price !== null) {
            return (float) $this->unit_price * max(1, (int) ($this->quantity ?? 1));
        }

        return null;
    }

    public function getDisplayTitleAttribute(): string
    {
        $sku = trim((string) ($this->attributes['sku'] ?? ''));

        return $sku !== '' ? $sku : 'Wayfair item';
    }

    public function getStockAttribute(): int
    {
        return max(1, (int) ($this->attributes['quantity'] ?? 1));
    }
}
