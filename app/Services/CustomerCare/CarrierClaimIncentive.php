<?php

namespace App\Services\CustomerCare;

use App\Models\ChannelTabulatorColumnSetting;
use App\Support\CustomerCareDepartments;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Carrier Claims "Incentive" badge: rupees earned = claim received amount x RATE.
 *
 * The page computes the live number from the filtered rows. On the 1st of every month the
 * L30 received amount (claims received, created in the last 30 days) is saved here as a
 * history entry that the badge shows as a dot.
 *
 * History lives in channel_tabulator_column_settings (same pattern as the eBay EL30 coupon
 * history), so no migration is needed.
 */
class CarrierClaimIncentive
{
    /** Rupees per received claim dollar. */
    public const RATE = 1;

    private const TABLE = 'dispatch_issue_issues';

    private const KEEP_ENTRIES = 60;

    private static function settingName(string $department): string
    {
        return 'carrier_claims_incentive_history_'.Str::slug($department !== '' ? $department : 'carrier');
    }

    private static function parseAmount(mixed $raw): float
    {
        $s = preg_replace('/[^0-9.\-]/', '', trim((string) ($raw ?? ''))) ?? '';
        if ($s === '' || $s === '.' || $s === '-') {
            return 0.0;
        }
        $v = (float) $s;

        return is_finite($v) ? round($v, 2) : 0.0;
    }

    /**
     * Claims received that were created in the 30 days up to $asOf (same window as the page's L30).
     *
     * @return array{count:int, amount:float}
     */
    public static function l30Received(string $department, ?CarbonInterface $asOf = null): array
    {
        if (! Schema::hasColumn(self::TABLE, 'claim_received')) {
            return ['count' => 0, 'amount' => 0.0];
        }
        $asOf = $asOf ? $asOf->copy() : now();
        $from = $asOf->copy()->subDays(30)->startOfDay();

        $query = DB::table(self::TABLE)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->where('claim_received', true)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $asOf);
        CustomerCareDepartments::applyWhereDepartmentMatches($query, 'department', $department);

        $count = 0;
        $amount = 0.0;
        foreach ($query->get(['amp_usd']) as $row) {
            $count++;
            $amount += self::parseAmount($row->amp_usd ?? null);
        }

        return ['count' => $count, 'amount' => round($amount, 2)];
    }

    /**
     * Save the L30 received snapshot for $asOf's date (re-running on the same day overwrites it).
     *
     * @return array{date:string, count:int, amount:float, incentive:float}
     */
    public static function snapshot(string $department, ?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? $asOf->copy() : now();
        $l30 = self::l30Received($department, $asOf);
        $date = $asOf->toDateString();
        $entry = [
            'count' => $l30['count'],
            'amount' => $l30['amount'],
            'incentive' => round($l30['amount'] * self::RATE, 2),
            'captured_at' => now()->toDateTimeString(),
        ];
        $name = self::settingName($department);

        DB::transaction(function () use ($name, $date, $entry) {
            $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $name)->lockForUpdate()->first();
            $vis = $row && is_array($row->visibility) ? $row->visibility : [];
            $entries = is_array($vis['entries'] ?? null) ? $vis['entries'] : [];
            $entries[$date] = $entry;
            ksort($entries);
            if (count($entries) > self::KEEP_ENTRIES) {
                $entries = array_slice($entries, -self::KEEP_ENTRIES, null, true);
            }
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => $name],
                ['visibility' => ['entries' => $entries], 'column_order' => []]
            );
        });

        return ['date' => $date] + $entry;
    }

    /**
     * Saved snapshots, newest first.
     *
     * @return list<array{date:string, count:int, amount:float, incentive:float}>
     */
    public static function history(string $department): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::settingName($department))->first();
        $vis = $row && is_array($row->visibility) ? $row->visibility : [];
        $entries = is_array($vis['entries'] ?? null) ? $vis['entries'] : [];
        krsort($entries);

        $out = [];
        foreach ($entries as $date => $e) {
            if (! is_array($e)) {
                continue;
            }
            $out[] = [
                'date' => (string) $date,
                'count' => (int) ($e['count'] ?? 0),
                'amount' => (float) ($e['amount'] ?? 0),
                'incentive' => (float) ($e['incentive'] ?? 0),
            ];
        }

        return $out;
    }
}
