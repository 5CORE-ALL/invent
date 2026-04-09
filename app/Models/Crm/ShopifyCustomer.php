<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopifyCustomer extends Model
{
    protected $fillable = [
        'shopify_customer_id',
        'customer_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'sync_status',
        'raw_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ShopifyOrder::class, 'shopify_customer_id', 'shopify_customer_id');
    }
}
