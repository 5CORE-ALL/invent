<?php

namespace App\Http\Controllers\CustomerCare\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking ID and Link are maintained on the All Issues board (`dispatch_issue_issues`).
 * Other boards show them read-only, looked up by order number.
 */
trait AttachesAllIssuesLinkFields
{
    /**
     * Fills `tracking_number` and `issue_link` on each row from All Issues.
     * A value already on the row wins; otherwise the newest non-empty value on any
     * non-archived All Issues row with the same order number is used.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachAllIssuesLinkFields(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $table = 'dispatch_issue_issues';
        $hasTracking = Schema::hasColumn($table, 'tracking_number');
        $hasLink = Schema::hasColumn($table, 'issue_link');
        if (! $hasTracking && ! $hasLink) {
            return $rows;
        }

        $key = static fn ($v): string => mb_strtolower(trim((string) $v));

        $orders = [];
        foreach ($rows as $row) {
            $k = $key($row['order_number'] ?? '');
            if ($k !== '') {
                $orders[$k] = trim((string) $row['order_number']);
            }
        }

        $map = [];
        foreach (array_chunk(array_values($orders), 500) as $chunk) {
            $query = DB::table($table)
                ->select(array_values(array_filter([
                    'order_number',
                    $hasTracking ? 'tracking_number' : null,
                    $hasLink ? 'issue_link' : null,
                ])))
                ->whereIn('order_number', $chunk)
                ->where(function ($q) {
                    $q->whereNull('is_archived')->orWhere('is_archived', false);
                })
                ->where(function ($q) use ($hasTracking, $hasLink) {
                    if ($hasTracking) {
                        $q->orWhere(function ($w) {
                            $w->whereNotNull('tracking_number')->where('tracking_number', '!=', '');
                        });
                    }
                    if ($hasLink) {
                        $q->orWhere(function ($w) {
                            $w->whereNotNull('issue_link')->where('issue_link', '!=', '');
                        });
                    }
                })
                ->orderByDesc('id');

            foreach ($query->get() as $found) {
                $k = $key($found->order_number);
                $tn = $hasTracking ? trim((string) ($found->tracking_number ?? '')) : '';
                $il = $hasLink ? trim((string) ($found->issue_link ?? '')) : '';
                if ($tn !== '' && ! isset($map[$k]['tracking_number'])) {
                    $map[$k]['tracking_number'] = $tn;
                }
                if ($il !== '' && ! isset($map[$k]['issue_link'])) {
                    $map[$k]['issue_link'] = $il;
                }
            }
        }

        foreach ($rows as $i => $row) {
            $k = $key($row['order_number'] ?? '');
            $own = trim((string) ($row['tracking_number'] ?? ''));
            $ownLink = trim((string) ($row['issue_link'] ?? ''));
            $rows[$i]['tracking_number'] = $own !== '' ? $own : ($map[$k]['tracking_number'] ?? null);
            $rows[$i]['issue_link'] = $ownLink !== '' ? $ownLink : ($map[$k]['issue_link'] ?? null);
        }

        return $rows;
    }
}
