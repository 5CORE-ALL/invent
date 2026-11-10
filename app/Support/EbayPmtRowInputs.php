<?php

namespace App\Support;

use App\Services\DilVsSbidApplyService;
use Illuminate\Support\Collection;

/**
 * Puts the numbers Ads Rule PMT decides with on the rows of the Campaign Ads pages
 * (INV, OV L30, category), so the Ads Rule PMT window there counts and decides like the push does.
 *
 * eBay 1 gives each listing's own roll-up. eBay 2 gives the whole PARENT family's numbers and marks the
 * row as a family row (the rule is decided once per parent and every ad listing of it gets that bid).
 */
final class EbayPmtRowInputs
{
    /**
     * @param  Collection<int, object>  $rows  rows with a listing_id
     */
    public static function attach(Collection $rows, string $account): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        try {
            $inputs = app(DilVsSbidApplyService::class)->pmtRowInputs(
                $account,
                $rows->pluck('listing_id')->filter()->map(fn ($id) => (string) $id)->all()
            );
        } catch (\Throwable $e) {
            report($e);
            $inputs = [];
        }

        foreach ($rows as $row) {
            $in = $inputs[(string) ($row->listing_id ?? '')] ?? null;
            $row->INV = $in['inv'] ?? null;
            $row->L30 = $in['ovL30'] ?? null;
            $row->excel_category = (string) ($in['category'] ?? '');
            $row->{'(Child) sku'} = (string) ($row->resolved_sku ?? '');
            if ($account === 'ebay2') {
                $row->pmt_family_row = true; // the numbers above are the parent family's
            }
        }
    }
}
