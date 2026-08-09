<?php

namespace App\Models;

use App\Support\Badges\BadgeDataCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BadgeDataHistory extends Model
{
    public const TZ = 'America/Los_Angeles';

    public $timestamps = false;

    protected $table = 'badges_data_histories';

    protected $fillable = [
        'page_name',
        'field',
        'snapshot_date',
        'value',
        'captured_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'value' => 'float',
        'captured_at' => 'datetime',
    ];

    /**
     * Persist today's California snapshot for every numeric field in $data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function recordPage(string $pageName, array $data, ?Carbon $when = null): void
    {
        if (! Schema::hasTable('badges_data_histories')) {
            return;
        }

        $when = $when ?: now(self::TZ);
        $date = $when->toDateString();

        foreach ($data as $field => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            self::query()->updateOrCreate(
                [
                    'page_name' => $pageName,
                    'field' => (string) $field,
                    'snapshot_date' => $date,
                ],
                [
                    'value' => (float) $value,
                    'captured_at' => now(),
                ]
            );
        }
    }

    /**
     * @return 'green'|'red'|'gray'
     */
    public static function toneFor(string $pageName, string $field, mixed $currentValue): string
    {
        if (! is_numeric($currentValue) || ! Schema::hasTable('badges_data_histories')) {
            return 'gray';
        }

        $today = now(self::TZ)->toDateString();
        $prev = self::query()
            ->where('page_name', $pageName)
            ->where('field', $field)
            ->whereDate('snapshot_date', '<', $today)
            ->orderByDesc('snapshot_date')
            ->value('value');

        if ($prev === null) {
            // Fall back to today's earlier snapshot vs current only when we have 2+ points later
            return 'gray';
        }

        $cur = (float) $currentValue;
        $previous = (float) $prev;
        if (abs($cur - $previous) < 0.00001) {
            return 'gray';
        }

        $lowerBetter = BadgeDataCatalog::isLowerBetter($pageName, $field);
        if ($lowerBetter) {
            return $cur < $previous ? 'green' : 'red';
        }

        return $cur > $previous ? 'green' : 'red';
    }

    /**
     * @return list<array{date: string, value: float, tone: string}>
     */
    public static function series(string $pageName, string $field, int $days = 30, ?float $liveValue = null): array
    {
        if (! Schema::hasTable('badges_data_histories')) {
            return $liveValue === null ? [] : [[
                'date' => now(self::TZ)->format('M j'),
                'value' => round($liveValue, 2),
                'tone' => 'gray',
            ]];
        }

        $days = max(1, min(365, $days));
        $start = now(self::TZ)->subDays($days - 1)->startOfDay();

        $rows = self::query()
            ->where('page_name', $pageName)
            ->where('field', $field)
            ->whereDate('snapshot_date', '>=', $start->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'value']);

        $byDate = [];
        foreach ($rows as $row) {
            $key = $row->snapshot_date instanceof \DateTimeInterface
                ? $row->snapshot_date->format('Y-m-d')
                : (string) $row->snapshot_date;
            $byDate[$key] = (float) $row->value;
        }

        if ($liveValue !== null) {
            $byDate[now(self::TZ)->toDateString()] = $liveValue;
        }

        $lowerBetter = BadgeDataCatalog::isLowerBetter($pageName, $field);
        $out = [];
        $prev = null;
        ksort($byDate);
        foreach ($byDate as $ymd => $value) {
            $tone = 'gray';
            if ($prev !== null) {
                if (abs($value - $prev) >= 0.00001) {
                    if ($lowerBetter) {
                        $tone = $value < $prev ? 'green' : 'red';
                    } else {
                        $tone = $value > $prev ? 'green' : 'red';
                    }
                }
            }
            $out[] = [
                'date' => Carbon::parse($ymd, self::TZ)->format('M j'),
                'value' => round($value, 2),
                'tone' => $tone,
            ];
            $prev = $value;
        }

        return $out;
    }
}
