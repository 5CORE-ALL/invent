<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyInventoryLog extends Model
{
    protected $fillable = [
        'sku',
        // 'quantity_adjustment', // Column doesn't exist in DB yet
        'inventory_item_id',
        'location_id',
        'status',
        'error_message',
        'attempt',
        'max_attempts',
        'last_attempt_at',
        'succeeded_at',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'succeeded_at' => 'datetime',
    ];

    public function shouldRetry(): bool
    {
        return $this->status !== 'success' 
            && $this->attempt < $this->max_attempts;
    }

    public function incrementAttempt(): void
    {
        $this->increment('attempt');
        $this->update([
            'last_attempt_at' => now(),
            'status' => 'processing'
        ]);
    }

    public function markSuccess(): void
    {
        $this->update([
            'status' => 'success',
            'succeeded_at' => now(),
            'error_message' => null
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error
        ]);
    }
}
