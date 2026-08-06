<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderFulfillmentDailySummary extends Model
{
    protected $table = 'sales_order_fulfillment_daily_data';

    protected $fillable = [
        'snapshot_date',
        'summary_data',
        'notes',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'summary_data' => 'array',
    ];

    public function getMetric(string $key, $default = null)
    {
        return $this->summary_data[$key] ?? $default;
    }
}
