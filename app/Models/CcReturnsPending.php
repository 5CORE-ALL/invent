<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Pending-returns count per channel. Pulled from each marketplace's returns
 * API where one exists; channels without an API use a hand-entered
 * `manual_count`. Shown in the Pending column of /customer-care/report.
 */
class CcReturnsPending extends Model
{
    protected $table = 'cc_returns_pending';

    protected $fillable = [
        'channel_id',
        'pending_count',
        'manual_count',
        'manual_updated_at',
        'fetch_status',
        'fetch_note',
        'source',
        'last_fetched_at',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'pending_count' => 'integer',
        'manual_count' => 'integer',
        'updated_by_user_id' => 'integer',
        'last_fetched_at' => 'datetime',
        'manual_updated_at' => 'datetime',
    ];

    /**
     * Effective count for a row: the live API count when the last pull
     * succeeded, otherwise the manual count (or null when neither exists).
     *
     * @return array{count: int|null, origin: string}  origin = api|manual|none
     */
    public function effective(): array
    {
        if ((string) $this->fetch_status === 'ok') {
            return ['count' => (int) $this->pending_count, 'origin' => 'api'];
        }
        if ($this->manual_count !== null) {
            return ['count' => (int) $this->manual_count, 'origin' => 'manual'];
        }

        return ['count' => null, 'origin' => 'none'];
    }

    /**
     * Total across ACTIVE channels only (API count when available, else manual).
     */
    public static function pendingTotal(): int
    {
        if (! Schema::hasTable('cc_returns_pending')) {
            return 0;
        }

        $activeIds = ChannelMaster::query()->where('status', 'Active')->pluck('id')->all();
        if ($activeIds === []) {
            return 0;
        }

        $total = 0;
        $seenSources = [];
        foreach (static::query()->whereIn('channel_id', $activeIds)->get() as $row) {
            $eff = $row->effective();
            if ($eff['origin'] === 'api' && (string) $row->source !== '') {
                // Two channels backed by the same store (e.g. B2B and
                // Business 5 Core (B2B)) must only be counted once.
                if (isset($seenSources[$row->source])) {
                    continue;
                }
                $seenSources[$row->source] = true;
            }
            $total += (int) ($eff['count'] ?? 0);
        }

        return $total;
    }
}
