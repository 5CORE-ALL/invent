<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundRecord extends Model
{
    protected $fillable = [
        'sku', 'qty', 'refund_amt', 'reason', 'comment', 'person_responsible', 'supplier_id',
        'order_id', 'channel_master_id', 'created_by', 'is_archived',
    ];

    protected $casts = [
        'qty' => 'integer',
        'refund_amt' => 'decimal:2',
        'is_archived' => 'boolean',
    ];

    public function editHistory(): HasMany
    {
        return $this->hasMany(RefundRecordEditHistory::class, 'refund_record_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function channelMaster(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_master_id');
    }
}
