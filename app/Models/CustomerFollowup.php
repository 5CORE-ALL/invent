<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\User;

class CustomerFollowup extends Model
{
    protected $fillable = [
        'ticket_id', 'order_id', 'sku', 'channel_master_id', 'customer_name', 'email', 'phone',
        'issue_type', 'status', 'priority', 'followup_date', 'followup_time',
        'next_followup_at', 'assigned_executive', 'original_executive', 'executive_history',
        'created_by_user_id',
        'comments', 'internal_remarks', 'reference_link',
        'image_path',
        'resolved_at',
    ];

    protected $casts = [
        'followup_date' => 'date',
        'followup_time' => 'datetime:H:i',
        'next_followup_at' => 'datetime',
        'resolved_at' => 'datetime',
        'executive_history' => 'array',
    ];

    /**
     * Record an executive touching this row. Idempotent for noisy saves: if
     * the most recent entry has the same {name, action} tuple we just bump
     * its timestamp instead of appending a duplicate row.
     *
     * `action` is free-form but the controller currently writes one of:
     *   - 'created'        : initial save in store()
     *   - 'updated'        : full-form save via update() that did not change status
     *   - 'status_changed' : status moved to something other than Resolved
     *   - 'resolved'       : status moved to Resolved ("marked done")
     *
     * Optional `$extra['status']` stores the status that was set, so the
     * history tooltip can say who marked the ticket done.
     *
     * Caller is responsible for persisting the model (->save()) afterwards.
     */
    public function appendExecutiveHistoryEntry(string $name, string $action, ?Carbon $at = null, array $extra = []): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $status = trim((string) ($extra['status'] ?? ''));
        $history = is_array($this->executive_history) ? $this->executive_history : [];
        $atIso = ($at ?? Carbon::now())->toIso8601String();

        $lastIdx = count($history) - 1;
        $lastStatus = $lastIdx >= 0 ? trim((string) ($history[$lastIdx]['status'] ?? '')) : '';
        if ($lastIdx >= 0
            && (string) ($history[$lastIdx]['name'] ?? '') === $name
            && (string) ($history[$lastIdx]['action'] ?? '') === $action
            && $lastStatus === $status
        ) {
            $history[$lastIdx]['at'] = $atIso;
            if ($status !== '') {
                $history[$lastIdx]['status'] = $status;
            }
        } else {
            $entry = [
                'name'   => $name,
                'at'     => $atIso,
                'action' => $action,
            ];
            if ($status !== '') {
                $entry['status'] = $status;
            }
            $history[] = $entry;
        }

        $this->executive_history = $history;
    }

    /**
     * Which history row is the "marked done" event for a Resolved ticket.
     *
     * Prefers an explicit `resolved` action (or a stored status of Resolved).
     * Older rows only stored `status_changed` / `updated`, so when nothing
     * explicit exists we use the entry written in the same moment as
     * `resolved_at` (within 10 minutes).
     */
    public static function markedDoneHistoryIndex(array $history, ?Carbon $resolvedAt, ?string $currentStatus): ?int
    {
        if ($currentStatus !== 'Resolved' || $history === []) {
            return null;
        }

        $lastExplicit = null;
        foreach ($history as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $action = strtolower(trim((string) ($entry['action'] ?? '')));
            $status = trim((string) ($entry['status'] ?? ''));
            if ($action === 'resolved' || strcasecmp($status, 'Resolved') === 0) {
                $lastExplicit = $i;
            }
        }
        if ($lastExplicit !== null) {
            return $lastExplicit;
        }

        if ($resolvedAt === null) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $entry = $history[$i] ?? null;
                if (! is_array($entry)) {
                    continue;
                }
                if (strtolower(trim((string) ($entry['action'] ?? ''))) === 'status_changed') {
                    return $i;
                }
            }

            return null;
        }

        $target = $resolvedAt->getTimestamp();
        $bestIdx = null;
        $bestDelta = PHP_INT_MAX;
        foreach ($history as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $at = trim((string) ($entry['at'] ?? ''));
            if ($at === '') {
                continue;
            }
            try {
                $ts = Carbon::parse($at)->getTimestamp();
            } catch (\Throwable $e) {
                continue;
            }
            $delta = abs($ts - $target);
            if ($delta <= 600 && $delta < $bestDelta) {
                $bestDelta = $delta;
                $bestIdx = $i;
            }
        }

        return $bestIdx;
    }

    /** Same rows as /all-marketplace-master (channel_master, status = active) */
    public function channelMaster(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_master_id');
    }

    /**
     * The auth user who created the ticket. Stamped once at create time and
     * never overwritten on edit, so the Executive column can render the
     * creator's *current* `users.name` (collapsing rename drift such as
     * "Hritiksha" / "Hritiksha Deb" or "Sounak" / "Sounak B"). Nullable
     * because legacy rows pre-date this column and best-effort backfill
     * leaves unknown rows unlinked.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
