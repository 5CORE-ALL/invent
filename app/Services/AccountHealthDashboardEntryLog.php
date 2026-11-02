<?php

namespace App\Services;

use App\Models\AccountHealthDashboardEntry;
use App\Models\AtoZClaimsRate;
use App\Models\ChannelMaster;
use App\Models\FullfillmentRate;
use App\Models\LateShipmentRate;
use App\Models\NegativeSellerRate;
use App\Models\OdrRate;
use App\Models\OnTimeDeliveryRate;
use App\Models\RefundRate;
use App\Models\ValidTrackingRate;
use App\Models\VoilanceRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccountHealthDashboardEntryLog
{
    /** @var array<string, array{label: string, model: class-string}> */
    public const METRICS = [
        'odr_rate' => ['label' => 'ODR Rate', 'model' => OdrRate::class],
        'fulfillment_rate' => ['label' => 'Fulfillment Rate', 'model' => FullfillmentRate::class],
        'valid_tracking_rate' => ['label' => 'Valid Tracking Rate', 'model' => ValidTrackingRate::class],
        'late_shipment_rate' => ['label' => 'Late Shipment Rate', 'model' => LateShipmentRate::class],
        'on_time_delivery' => ['label' => 'On Time Delivery', 'model' => OnTimeDeliveryRate::class],
        'negative_seller_rate' => ['label' => 'Negative Seller Rate', 'model' => NegativeSellerRate::class],
        'atoz_claims_rate' => ['label' => 'A-to-Z Claims', 'model' => AtoZClaimsRate::class],
        'violation_rate' => ['label' => 'Violation/Compliance', 'model' => VoilanceRate::class],
        'refund_rate' => ['label' => 'Refund Rate', 'model' => RefundRate::class],
    ];

    /**
     * @param  array<string, array<int, string>>  $columns  value column => request keys that mean that column
     */
    public static function recordRequest(int $channelId, string $channel, string $metric, mixed $row, Request $request, array $columns): void
    {
        foreach ($columns as $column => $keys) {
            $present = null;
            foreach ($keys as $key) {
                if ($request->exists($key)) {
                    $present = $key;
                    break;
                }
            }
            if ($present === null) {
                continue;
            }
            $old = is_object($row) ? ($row->{$column} ?? null) : null;
            self::write($channelId, $channel, $metric, $column, $old, $request->input($present));
        }
    }

    public static function write(int $channelId, string $channel, string $metric, string $column, mixed $old, mixed $new): void
    {
        $oldText = self::text($old);
        $newText = self::text($new);
        if ($oldText === $newText) {
            return;
        }
        if (! self::tableReady()) {
            return;
        }

        try {
            $user = Auth::user();
            AccountHealthDashboardEntry::query()->create([
                'channel_id' => $channelId,
                'channel' => $channel,
                'metric' => $metric,
                'value_column' => $column,
                'old_value' => $oldText === '' ? null : $oldText,
                'new_value' => $newText === '' ? null : $newText,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?: ($user?->email ?: 'Unknown'),
                'entered_on' => now()->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Account health entry log failed', ['error' => $e->getMessage(), 'channel' => $channel, 'metric' => $metric]);
        }
    }

    /**
     * One series per dashboard column for a single marketplace.
     * Logged entries win over the stored snapshots for the same day.
     *
     * @return array{channel: string, columns: array<int, array<string, mixed>>}|null
     */
    public static function series(string $channelName, ?string $onlyMetric = null): ?array
    {
        $channel = self::findChannel($channelName);
        if ($channel === null) {
            return null;
        }

        $wanted = self::METRICS;
        if ($onlyMetric !== null && $onlyMetric !== '' && $onlyMetric !== 'all' && isset($wanted[$onlyMetric])) {
            $wanted = [$onlyMetric => $wanted[$onlyMetric]];
        }

        $currentByDate = [];
        $allowedByDate = [];
        $latestAllowed = [];
        foreach ($wanted as $key => $meta) {
            $rows = $meta['model']::query()->where('channel_id', $channel->id)->orderBy('id')->get();
            foreach ($rows as $row) {
                self::putPoint($currentByDate, $row->report_date ?? null, $key, $row->current ?? null);
                self::putPoint($currentByDate, $row->prev_1_date ?? null, $key, $row->prev_1 ?? null);
                self::putPoint($currentByDate, $row->prev_2_date ?? null, $key, $row->prev_2 ?? null);
                $allowedText = self::text($row->allowed ?? null);
                if ($allowedText !== '') {
                    $latestAllowed[$key] = $allowedText;
                    self::putPoint($allowedByDate, $row->report_date ?? null, $key, $allowedText);
                }
            }
        }

        if (self::tableReady()) {
            $logs = AccountHealthDashboardEntry::query()
                ->where('channel_id', $channel->id)
                ->whereIn('metric', array_keys($wanted))
                ->orderBy('id')
                ->get(['metric', 'value_column', 'new_value', 'entered_on']);
            foreach ($logs as $log) {
                $day = $log->entered_on instanceof \DateTimeInterface ? $log->entered_on->format('Y-m-d') : substr((string) $log->entered_on, 0, 10);
                if ($day === '' || ! isset($wanted[$log->metric])) {
                    continue;
                }
                $bucket = $log->value_column === 'allowed' ? $allowedByDate : $currentByDate;
                $number = self::number($log->new_value);
                if ($number === null) {
                    if (isset($bucket[$day][$log->metric])) {
                        unset($bucket[$day][$log->metric]);
                    }
                } else {
                    $bucket[$day][$log->metric] = $number;
                }
                if ($log->value_column === 'allowed') {
                    $allowedByDate = $bucket;
                    $latestAllowed[$log->metric] = self::text($log->new_value);
                } else {
                    $currentByDate = $bucket;
                }
            }
        }

        $columns = [];
        foreach ($wanted as $key => $meta) {
            $days = [];
            foreach ($currentByDate as $day => $metrics) {
                if (array_key_exists($key, $metrics)) {
                    $days[$day] = true;
                }
            }
            foreach ($allowedByDate as $day => $metrics) {
                if (array_key_exists($key, $metrics)) {
                    $days[$day] = true;
                }
            }
            $dates = array_keys($days);
            sort($dates);
            $points = [];
            $allowedPoints = [];
            $updatedOn = '';
            $allowedUpdatedOn = '';
            $latest = null;
            foreach ($dates as $day) {
                if (isset($currentByDate[$day][$key])) {
                    $points[] = ['date' => $day, 'value' => $currentByDate[$day][$key]];
                    $updatedOn = $day;
                    $latest = $currentByDate[$day][$key];
                }
                if (isset($allowedByDate[$day][$key])) {
                    $allowedPoints[] = ['date' => $day, 'value' => $allowedByDate[$day][$key]];
                    $allowedUpdatedOn = $day;
                }
            }
            $columns[] = [
                'key' => $key,
                'label' => $meta['label'],
                'updated_on' => $updatedOn,
                'allowed_updated_on' => $allowedUpdatedOn,
                'latest' => $latest,
                'latest_allowed' => $latestAllowed[$key] ?? '',
                'points' => $points,
                'allowed_points' => $allowedPoints,
            ];
        }

        return [
            'channel' => (string) $channel->channel,
            'columns' => $columns,
        ];
    }

    /**
     * @return array{channel: string, rows: array<int, array<string, string>>}|null
     */
    public static function history(string $channelName): ?array
    {
        $channel = self::findChannel($channelName);
        if ($channel === null) {
            return null;
        }

        $rows = [];
        $seen = [];
        if (self::tableReady()) {
            $logs = AccountHealthDashboardEntry::query()
                ->where('channel_id', $channel->id)
                ->orderByDesc('id')
                ->limit(300)
                ->get();
            foreach ($logs as $log) {
                $day = $log->entered_on instanceof \DateTimeInterface ? $log->entered_on->format('Y-m-d') : substr((string) $log->entered_on, 0, 10);
                $seen[$log->metric.'|'.$log->value_column.'|'.$day] = true;
                $rows[] = [
                    'at' => optional($log->created_at)->format('Y-m-d H:i') ?: $day,
                    'updated_on' => $day,
                    'user_name' => (string) ($log->user_name ?: 'Unknown'),
                    'metric' => self::METRICS[$log->metric]['label'] ?? (string) $log->metric,
                    'metric_key' => (string) $log->metric,
                    'column' => $log->value_column === 'allowed' ? 'Allowed' : 'Current',
                    'old_value' => (string) ($log->old_value ?? ''),
                    'new_value' => (string) ($log->new_value ?? ''),
                ];
            }
        }

        foreach (self::METRICS as $key => $meta) {
            $model = $meta['model'];
            $stored = $model::query()->where('channel_id', $channel->id)->orderByDesc('id')->get();
            foreach ($stored as $row) {
                foreach ([['current', 'report_date'], ['prev_1', 'prev_1_date'], ['prev_2', 'prev_2_date']] as [$valueCol, $dateCol]) {
                    $day = self::day($row->{$dateCol} ?? null);
                    $value = self::text($row->{$valueCol} ?? null);
                    if ($day === '' || $value === '' || isset($seen[$key.'|current|'.$day])) {
                        continue;
                    }
                    $seen[$key.'|current|'.$day] = true;
                    $rows[] = [
                        'at' => $day,
                        'updated_on' => $day,
                        'user_name' => 'Not recorded',
                        'metric' => $meta['label'],
                        'metric_key' => $key,
                        'column' => 'Current',
                        'old_value' => '',
                        'new_value' => $value,
                    ];
                }
            }
        }

        usort($rows, function (array $a, array $b) {
            return strcmp($b['at'], $a['at']);
        });

        return [
            'channel' => (string) $channel->channel,
            'rows' => array_slice($rows, 0, 300),
        ];
    }

    public static function findChannel(string $channelName): ?ChannelMaster
    {
        $name = trim($channelName);
        if ($name === '') {
            return null;
        }

        return ChannelMaster::query()
            ->whereRaw('LOWER(channel) = ?', [strtolower($name)])
            ->first();
    }

    /**
     * @param  array<string, array<string, float>>  $byDate
     */
    protected static function putPoint(array &$byDate, mixed $date, string $metric, mixed $value): void
    {
        $day = self::day($date);
        $number = self::number($value);
        if ($day === '' || $number === null || ! isset(self::METRICS[$metric])) {
            return;
        }
        $byDate[$day][$metric] = $number;
    }

    protected static function day(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            $day = $date->format('Y-m-d');
        } else {
            $day = substr(trim((string) $date), 0, 10);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day === '0000-00-00') {
            return '';
        }

        return $day;
    }

    protected static function number(mixed $value): ?float
    {
        $text = self::text($value);
        if ($text === '' || strcasecmp($text, 'N/A') === 0 || $text === '#DIV/0!') {
            return null;
        }
        $text = str_replace([',', '%', ' '], '', $text);
        if (! is_numeric($text)) {
            return null;
        }

        return round((float) $text, 4);
    }

    protected static function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    protected static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            if (! Schema::hasTable('account_health_dashboard_entries')) {
                Schema::create('account_health_dashboard_entries', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('channel_id')->index();
                    $table->string('channel', 191);
                    $table->string('metric', 64);
                    $table->string('value_column', 16);
                    $table->string('old_value')->nullable();
                    $table->string('new_value')->nullable();
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->string('user_name', 191)->nullable();
                    $table->date('entered_on')->index();
                    $table->timestamps();
                    $table->index(['channel_id', 'metric', 'entered_on'], 'ah_dash_entries_ch_metric_day');
                });
            }
            $ready = true;
        } catch (\Throwable $e) {
            Log::warning('Account health entry table is not available', ['error' => $e->getMessage()]);
            $ready = Schema::hasTable('account_health_dashboard_entries');
        }

        return $ready;
    }
}
