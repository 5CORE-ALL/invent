<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LmpCompetitorHistory extends Model
{
    protected $table = 'lmp_competitor_histories';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'parent',
        'action',
        'item_id',
        'competitor_id',
        'product_title',
        'total_price',
        'changes',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
        'total_price' => 'float',
    ];

    public static function logAction(
        string $sku,
        string $action,
        ?string $itemId = null,
        ?int $competitorId = null,
        ?string $productTitle = null,
        ?float $totalPrice = null,
        ?string $parent = null,
        ?string $updatedBy = null
    ): void {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        $action = strtolower(trim($action));
        $itemId = trim((string) ($itemId ?? ''));
        $priceLabel = $totalPrice !== null ? '$' . number_format($totalPrice, 2) : 'N/A';
        $title = trim((string) ($productTitle ?? ''));

        if ($action === 'added') {
            $changes = sprintf('Added eBay item ID %s (%s)', $itemId ?: '—', $priceLabel);
        } elseif ($action === 'deleted') {
            $changes = sprintf('Deleted eBay item ID %s (%s)', $itemId ?: '—', $priceLabel);
        } else {
            $changes = sprintf('%s eBay item ID %s', ucfirst($action), $itemId ?: '—');
        }

        if ($title !== '') {
            $changes .= ' — ' . mb_substr($title, 0, 120);
        }

        self::create([
            'sku' => $sku,
            'parent' => $parent !== '' ? $parent : null,
            'action' => $action,
            'item_id' => $itemId !== '' ? $itemId : null,
            'competitor_id' => $competitorId,
            'product_title' => $title !== '' ? $title : null,
            'total_price' => $totalPrice,
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

    /**
     * @return array<int, string>
     */
    public static function staleSkuNorms(int $staleDays = 15): array
    {
        $threshold = now()->subDays($staleDays);

        return self::query()
            ->selectRaw('UPPER(TRIM(sku)) as sku_norm')
            ->groupBy(DB::raw('UPPER(TRIM(sku))'))
            ->havingRaw('MAX(updated_at) < ?', [$threshold])
            ->pluck('sku_norm')
            ->map(fn ($sku) => strtoupper(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();
    }
}
