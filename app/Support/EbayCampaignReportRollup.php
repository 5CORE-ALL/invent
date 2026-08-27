<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * eBay campaign-report rollups (KW priority + PMT general).
 *
 * L30 rows are upserted per listing/campaign. When a listing drops out of the
 * eBay report, its last L30 snapshot is left behind. Summing every L30 row
 * therefore mixes current spend with leftovers from months ago.
 */
final class EbayCampaignReportRollup
{
    /**
     * Keep only the latest L30 sync day so totals match Seller Hub.
     */
    public static function restrictToLatestL30Snapshot(Builder $query, string $table): Builder
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'updated_at')) {
            return $query;
        }

        $latest = DB::table($table)
            ->whereRaw("UPPER(TRIM(report_range)) = 'L30'")
            ->max('updated_at');

        if ($latest) {
            $query->whereDate('updated_at', Carbon::parse($latest)->toDateString());
        }

        return $query;
    }
}
