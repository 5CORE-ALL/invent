<?php

namespace App\Services\Crm;

use App\Models\Crm\FollowUp;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimized Eloquent entry points for CRM follow-up dashboard metrics and lists.
 */
final class FollowUpDashboardQueries
{
    /**
     * All pending follow-ups, prioritised for display.
     */
    public static function pendingFollowUps(?int $assignedUserId = null): Builder
    {
        $q = FollowUp::query()
            ->pending()
            ->select([
                'follow_ups.id',
                'follow_ups.customer_id',
                'follow_ups.company_id',
                'follow_ups.assigned_user_id',
                'follow_ups.title',
                'follow_ups.follow_up_type',
                'follow_ups.priority',
                'follow_ups.status',
                'follow_ups.scheduled_at',
                'follow_ups.reminder_at',
                'follow_ups.created_at',
            ])
            ->orderByPriority()
            ->orderBy('follow_ups.scheduled_at');

        return self::applyAssignee($q, $assignedUserId);
    }

    /**
     * Pending follow-ups whose scheduled time is in the past (includes earlier today).
     */
    public static function overdueFollowUps(?int $assignedUserId = null): Builder
    {
        $q = FollowUp::query()
            ->overdue()
            ->select([
                'follow_ups.id',
                'follow_ups.customer_id',
                'follow_ups.company_id',
                'follow_ups.assigned_user_id',
                'follow_ups.title',
                'follow_ups.follow_up_type',
                'follow_ups.priority',
                'follow_ups.status',
                'follow_ups.scheduled_at',
                'follow_ups.created_at',
            ])
            ->orderByPriority()
            ->orderBy('follow_ups.scheduled_at');

        return self::applyAssignee($q, $assignedUserId);
    }

    /**
     * Pending follow-ups scheduled for the current calendar day.
     */
    public static function todaysFollowUps(?int $assignedUserId = null): Builder
    {
        $q = FollowUp::query()
            ->pendingScheduledToday()
            ->select([
                'follow_ups.id',
                'follow_ups.customer_id',
                'follow_ups.company_id',
                'follow_ups.assigned_user_id',
                'follow_ups.title',
                'follow_ups.follow_up_type',
                'follow_ups.priority',
                'follow_ups.status',
                'follow_ups.scheduled_at',
                'follow_ups.reminder_at',
                'follow_ups.created_at',
            ])
            ->orderByPriority()
            ->orderBy('follow_ups.scheduled_at');

        return self::applyAssignee($q, $assignedUserId);
    }

    /**
     * Aggregate conversion metrics for completed follow-ups.
     *
     * Uses one query with conditional sums. {@see $since} / {@see $until} filter on {@see FollowUp::$updated_at}
     * (last change time, typically when status/outcome was set).
     *
     * @return array{
     *     completed_total: int,
     *     converted: int,
     *     interested: int,
     *     not_interested: int,
     *     callback: int,
     *     no_outcome: int,
     *     conversion_rate: float|null,
     * }
     */
    public static function conversionStats(
        ?int $assignedUserId = null,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null
    ): array {
        $q = FollowUp::query()
            ->where('follow_ups.status', FollowUp::STATUS_COMPLETED)
            ->when($since !== null, fn (Builder $b) => $b->where('follow_ups.updated_at', '>=', $since))
            ->when($until !== null, fn (Builder $b) => $b->where('follow_ups.updated_at', '<=', $until));

        self::applyAssignee($q, $assignedUserId);

        /** @var FollowUp|null $row */
        $row = $q->clone()
            ->selectRaw(
                'COUNT(*) as completed_total, '
                .'SUM(CASE WHEN follow_ups.outcome = ? THEN 1 ELSE 0 END) as converted, '
                .'SUM(CASE WHEN follow_ups.outcome = ? THEN 1 ELSE 0 END) as interested, '
                .'SUM(CASE WHEN follow_ups.outcome = ? THEN 1 ELSE 0 END) as not_interested, '
                .'SUM(CASE WHEN follow_ups.outcome = ? THEN 1 ELSE 0 END) as callback, '
                .'SUM(CASE WHEN follow_ups.outcome IS NULL THEN 1 ELSE 0 END) as no_outcome',
                [
                    FollowUp::OUTCOME_CONVERTED,
                    FollowUp::OUTCOME_INTERESTED,
                    FollowUp::OUTCOME_NOT_INTERESTED,
                    FollowUp::OUTCOME_CALLBACK,
                ]
            )
            ->first();

        $total = (int) ($row?->completed_total ?? 0);
        $converted = (int) ($row?->converted ?? 0);

        return [
            'completed_total' => $total,
            'converted' => $converted,
            'interested' => (int) ($row?->interested ?? 0),
            'not_interested' => (int) ($row?->not_interested ?? 0),
            'callback' => (int) ($row?->callback ?? 0),
            'no_outcome' => (int) ($row?->no_outcome ?? 0),
            'conversion_rate' => $total > 0 ? round($converted / $total * 100, 2) : null,
        ];
    }

    /**
     * @template TModel of FollowUp
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected static function applyAssignee(Builder $query, ?int $assignedUserId): Builder
    {
        if ($assignedUserId !== null) {
            $query->where('follow_ups.assigned_user_id', $assignedUserId);
        }

        return $query;
    }
}
