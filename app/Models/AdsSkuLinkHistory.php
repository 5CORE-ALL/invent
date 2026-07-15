<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdsSkuLinkHistory extends Model
{
    protected $table = 'ads_sku_link_histories';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'parent',
        'action',
        'linked_sku',
        'changes',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public static function logAction(
        string $sku,
        string $action,
        ?string $linkedSku = null,
        ?string $parent = null,
        ?string $updatedBy = null
    ): void {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        $action = strtolower(trim($action));
        $linkedSku = trim((string) ($linkedSku ?? ''));

        if ($action === 'linked') {
            $changes = sprintf('Linked SKU %s', $linkedSku !== '' ? $linkedSku : '—');
        } elseif ($action === 'unlinked') {
            $changes = sprintf('Unlinked SKU %s', $linkedSku !== '' ? $linkedSku : '—');
        } else {
            $changes = sprintf('%s SKU %s', ucfirst($action), $linkedSku !== '' ? $linkedSku : '—');
        }

        self::create([
            'sku' => $sku,
            'parent' => $parent !== '' ? $parent : null,
            'action' => $action,
            'linked_sku' => $linkedSku !== '' ? $linkedSku : null,
            'changes' => $changes,
            'updated_by' => $updatedBy ?: 'N/A',
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array<string, mixed>>
     */
    public static function buildSummaryMap(array $skus, int $staleDays = 15): array
    {
        $skuList = collect($skus)
            ->map(fn ($sku) => strtoupper(trim((string) $sku)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($skuList === []) {
            return [];
        }

        $rows = self::query()
            ->whereIn(DB::raw('UPPER(TRIM(sku))'), $skuList)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) $row->sku));
            $grouped[$key][] = $row;
        }

        $threshold = now()->subDays($staleDays);
        $summary = [];

        foreach ($skuList as $skuNorm) {
            $entries = $grouped[$skuNorm] ?? [];
            if ($entries === []) {
                continue;
            }

            $latest = $entries[0];
            $updatedAt = $latest->updated_at instanceof Carbon
                ? $latest->updated_at
                : Carbon::parse($latest->updated_at);

            $summary[$skuNorm] = [
                'history_count' => count($entries),
                'latest_history_at' => $updatedAt->timezone('America/New_York')->format('m-d-Y'),
                'latest_history_time' => $updatedAt->timezone('America/New_York')->format('H:i'),
                'latest_history_by' => $latest->updated_by ?: 'N/A',
                'latest_change' => $latest->changes,
                'history_stale' => $updatedAt->lt($threshold),
            ];
        }

        return $summary;
    }
}
