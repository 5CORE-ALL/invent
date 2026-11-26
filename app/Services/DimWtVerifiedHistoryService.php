<?php

namespace App\Services;

use App\Models\BadgeDataHistory;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * History of how many child SKUs are verified on /dim-wt-master.
 *
 * One point per day (California day, same as every other badge history), stored in badges_data_histories
 * under page 'dim_wt_verified' with the fields verified / not_verified / total. The point of the current
 * day is rewritten every time a Verified flag changes and by the nightly command, so it always holds the
 * latest count of that day. Parent rows (SKU contains PARENT) are not counted, same as the page.
 */
class DimWtVerifiedHistoryService
{
    public const PAGE = 'dim_wt_verified';

    /** @return array{total: int, verified: int, not_verified: int} */
    public function counts(): array
    {
        $row = ProductMaster::query()
            ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
            ->selectRaw(
                "COUNT(*) AS total, SUM(CASE WHEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.verified_data')), '0') IN ('1', 'true') THEN 1 ELSE 0 END) AS verified"
            )
            ->first();

        $total = (int) ($row->total ?? 0);
        $verified = (int) ($row->verified ?? 0);

        return ['total' => $total, 'verified' => $verified, 'not_verified' => max(0, $total - $verified)];
    }

    /** Save today's point. Never throws: history must not block a verify click. */
    public function record(): ?array
    {
        try {
            $counts = $this->counts();
            BadgeDataHistory::recordPage(self::PAGE, $counts);

            return $counts;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array{current: array<string, int>, series: list<array<string, mixed>>, first_date: ?string}
     */
    public function series(int $days): array
    {
        $current = $this->record() ?? $this->counts();
        $days = max(1, min(730, $days));
        $start = now(BadgeDataHistory::TZ)->subDays($days - 1)->startOfDay();

        $byDate = [];
        if (Schema::hasTable('badges_data_histories')) {
            $rows = BadgeDataHistory::query()
                ->where('page_name', self::PAGE)
                ->whereDate('snapshot_date', '>=', $start->toDateString())
                ->orderBy('snapshot_date')
                ->get(['field', 'snapshot_date', 'value']);
            foreach ($rows as $r) {
                $ymd = $r->snapshot_date instanceof \DateTimeInterface ? $r->snapshot_date->format('Y-m-d') : (string) $r->snapshot_date;
                $byDate[$ymd][(string) $r->field] = (int) round((float) $r->value);
            }
        }

        $first = null;
        if (Schema::hasTable('badges_data_histories')) {
            $first = BadgeDataHistory::query()->where('page_name', self::PAGE)->min('snapshot_date');
        }

        ksort($byDate);
        $series = [];
        foreach ($byDate as $ymd => $f) {
            $verified = $f['verified'] ?? null;
            if ($verified === null) {
                continue;
            }
            $total = $f['total'] ?? null;
            $series[] = [
                'ymd' => $ymd,
                'date' => Carbon::parse($ymd, BadgeDataHistory::TZ)->format('M j'),
                'verified' => $verified,
                'not_verified' => $f['not_verified'] ?? ($total !== null ? max(0, $total - $verified) : null),
                'total' => $total,
            ];
        }

        return [
            'current' => $current,
            'series' => $series,
            'first_date' => $first ? Carbon::parse($first, BadgeDataHistory::TZ)->format('M j, Y') : null,
        ];
    }
}
