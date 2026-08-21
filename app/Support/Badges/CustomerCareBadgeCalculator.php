<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Models\CcMessagesPending;
use App\Models\CustomerFollowup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerCareBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'customer-care';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * KPIs from Follow Up CC + All Issues / issue boards.
     *
     * @return array{
     *     pending_messages: int,
     *     pending_followups: int,
     *     active_issues: int,
     *     dispatch_issues: int,
     *     qc_issues: int,
     *     label_issues: int,
     *     l30_issue_rows: int
     * }
     */
    public static function calculate(): array
    {
        return [
            'pending_messages' => CcMessagesPending::pendingTotal(),
            'pending_followups' => Schema::hasTable('customer_followups')
                ? (int) CustomerFollowup::query()->where('status', 'Pending')->count()
                : 0,
            'active_issues' => self::activeCount('orders_on_hold_issues'),
            'dispatch_issues' => self::activeCount('dispatch_issue_issues'),
            'qc_issues' => self::activeCount('qc_and_packing_issues'),
            'label_issues' => self::activeCount('label_issue_issues'),
            'l30_issue_rows' => self::l30IssueRows('orders_on_hold_issues'),
        ];
    }

    private static function activeCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $q = DB::table($table);
        if (Schema::hasColumn($table, 'is_archived')) {
            $q->where(function ($x) {
                $x->where('is_archived', 0)->orWhereNull('is_archived');
            });
        }
        if (Schema::hasColumn($table, 'archived_at')) {
            $q->whereNull('archived_at');
        }

        return (int) $q->count();
    }

    private static function l30IssueRows(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_at')) {
            return 0;
        }

        return (int) DB::table($table)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }
}
