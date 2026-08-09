<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseContractBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'purchase-contract';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * Mirrors Purchase Contract header badges (O Amount / Advance / Balance).
     *
     * @return array{o_amount: float, advance: float, balance: float, po_count: int}
     */
    public static function calculate(): array
    {
        if (! Schema::hasTable('purchase_orders')) {
            return ['o_amount' => 0, 'advance' => 0, 'balance' => 0, 'po_count' => 0];
        }

        $q = DB::table('purchase_orders');
        if (Schema::hasColumn('purchase_orders', 'is_archived')) {
            $q->where(function ($x) {
                $x->where('is_archived', 0)->orWhereNull('is_archived');
            });
        }

        $oAmount = (float) (clone $q)->sum('total_amount');
        $advance = (float) (clone $q)->sum('advance_amount');
        $poCount = (int) (clone $q)->count();

        return [
            'o_amount' => round($oAmount, 2),
            'advance' => round($advance, 2),
            'balance' => round(max(0, $oAmount - $advance), 2),
            'po_count' => $poCount,
        ];
    }
}
