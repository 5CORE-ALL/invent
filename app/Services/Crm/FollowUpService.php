<?php

namespace App\Services\Crm;

use App\Events\Crm\CrmCommunicationLogged;
use App\Events\Crm\CrmFollowUpCreated;
use App\Events\Crm\CrmFollowUpStatusChanged;
use App\Events\Crm\CrmFollowUpUpdated;
use App\Models\Crm\CommunicationLog;
use App\Models\Crm\Customer;
use App\Models\Crm\FollowUp;
use App\Models\Crm\FollowUpStatusHistory;
use App\Models\User;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FollowUpService implements FollowUpServiceInterface
{
    public function createFollowUp(array $attributes, User $actor, array $eventContext = []): FollowUp
    {
        $this->assertRequiredKeys($attributes, ['customer_id', 'assigned_user_id', 'title', 'follow_up_type', 'priority']);

        return DB::transaction(function () use ($attributes, $actor, $eventContext) {
            $attributes = array_merge(['status' => FollowUp::STATUS_PENDING], $attributes);

            /** @var FollowUp $followUp */
            $followUp = FollowUp::query()->create(
                collect($attributes)->only((new FollowUp)->getFillable())->all()
            );

            FollowUpStatusHistory::query()->create([
                'follow_up_id' => $followUp->id,
                'old_status' => null,
                'new_status' => $followUp->status,
                'changed_by' => $actor->id,
            ]);

            $fresh = $followUp->fresh(['customer', 'assignedUser', 'company']);
            event(new CrmFollowUpCreated($fresh, $actor, $eventContext));

            return $fresh;
        });
    }

    public function updateFollowUp(FollowUp $followUp, array $attributes, User $actor): FollowUp
    {
        return DB::transaction(function () use ($followUp, $attributes, $actor) {
            $oldStatus = $followUp->status;

            $followUp->fill(
                collect($attributes)->only($followUp->getFillable())->all()
            );

            if ($followUp->isDirty('status')) {
                FollowUpStatusHistory::query()->create([
                    'follow_up_id' => $followUp->id,
                    'old_status' => $oldStatus,
                    'new_status' => $followUp->status,
                    'changed_by' => $actor->id,
                ]);
            }

            $changes = $followUp->getDirty();
            $followUp->save();

            $fresh = $followUp->fresh(['customer', 'assignedUser', 'company']);
            if ($changes !== []) {
                event(new CrmFollowUpUpdated($fresh, $actor, $changes));
            }

            return $fresh;
        });
    }

    public function changeStatus(FollowUp $followUp, string $newStatus, User $actor): FollowUp
    {
        $allowed = [
            FollowUp::STATUS_PENDING,
            FollowUp::STATUS_COMPLETED,
            FollowUp::STATUS_POSTPONED,
            FollowUp::STATUS_CANCELLED,
        ];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException('Invalid follow-up status: '.$newStatus);
        }

        if ($followUp->status === $newStatus) {
            return $followUp;
        }

        return DB::transaction(function () use ($followUp, $newStatus, $actor) {
            $oldStatus = $followUp->status;

            FollowUpStatusHistory::query()->create([
                'follow_up_id' => $followUp->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $actor->id,
            ]);

            $followUp->update(['status' => $newStatus]);

            $fresh = $followUp->fresh(['customer', 'assignedUser', 'company']);
            event(new CrmFollowUpStatusChanged($fresh, $actor, $oldStatus, $newStatus));

            return $fresh;
        });
    }

    public function addCommunication(array $attributes, User $actor): CommunicationLog
    {
        $this->assertRequiredKeys($attributes, ['customer_id', 'type', 'message']);

        $payload = collect($attributes)
            ->only(['customer_id', 'follow_up_id', 'type', 'message', 'attachment_path'])
            ->merge(['user_id' => $actor->id])
            ->all();

        /** @var CommunicationLog $log */
        $log = CommunicationLog::query()->create($payload);

        $fresh = $log->fresh(['customer', 'followUp', 'user']);
        event(new CrmCommunicationLogged($fresh, $actor));

        return $fresh;
    }

    public function getCustomerTimeline(Customer $customer, ?int $limit = 100): Collection
    {
        $limit = $limit ?? 100;

        $rows = collect();

        FollowUp::query()
            ->where('customer_id', $customer->id)
            ->with('assignedUser')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (FollowUp $fu) use ($rows): void {
                $at = $fu->updated_at ?? $fu->created_at;
                $rows->push([
                    'type' => 'follow_up',
                    'occurred_at' => $at,
                    'title' => $fu->title,
                    'meta' => [
                        'follow_up_id' => $fu->id,
                        'status' => $fu->status,
                        'follow_up_type' => $fu->follow_up_type,
                        'priority' => $fu->priority,
                        'assigned_to' => $fu->assignedUser?->name,
                    ],
                ]);
            });

        CommunicationLog::query()
            ->where('customer_id', $customer->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (CommunicationLog $log) use ($rows): void {
                $rows->push([
                    'type' => 'communication',
                    'occurred_at' => $log->created_at,
                    'title' => ucfirst($log->type),
                    'meta' => [
                        'communication_log_id' => $log->id,
                        'message_excerpt' => \Illuminate\Support\Str::limit($log->message, 200),
                        'logged_by' => $log->user?->name,
                        'follow_up_id' => $log->follow_up_id,
                    ],
                ]);
            });

        $followUpIds = FollowUp::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        if ($followUpIds->isNotEmpty()) {
            FollowUpStatusHistory::query()
                ->whereIn('follow_up_id', $followUpIds)
                ->with(['changedByUser', 'followUp'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->each(function (FollowUpStatusHistory $h) use ($rows): void {
                    $rows->push([
                        'type' => 'status_change',
                        'occurred_at' => $h->created_at,
                        'title' => sprintf(
                            'Status: %s → %s',
                            $h->old_status ?? '—',
                            $h->new_status
                        ),
                        'meta' => [
                            'history_id' => $h->id,
                            'follow_up_id' => $h->follow_up_id,
                            'follow_up_title' => $h->followUp?->title,
                            'changed_by' => $h->changedByUser?->name,
                        ],
                    ]);
                });
        }

        return $rows
            ->sortByDesc(fn (array $row): int => ($row['occurred_at'] instanceof CarbonInterface)
                ? $row['occurred_at']->timestamp
                : 0)
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function assertRequiredKeys(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                throw new InvalidArgumentException("Missing required field: {$key}");
            }
        }
    }
}
