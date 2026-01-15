<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMasterSummary extends Model
{
    use HasFactory;
    
    protected $table = 'channel_master_daily_data';
    
    protected $fillable = [
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
