<?php

namespace App\Http\Middleware;

use App\Support\CustomerCareDepartments;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every change made through the All Issues endpoints (create, edit,
 * inline edit, archive, delete) in `dispatch_issue_activity`.
 *
 * It works by snapshotting the affected rows (the row itself plus every row of
 * its group) before and after the controller runs and diffing the two, so it
 * captures changes no matter which controller code path made them. A failure
 * here never breaks the request — it is only logged.
 */
class LogIssueActivity
{
    public const LOG_TABLE = 'dispatch_issue_activity';

    private const ISSUES_TABLE = 'dispatch_issue_issues';

    /** Columns that change on every write and are not interesting to show. */
    private const IGNORED_COLUMNS = [
        'id', 'created_at', 'updated_at', 'group_id', 'created_by_user_id',
        'archived_at', 'archived_by', 'is_archived',
    ];

    private const ACTION_LABELS = [
        'list.store' => 'Created',
        'list.update' => 'Edited',
        'list.archive' => 'Archived',
        'list.destroy' => 'Deleted',
        'list.patch.claim.filed' => 'Claim Filed',
        'list.patch.claimable' => 'Claimable',
        'list.patch.claimable.remark' => 'Claimable Remark',
        'list.patch.nfe' => 'NFE',
        'list.patch.resolved' => 'Resolved',
        'list.patch.amp.usd' => 'AMP USD',
        'list.patch.amt.rec' => 'Amt Rec',
        'list.patch.total.loss' => 'Loss $',
        'list.patch.claim.received' => 'Claim Received',
        'list.patch.issue.carrier' => 'Carrier',
    ];

    public function handle(Request $request, Closure $next)
    {
        $before = null;
        $id = $request->route('id');
        $id = $id !== null && is_numeric($id) ? (int) $id : null;

        try {
            if (Schema::hasTable(self::LOG_TABLE)) {
                $before = $this->snapshot($id, null);
            }
        } catch (\Throwable $e) {
            $before = null;
            Log::warning('LogIssueActivity: before-snapshot failed: '.$e->getMessage());
        }

        /** @var Response $response */
        $response = $next($request);

        if ($before !== null && $response->getStatusCode() < 400) {
            try {
                $this->record($request, $before, $id, $response);
            } catch (\Throwable $e) {
                Log::warning('LogIssueActivity: could not record activity: '.$e->getMessage());
            }
        }

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>> keyed by issue id
     */
    private function snapshot(?int $id, ?string $groupId): array
    {
        $query = DB::table(self::ISSUES_TABLE);

        if ($id !== null) {
            $gid = DB::table(self::ISSUES_TABLE)->where('id', $id)->value('group_id');
            $query->where(function ($q) use ($id, $gid) {
                $q->where('id', $id);
                if ($gid) {
                    $q->orWhere('group_id', $gid);
                }
            });
        } elseif ($groupId) {
            $query->where('group_id', $groupId);
        } else {
            return [];
        }

        $out = [];
        foreach ($query->get() as $row) {
            $arr = (array) $row;
            $out[(int) $arr['id']] = $arr;
        }

        return $out;
    }

    private function record(Request $request, array $before, ?int $id, Response $response): void
    {
        $groupId = null;
        if ($id === null) {
            $decoded = json_decode((string) $response->getContent(), true);
            $groupId = is_array($decoded) ? ($decoded['group_id'] ?? null) : null;
        }

        $after = $this->snapshot($id, is_string($groupId) ? $groupId : null);

        $routeName = (string) optional($request->route())->getName();
        $suffix = preg_replace('/^.*?\.issues\./', '', $routeName);
        $action = self::ACTION_LABELS[$suffix] ?? null;

        $user = $request->user();
        $userName = trim((string) ($user->name ?? '')) ?: 'System';
        $now = now();
        $rows = [];

        $base = function (array $row, string $event, ?string $actionLabel) use ($user, $userName, $now) {
            return [
                'issue_id' => (int) $row['id'],
                'group_id' => $row['group_id'] ?? null,
                'event' => $event,
                'action' => $actionLabel,
                'sku' => $this->clip($row['sku'] ?? null, 255),
                'order_number' => $this->clip($row['order_number'] ?? null, 255),
                'department' => $this->clip(CustomerCareDepartments::label($row['department'] ?? null), 500),
                'changes' => null,
                'snapshot' => null,
                'user_id' => $user->id ?? null,
                'user_name' => $this->clip($userName, 255),
                'created_at' => $now,
            ];
        };

        foreach ($after as $rid => $row) {
            if (! isset($before[$rid])) {
                $rows[] = $base($row, 'created', 'Created');
                continue;
            }

            $old = $before[$rid];
            $wasArchived = ! empty($old['is_archived']);
            $isArchived = ! empty($row['is_archived']);

            if (! $wasArchived && $isArchived) {
                $rows[] = $base($row, 'archived', 'Archived');
                continue;
            }
            if ($wasArchived && ! $isArchived) {
                $rows[] = $base($row, 'restored', 'Restored');
                continue;
            }

            $changes = $this->diff($old, $row);
            if ($changes) {
                $entry = $base($row, 'updated', $action ?? 'Edited');
                $entry['changes'] = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $rows[] = $entry;
            }
        }

        foreach ($before as $rid => $row) {
            if (isset($after[$rid])) {
                continue;
            }
            $entry = $base($row, 'deleted', 'Deleted');
            $entry['snapshot'] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $rows[] = $entry;
        }

        if ($rows) {
            DB::table(self::LOG_TABLE)->insert($rows);
        }
    }

    /**
     * @return array<string, array{old: ?string, new: ?string}>
     */
    private function diff(array $old, array $new): array
    {
        $changes = [];
        foreach ($new as $col => $value) {
            if (in_array($col, self::IGNORED_COLUMNS, true)) {
                continue;
            }
            $prev = $old[$col] ?? null;
            if ($this->same($prev, $value)) {
                continue;
            }
            $changes[$col] = [
                'old' => $this->display($col, $prev),
                'new' => $this->display($col, $value),
            ];
        }

        return $changes;
    }

    private function same($a, $b): bool
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

    private function display(string $col, $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($col === 'department') {
            $value = CustomerCareDepartments::label((string) $value);
        }

        return $this->clip((string) $value, 400);
    }

    private function clip($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = (string) $value;

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }
}