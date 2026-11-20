<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of changes made to a SKU's images in Image Master
 * (additions, edits, removals, re-ordering, uploads, Shopify pulls ...).
 * Marketplace pushes are tracked separately by ImageMasterPushHistory.
 */
class ImageMasterActivityLog
{
    private const TABLE = 'image_master_activity_logs';

    private static ?bool $tableExists = null;

    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function record(string $sku, string $action, string $summary, ?array $details = null): void
    {
        $sku = trim($sku);
        if ($sku === '' || ! self::tableExists()) {
            return;
        }

        try {
            $user = auth()->user();
            DB::table(self::TABLE)->insert([
                'sku' => $sku,
                'action' => mb_substr($action, 0, 32),
                'user_id' => $user?->getAuthIdentifier(),
                'user_name' => $user->name ?? null,
                'user_email' => $user->email ?? null,
                'summary' => mb_substr($summary, 0, 500),
                'details' => $details === null ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ImageMasterActivityLog record failed', ['sku' => $sku, 'action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Most recent events per SKU (newest first).
     *
     * @param  list<string>  $skus
     * @param  list<string>  $only    keep only these actions (empty = all)
     * @param  list<string>  $except  drop these actions
     * @return array<string, list<array{id: int, action: string, summary: string, user_name: string, user_email: string, at: string, details: array<string, mixed>|null}>>
     */
    public static function recentForSkus(array $skus, int $perSku = 15, array $only = [], array $except = []): array
    {
        if (! self::tableExists()) {
            return [];
        }
        $skus = array_values(array_unique(array_filter(array_map(static fn ($s) => trim((string) $s), $skus))));
        $out = [];

        try {
            foreach (array_chunk($skus, 500) as $chunk) {
                $query = DB::table(self::TABLE)->whereIn('sku', $chunk);
                if ($only !== []) {
                    $query->whereIn('action', $only);
                }
                if ($except !== []) {
                    $query->whereNotIn('action', $except);
                }
                $rows = $query
                    ->orderByDesc('id')
                    ->get(['id', 'sku', 'action', 'user_name', 'user_email', 'summary', 'details', 'created_at']);

                foreach ($rows as $r) {
                    $key = strtoupper(trim((string) $r->sku));
                    if (count($out[$key] ?? []) >= $perSku) {
                        continue;
                    }
                    $details = $r->details ? json_decode((string) $r->details, true) : null;
                    $out[$key][] = [
                        'id' => (int) $r->id,
                        'action' => (string) $r->action,
                        'summary' => (string) ($r->summary ?? ''),
                        'user_name' => (string) ($r->user_name ?? ''),
                        'user_email' => (string) ($r->user_email ?? ''),
                        'at' => (string) $r->created_at,
                        'details' => is_array($details) ? $details : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ImageMasterActivityLog recentForSkus failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Distinct SKUs whose images changed on each day. Missing days are zero.
     * The activity log only exists from mid-November, so uploads and successful
     * image pushes are included too. A SKU counts once per day. Audit tasks are not image updates.
     *
     * @return list<array{date: string, label: string, skus: int}>
     */
    public static function dailyUpdatedSkuCounts(int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $start = now($tz)->startOfDay()->subDays($days - 1);
        $from = $start->format('Y-m-d H:i:s');
        $byDay = [];

        self::collectSkuDays($byDay, self::TABLE, 'created_at', $from, function ($query) {
            $query->where('action', '!=', 'audit');
        });
        self::collectSkuDays($byDay, 'product_images', 'created_at', $from);
        self::collectSkuDays($byDay, 'image_master_push_histories', 'pushed_at', $from, function ($query) {
            $query->where('success', 1)->where('image_count', '>', 0);
        });

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            $out[] = [
                'date' => $key,
                'label' => $d->format('M j'),
                'skus' => isset($byDay[$key]) ? count($byDay[$key]) : 0,
            ];
        }

        return $out;
    }

    /**
     * Add one distinct SKU per calendar day from a timestamped image table.
     *
     * @param  array<string, array<string, true>>  $byDay
     * @param  callable(\Illuminate\Database\Query\Builder): void|null  $constrain
     */
    private static function collectSkuDays(array &$byDay, string $table, string $atColumn, string $from, ?callable $constrain = null): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, $atColumn)) {
            return;
        }

        try {
            $query = DB::table($table)
                ->where($atColumn, '>=', $from)
                ->whereNotNull('sku')
                ->where('sku', '!=', '');
            if ($constrain) {
                $constrain($query);
            }
            $rows = $query
                ->selectRaw('DATE(`'.$atColumn.'`) as d, UPPER(TRIM(sku)) as sku_key')
                ->groupByRaw('DATE(`'.$atColumn.'`), UPPER(TRIM(sku))')
                ->get();
            foreach ($rows as $r) {
                $day = substr((string) $r->d, 0, 10);
                $sku = strtoupper(trim((string) $r->sku_key));
                if ($day === '' || $sku === '') {
                    continue;
                }
                $byDay[$day][$sku] = true;
            }
        } catch (\Throwable $e) {
            Log::warning('ImageMasterActivityLog daily source failed', ['table' => $table, 'error' => $e->getMessage()]);
        }
    }

    private static function tableExists(): bool
    {
        if (self::$tableExists === null) {
            try {
                self::$tableExists = Schema::hasTable(self::TABLE);
            } catch (\Throwable) {
                self::$tableExists = false;
            }
        }

        return self::$tableExists;
    }
}
