<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Http\Middleware\LogIssueActivity;
use App\Support\CustomerCareDepartments;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Read side of the All Issues "History" and "Archive" buttons.
 *
 *  - history(): every change made on the page (create / edit / inline edit /
 *    archive / delete) with field-level old → new values and who made it.
 *    Changes made before activity logging existed are rebuilt from the older
 *    per-revision history table.
 *  - archive(): everything that was archived or deleted, and by whom.
 */
class IssueActivityController extends Controller
{
    private const ISSUES = 'dispatch_issue_issues';

    private const LEGACY_HISTORY = 'dispatch_issue_issue_histories';

    private const LIMIT = 3000;

    private const FIELD_LABELS = [
        'sku' => 'SKU',
        'qty' => 'Qty',
        'order_qty' => 'Order Qty',
        'order_number' => 'Order #',
        'parent' => 'Parent',
        'marketplace_1' => 'Marketplace',
        'marketplace_2' => 'Marketplace 2',
        'what_happened' => 'What Happened',
        'issue' => 'Root Cause Found',
        'issue_remark' => 'Root Cause Remark',
        'action_1' => 'Action',
        'action_1_remark' => 'Action Remark',
        'replacement_tracking' => 'Replacement Tracking',
        'c_action_1' => 'Root Cause Fixed',
        'c_action_1_remark' => 'Root Cause Fixed Remark',
        'close_note' => 'Close Note',
        'department' => 'Department',
        'tracking_number' => 'Tracking ID',
        'issue_link' => 'Link',
        'total_loss' => 'Loss $',
        'issue_carrier' => 'Carrier',
        'nfe' => 'NFE',
        'resolved' => 'Resolved',
        'claimable' => 'Claimable',
        'claimable_remark' => 'Claimable Remark',
        'claim_filed' => 'Claim Filed',
        'claim_received' => 'Claim Received',
        'amp_usd' => 'AMP USD',
        'amt_rec' => 'Amt Rec',
        'issue_date' => 'Issue Date',
        'image_1' => 'Image 1',
        'image_2' => 'Image 2',
    ];

    /** Columns that are bookkeeping, not "changes". */
    private const LEGACY_SKIP = [
        'id', 'orders_on_hold_issue_id', 'event_type', 'revision_no', 'created_by',
        'created_by_user_id', 'logged_at', 'created_at', 'updated_at', 'group_id',
        'is_archived', 'archived_at', 'archived_by',
    ];

    public function history(Request $request): JsonResponse
    {
        $dept = $this->departmentFilter($request);
        $events = [];
        $firstLiveAt = null;

        if (Schema::hasTable(LogIssueActivity::LOG_TABLE)) {
            $q = DB::table(LogIssueActivity::LOG_TABLE)->orderByDesc('id')->limit(self::LIMIT);
            if ($dept !== '') {
                $q->where('department', 'like', '%'.$dept.'%');
            }
            foreach ($q->get() as $r) {
                $events[] = $this->liveEvent($r);
            }
            $firstLiveAt = DB::table(LogIssueActivity::LOG_TABLE)->min('created_at');
        }

        foreach ($this->legacyEvents($firstLiveAt, $dept) as $e) {
            $events[] = $e;
        }

        usort($events, fn ($a, $b) => strcmp($b['ts'], $a['ts']));
        $truncated = count($events) > self::LIMIT;
        $events = array_slice($events, 0, self::LIMIT);

        return response()->json([
            'events' => $events,
            'truncated' => $truncated,
            'tracking_since' => $firstLiveAt ? Carbon::parse($firstLiveAt)->format('d M Y H:i') : null,
        ]);
    }

    public function archive(Request $request): JsonResponse
    {
        $dept = $this->departmentFilter($request);
        $entries = [];

        $q = DB::table(self::ISSUES)->where('is_archived', true)
            ->orderByDesc('archived_at')->orderByDesc('id')->limit(self::LIMIT);
        $rawDept = trim((string) $request->query('department', ''));
        if ($rawDept !== '') {
            CustomerCareDepartments::applyWhereDepartmentMatches($q, 'department', $rawDept);
        }
        foreach ($q->get() as $r) {
            $at = $r->archived_at ?? $r->updated_at;
            $entries[] = $this->archiveEntry('archived', $at, $r->archived_by ?? null, (array) $r);
        }

        if (Schema::hasTable(LogIssueActivity::LOG_TABLE)) {
            $dq = DB::table(LogIssueActivity::LOG_TABLE)->where('event', 'deleted')
                ->orderByDesc('id')->limit(self::LIMIT);
            if ($dept !== '') {
                $dq->where('department', 'like', '%'.$dept.'%');
            }
            foreach ($dq->get() as $r) {
                $snap = json_decode((string) $r->snapshot, true);
                $snap = is_array($snap) ? $snap : [];
                $snap += [
                    'sku' => $r->sku,
                    'order_number' => $r->order_number,
                    'department' => null,
                ];
                $entry = $this->archiveEntry('deleted', $r->created_at, $r->user_name, $snap);
                if (empty($snap['department'])) {
                    $entry['department'] = (string) $r->department;
                }
                $entries[] = $entry;
            }
        }

        usort($entries, fn ($a, $b) => strcmp($b['ts'], $a['ts']));
        $truncated = count($entries) > self::LIMIT;

        return response()->json([
            'events' => array_slice($entries, 0, self::LIMIT),
            'truncated' => $truncated,
            'delete_tracking_since' => Schema::hasTable(LogIssueActivity::LOG_TABLE)
                ? DB::table(LogIssueActivity::LOG_TABLE)->min('created_at')
                : null,
        ]);
    }

    private function departmentFilter(Request $request): string
    {
        $d = trim((string) $request->query('department', ''));

        return $d === '' ? '' : CustomerCareDepartments::label($d);
    }

    private function fmt($at): array
    {
        if (! $at) {
            return ['', ''];
        }
        try {
            $c = Carbon::parse($at);

            return [$c->format('Y-m-d H:i:s'), $c->format('d M Y, H:i')];
        } catch (\Throwable $e) {
            return [(string) $at, (string) $at];
        }
    }

    private function label(string $col): string
    {
        return self::FIELD_LABELS[$col] ?? Str::headline($col);
    }

    private function liveEvent(object $r): array
    {
        [$ts, $when] = $this->fmt($r->created_at);
        $changes = [];
        $decoded = json_decode((string) $r->changes, true);
        if (is_array($decoded)) {
            foreach ($decoded as $col => $v) {
                $changes[] = [
                    'field' => $this->label((string) $col),
                    'old' => $v['old'] ?? null,
                    'new' => $v['new'] ?? null,
                ];
            }
        }

        return [
            'key' => 'a'.$r->id,
            'ts' => $ts,
            'when' => $when,
            'event' => $r->event,
            'action' => $r->action,
            'user' => $r->user_name ?: '—',
            'issue_id' => $r->issue_id,
            'sku' => $r->sku,
            'order_number' => $r->order_number,
            'department' => $r->department,
            'changes' => $changes,
        ];
    }

    private function archiveEntry(string $event, $at, ?string $user, array $row): array
    {
        [$ts, $when] = $this->fmt($at);
        [, $created] = $this->fmt($row['created_at'] ?? null);

        return [
            'event' => $event,
            'ts' => $ts,
            'when' => $when,
            'user' => trim((string) $user) !== '' ? $user : '—',
            'issue_id' => $row['id'] ?? null,
            'sku' => $row['sku'] ?? null,
            'order_number' => $row['order_number'] ?? null,
            'department' => CustomerCareDepartments::label($row['department'] ?? null),
            'issue' => $row['issue'] ?? null,
            'total_loss' => $row['total_loss'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'created_at' => $created,
        ];
    }

    /**
     * Events from before activity logging started, rebuilt from the older
     * per-revision snapshots. Each revision is diffed against the previous one
     * of the same issue (when that revision is within the loaded window).
     *
     * @return array<int, array<string, mixed>>
     */
    private function legacyEvents(?string $beforeAt, string $dept): array
    {
        if (! Schema::hasTable(self::LEGACY_HISTORY)) {
            return [];
        }

        $q = DB::table(self::LEGACY_HISTORY)->orderByDesc('id')->limit(self::LIMIT);
        if ($beforeAt) {
            $q->where(function ($w) use ($beforeAt) {
                $w->where('logged_at', '<', $beforeAt)->orWhereNull('logged_at');
            });
        }
        $rows = $q->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $orders = [];
        $ids = $rows->pluck('orders_on_hold_issue_id')->filter()->unique()->values();
        foreach ($ids->chunk(1000) as $chunk) {
            foreach (DB::table(self::ISSUES)->whereIn('id', $chunk)->get(['id', 'order_number']) as $i) {
                $orders[(int) $i->id] = $i->order_number;
            }
        }

        // Oldest-first per issue so each revision can see the previous one.
        $byIssue = [];
        foreach ($rows->sortBy('id') as $r) {
            $byIssue[(int) $r->orders_on_hold_issue_id][] = $r;
        }

        $out = [];
        foreach ($byIssue as $issueId => $revs) {
            $prev = null;
            foreach ($revs as $r) {
                $arr = (array) $r;
                $changes = [];
                if ($prev !== null && ($arr['event_type'] ?? '') === 'updated') {
                    foreach ($arr as $col => $val) {
                        if (in_array($col, self::LEGACY_SKIP, true)) {
                            continue;
                        }
                        $old = $prev[$col] ?? null;
                        if ($this->sameValue($old, $val)) {
                            continue;
                        }
                        $changes[] = [
                            'field' => $this->label((string) $col),
                            'old' => $this->legacyDisplay($col, $old),
                            'new' => $this->legacyDisplay($col, $val),
                        ];
                    }
                }
                $prev = $arr;

                $deptLabel = CustomerCareDepartments::label($arr['department'] ?? null);
                if ($dept !== '' && stripos($deptLabel, $dept) === false) {
                    continue;
                }

                [$ts, $when] = $this->fmt($arr['logged_at'] ?? $arr['created_at'] ?? null);
                $type = (string) ($arr['event_type'] ?? 'updated');
                $out[] = [
                    'key' => 'h'.$arr['id'],
                    'ts' => $ts,
                    'when' => $when,
                    'event' => in_array($type, ['created', 'updated', 'archived'], true) ? $type : 'updated',
                    'action' => null,
                    'user' => trim((string) ($arr['created_by'] ?? '')) ?: '—',
                    'issue_id' => $issueId,
                    'sku' => $arr['sku'] ?? null,
                    'order_number' => $orders[$issueId] ?? null,
                    'department' => $deptLabel,
                    'changes' => $changes,
                    'legacy' => true,
                ];
            }
        }

        return $out;
    }

    private function sameValue($a, $b): bool
    {
        $a = is_string($a) ? trim($a) : $a;
        $b = is_string($b) ? trim($b) : $b;
        if (($a === null || $a === '') && ($b === null || $b === '')) {
            return true;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.0000001;
        }

        return (string) $a === (string) $b;
    }

    private function legacyDisplay(string $col, $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($col === 'department') {
            return CustomerCareDepartments::label((string) $value);
        }
        $s = (string) $value;

        return mb_strlen($s) > 400 ? mb_substr($s, 0, 399).'…' : $s;
    }
}