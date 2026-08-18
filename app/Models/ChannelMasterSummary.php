<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMasterSummary extends Model
{
    use HasFactory;
    
    protected $table = 'channel_master_daily_data';
    
    protected $fillable = [
        'id',
        'channel',
        'snapshot_date',
        'summary_data',
        'notes',
    ];
    
    protected $casts = [
        'snapshot_date' => 'date',
        'summary_data' => 'array', // Auto JSON encode/decode
    ];

    /**
     * Decode summary_data whether it is already an array or a JSON string.
     *
     * @param  mixed  $sd
     * @return array<string, mixed>
     */
    public static function decodeSummaryData($sd): array
    {
        if (is_string($sd)) {
            $sd = json_decode($sd, true);
        }

        return is_array($sd) ? $sd : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryArray(): array
    {
        return self::decodeSummaryData($this->summary_data);
    }

    /**
     * Full Active Channel snapshots always persist l30_sales. Listing-only
     * writers (Missing L, Reverb map/miss) can create a sparse day without it,
     * which the metric chart then plots as $0 for every column.
     */
    public function isFullChannelSnapshot(): bool
    {
        return array_key_exists('l30_sales', $this->summaryArray());
    }

    /**
     * Merge fields into today's Pacific snapshot without wiping sales/metrics.
     * If today has no full snapshot yet, seed from the latest full day.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function mergeTodaySummary(
        string $channel,
        array $fields,
        ?string $notes = null,
        string $timezone = 'America/Los_Angeles'
    ): ?self {
        $today = now($timezone)->toDateString();
        $existing = self::where('channel', $channel)
            ->whereDate('snapshot_date', $today)
            ->first();
        $sd = $existing ? $existing->summaryArray() : [];

        if (! array_key_exists('l30_sales', $sd)) {
            $prior = self::query()
                ->where('channel', $channel)
                ->where('snapshot_date', '<', $today)
                ->orderByDesc('snapshot_date')
                ->limit(14)
                ->get()
                ->first(fn (self $row) => $row->isFullChannelSnapshot());
            if ($prior) {
                $priorSd = $prior->summaryArray();
                // Keep L30 / views so listing-only writers don't plot $0, but never
                // reuse yesterday's Y Sales as today's as-of day (that made Aug 16
                // and Aug 17 look identical on /all-marketplace-master).
                unset($priorSd['y_sales'], $priorSd['calculated_at']);
                $priorSd['seeded_from_snapshot'] = $prior->snapshot_date instanceof \DateTimeInterface
                    ? $prior->snapshot_date->format('Y-m-d')
                    : (string) $prior->snapshot_date;
                $sd = array_merge($priorSd, $sd);
            }
        }

        $sd = array_merge($sd, $fields);

        // Listing / catalogue merges can run before the daily auto-save. If the
        // last full calc is from a previous Pacific day, drop copied Y Sales so
        // the chart recomputes that as-of day from orders.
        $calcDay = substr((string) ($sd['calculated_at'] ?? ''), 0, 10);
        if ($calcDay !== '' && $calcDay < $today && ! array_key_exists('y_sales', $fields)) {
            unset($sd['y_sales']);
        }

        return self::updateOrCreate(
            [
                'channel' => $channel,
                'snapshot_date' => $today,
            ],
            [
                'summary_data' => $sd,
                'notes' => $existing?->notes ?: ($notes ?: 'Merged channel snapshot'),
            ]
        );
    }
    
    /**
     * Get yesterday's summary for comparison
     */
    public static function getYesterday($channel)
    {
        return self::where('channel', $channel)
            ->whereDate('snapshot_date', now()->subDay()->toDateString())
            ->first();
    }
    
    /**
     * Get summary for a specific date
     */
    public static function getForDate($date, $channel)
    {
        return self::where('channel', $channel)
            ->whereDate('snapshot_date', $date)
            ->first();
    }
    
    /**
     * Get last N days of summaries
     */
    public static function getLastDays($days = 7, $channel = null)
    {
        $query = self::where('snapshot_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date', 'desc');
        
        if ($channel) {
            $query->where('channel', $channel);
        }
        
        return $query->get();
    }
    
    /**
     * Get all channels for a specific date
     */
    public static function getAllChannelsForDate($date)
    {
        return self::whereDate('snapshot_date', $date)->get();
    }
    
    /**
     * Helper to get a specific metric from summary_data
     */
    public function get($key, $default = null)
    {
        return $this->summary_data[$key] ?? $default;
    }
    
    /**
     * Helper to set a specific metric in summary_data
     */
    public function set($key, $value)
    {
        $data = $this->summary_data ?? [];
        $data[$key] = $value;
        $this->summary_data = $data;
        return $this;
    }
}
