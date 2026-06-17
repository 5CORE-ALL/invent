<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Crm\FollowUp */
class FollowUpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'shopify_order_id' => $this->shopify_order_id,
            'company_id' => $this->company_id,
            'assigned_user_id' => $this->assigned_user_id,

            'title' => $this->title,
            'description' => $this->description,

            'follow_up_type' => $this->follow_up_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'outcome' => $this->outcome,

            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'reminder_at' => $this->reminder_at?->toIso8601String(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'reminder_notified_at' => $this->reminder_notified_at?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'customer' => $this->whenLoaded('customer', function () {
                if ($this->customer === null) {
                    return null;
                }

                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                ];
            }),

            'assigned_user' => $this->whenLoaded('assignedUser', function () {
                if ($this->assignedUser === null) {
                    return null;
                }

                return [
                    'id' => $this->assignedUser->id,
                    'name' => $this->assignedUser->name,
                    'email' => $this->assignedUser->email,
                ];
            }),

            'company' => $this->whenLoaded('company', function () {
                if ($this->company === null) {
                    return null;
                }

                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),

            'status_histories' => $this->when(
                $this->relationLoaded('statusHistories'),
                fn () => $this->statusHistories->map(fn ($history) => [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'changed_by' => $history->relationLoaded('changedByUser') && $history->changedByUser
                        ? [
                            'id' => $history->changedByUser->id,
                            'name' => $history->changedByUser->name,
                            'email' => $history->changedByUser->email,
                        ]
                        : null,
                    'created_at' => $history->created_at?->toIso8601String(),
                ])->values()->all()
            ),
        ];
    }
}
