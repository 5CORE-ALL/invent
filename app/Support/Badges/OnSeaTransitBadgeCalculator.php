<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Models\ChinaLoad;
use App\Models\OnSeaTransit;

class OnSeaTransitBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'on-sea-transit';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        ChinaLoad::query()
            ->pluck('container_sl_no')
            ->each(function (string $containerSlNo): void {
                OnSeaTransit::firstOrCreate(['container_sl_no' => $containerSlNo]);
            });
    }

    /**
     * Mirrors updateBadgeCounts() in on_sea_transit/index.blade.php.
     *
     * @return array{pre_load: int, on_sea: int, landed: int, transit: int, total_value: float, due: float, value: float}
     */
    public static function calculate(): array
    {
        $rows = OnSeaTransit::query()
            ->whereNull('archived_at')
            ->get(['status', 'invoice_value', 'balance']);

        $visible = $rows->filter(fn (OnSeaTransit $row) => $row->status !== 'Arrived');

        $preLoad = $visible->filter(
            fn (OnSeaTransit $row) => ! $row->status || $row->status === 'Planning'
        )->count();

        $onSea = $visible->where('status', 'On Sea')->count();
        $landed = $visible->where('status', 'Landed')->count();
        $transit = $visible->count() - $preLoad;

        $nonPlanning = $visible->filter(
            fn (OnSeaTransit $row) => $row->status && $row->status !== 'Planning'
        );

        $totalValue = $nonPlanning->sum(fn (OnSeaTransit $row) => (float) ($row->invoice_value ?? 0));
        $due = $nonPlanning->sum(fn (OnSeaTransit $row) => (float) ($row->balance ?? 0));
        $value = $visible->sum(fn (OnSeaTransit $row) => (float) ($row->invoice_value ?? 0));

        return [
            'pre_load' => $preLoad,
            'on_sea' => $onSea,
            'landed' => $landed,
            'transit' => $transit,
            'total_value' => round($totalValue, 2),
            'due' => round($due, 2),
            'value' => round($value, 2),
        ];
    }
}
