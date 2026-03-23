<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerFollowup extends Model
{
    protected $fillable = [
        'ticket_id', 'order_id', 'sku', 'channel_master_id', 'customer_name', 'email', 'phone',
        'issue_type', 'status', 'priority', 'followup_date', 'followup_time',
        'next_followup_at', 'assigned_executive', 'comments', 'internal_remarks', 'reference_link',
    ];

    protected $casts = [
        'followup_date' => 'date',
        'followup_time' => 'datetime:H:i',
        'next_followup_at' => 'datetime',
    ];

    /** Same rows as /all-marketplace-master (channel_master, status = active) */
    public function channelMaster(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_master_id');
    }

    public function isOverdue(): bool
    {
        if (!$this->next_followup_at || $this->status === 'Resolved') {
            return false;
        }
        return $this->next_followup_at->isPast();
    }

    /** Final public ticket id stored in DB, e.g. TKT-000042 */
    public static function assignTicketIdFromPrimaryKey(self $row): string
    {
        $ticketId = 'TKT-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT);
        if ($row->ticket_id !== $ticketId) {
            $row->ticket_id = $ticketId;
            $row->saveQuietly();
        }

        return $ticketId;
    }

    /** Temp id for first insert (unique until replaced by assignTicketIdFromPrimaryKey) */
    public static function temporaryTicketId(): string
    {
        return 'TMP-' . Str::uuid()->toString();
    }
}
