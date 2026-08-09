<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryVerifyBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'verify-adjust';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * Mirrors Verification & Adjustment green/yellow verified counts.
     *
     * @return array{verified: int, unverified: int, total: int}
     */
    public static function calculate(): array
    {
        if (! Schema::hasTable('inventories') || ! Schema::hasColumn('inventories', 'is_verified')) {
            return ['verified' => 0, 'unverified' => 0, 'total' => 0];
        }

        $verified = (int) DB::table('inventories')->where('is_verified', 1)->count();
        $total = (int) DB::table('inventories')->count();

        return [
            'verified' => $verified,
            'unverified' => max(0, $total - $verified),
            'total' => $total,
        ];
    }
}
