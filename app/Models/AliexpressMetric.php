<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AliexpressMetric extends Model
{
    use HasFactory;

    protected $table = 'aliexpress_metric';

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'l30',
        'l60',
        'order_dates',
        'last_order_date'
    ];

    protected $casts = [
        'order_dates' => 'array',
        'last_order_date' => 'datetime',
        'price' => 'decimal:2',
        'l30' => 'integer',
        'l60' => 'integer'
    ];

    public static function updateOrderMetrics($productId, $sku, $orderData, $productData)
    {
        $orderDate = Carbon::parse($orderData['gmt_create']);
        $now = Carbon::now();
        
        Log::info("Processing product ID: {$productId}, SKU: {$sku}, Order Date: {$orderDate}");
        
        $metric = static::firstOrNew([
            'product_id' => $productId,
            'sku' => $sku
        ]);
        
        $orderDates = $metric->order_dates ?? [];
        $orderKey = $orderData['order_id'] . '_' . $productId . '_' . $orderDate->toDateTimeString();
        
        // Only process if this order hasn't been recorded yet
        if (!isset($orderDates[$orderKey])) {
            $orderDates[$orderKey] = [
                'date' => $orderDate->toDateTimeString(),
                'count' => $productData['product_count'],
                'order_id' => $orderData['order_id'],
                'product_id' => $productId,
                'sku' => $sku,
                'amount' => $productData['product_unit_price']['amount']
            ];
            
            // Calculate L30 and L60
            $l30 = 0;
            $l60 = 0;
            foreach ($orderDates as $order) {
                $orderDateTime = Carbon::parse($order['date']);
                $daysDiff = $now->diffInDays($orderDateTime);
                
                if ($daysDiff <= 30) {
                    $l30 += $order['count'];
                }
                if ($daysDiff <= 60) {
                    $l60 += $order['count'];
                }
            }
            
            $metric->fill([
                'price' => $productData['product_unit_price']['amount'],
                'l30' => $l30,
                'l60' => $l60,
                'order_dates' => $orderDates,
                'last_order_date' => $orderDate
            ]);
            
            $metric->save();
        }
        
        return $metric;
    }
}