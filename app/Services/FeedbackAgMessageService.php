<?php

namespace App\Services;

use App\Models\DepartmentFeedback;
use App\Models\DynamicMessage;
use App\Models\FeedbackAgRule;
use App\Models\User;
use App\Services\DynamicMessages\DynamicMessageEngine;

/**
 * AG-MSG: evaluates the saved rules against a person's lifetime feedback numbers and, for every
 * rule that applies, delivers a message (shown to the recipient as coming from president@5core.com).
 */
class FeedbackAgMessageService
{
    public const EVENT_KEY = 'feedback.ag_msg';

    public function __construct(private readonly DynamicMessageEngine $engine) {}

    /**
     * Lifetime numbers per user id for one department ("all" = every department).
     *
     * @param  list<int>  $userIds
     * @return array<int, array{status: string, avg: ?float, reviews: int, ratings: int}>
     */
    public function metrics(array $userIds, string $department): array
    {
        $out = [];
        foreach ($userIds as $id) {
            $out[$id] = ['status' => 'none', 'avg' => null, 'reviews' => 0, 'ratings' => 0];
        }
        if ($userIds === [] || ! DepartmentFeedback::tableReady()) {
            return $out;
        }

        $q = DepartmentFeedback::query()
            ->whereIn('user_id', $userIds)
            ->where('skipped', false)
            ->whereNotNull('rating');
        if ($department !== 'all') {
            $q->where('department', $department);
        }

        $rows = $q->selectRaw("user_id, COUNT(*) as total, AVG(rating) as avg_rating, SUM(CASE WHEN comment IS NOT NULL AND TRIM(comment) <> '' THEN 1 ELSE 0 END) as reviews")
            ->groupBy('user_id')
            ->get();

        foreach ($rows as $r) {
            $out[(int) $r->user_id] = [
                'status' => (int) $r->total > 0 ? 'given' : 'none',
                'avg' => round((float) $r->avg_rating, 1),
                'reviews' => (int) $r->reviews,
                'ratings' => (int) $r->total,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $userIds
     * @return array{sent: int, duplicates: int, no_match: int, people: int}
     */
    public function generate(array $userIds, string $department): array
    {
        $result = ['sent' => 0, 'duplicates' => 0, 'no_match' => 0, 'people' => 0];
        $rules = FeedbackAgRule::query()->where('is_active', true)->orderBy('id')->get();
        if ($rules->isEmpty() || $userIds === []) {
            $result['no_match'] = count($userIds);

            return $result;
        }

        $users = User::query()->whereIn('id', $userIds)->active()->get()->keyBy('id');
        $metrics = $this->metrics($users->keys()->map(fn ($id) => (int) $id)->all(), $department);
        $label = $department === 'all'
            ? 'All departments'
            : (DepartmentFeedback::DEPARTMENTS[$department]['label'] ?? $department);
        $today = now(config('dynamic_messages.timezone'))->format('Y-m-d');

        foreach ($users as $user) {
            $m = $metrics[(int) $user->id];
            $matched = false;
            $vars = [
                'department' => $label,
                'status' => $m['status'] === 'given' ? 'Given' : 'Not given yet',
                'avg' => $m['avg'] === null ? '-' : number_format($m['avg'], 1),
                'ratings' => $m['ratings'],
                'reviews' => $m['reviews'],
            ];

            foreach ($rules as $rule) {
                if (! $rule->matches($m)) {
                    continue;
                }
                $matched = true;
                $dedupe = 'ag:'.$rule->id.':'.$department.':'.$today;
                $exists = DynamicMessage::query()
                    ->where('event_key', self::EVENT_KEY)
                    ->where('recipient_user_id', $user->id)
                    ->where('dedupe_key', $dedupe)
                    ->exists();
                if ($exists) {
                    $result['duplicates']++;

                    continue;
                }

                DynamicMessage::create([
                    'rule_id' => null,
                    'recipient_user_id' => $user->id,
                    'dedupe_key' => $dedupe,
                    'event_key' => self::EVENT_KEY,
                    'page_label' => 'Feedback',
                    'title' => mb_substr('Feedback · '.$label, 0, 190),
                    'body' => $this->engine->render((string) $rule->message, $user, $vars),
                    'context' => $vars + ['ag_rule_id' => $rule->id],
                    'allow_reply' => (bool) $rule->allow_reply,
                ]);
                $result['sent']++;
            }

            $matched ? $result['people']++ : $result['no_match']++;
        }

        return $result;
    }
}
