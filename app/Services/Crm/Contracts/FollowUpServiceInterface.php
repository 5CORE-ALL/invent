<?php

namespace App\Services\Crm\Contracts;

use App\Models\Crm\CommunicationLog;
use App\Models\Crm\Customer;
use App\Models\Crm\FollowUp;
use App\Models\User;
use Illuminate\Support\Collection;

interface FollowUpServiceInterface
{
    /**
     * @param  array<string, mixed>  $attributes  Follow-up attributes (see {@see FollowUp::$fillable}); requires customer_id, assigned_user_id, title, follow_up_type, priority
     * @param  array<string, mixed>  $eventContext  Merged into CRM activity log / events (e.g. source, shopify_order_id)
     */
    public function createFollowUp(array $attributes, User $actor, array $eventContext = []): FollowUp;

    /**
     * @param  array<string, mixed>  $attributes  Partial attributes to update
     */
    public function updateFollowUp(FollowUp $followUp, array $attributes, User $actor): FollowUp;

    public function changeStatus(FollowUp $followUp, string $newStatus, User $actor): FollowUp;

    /**
     * @param  array<string, mixed>  $attributes  customer_id, type, message required; optional follow_up_id, attachment_path; user_id is set from actor
     */
    public function addCommunication(array $attributes, User $actor): CommunicationLog;

    /**
     * Unified timeline (newest first): follow-ups, communications, status changes.
     *
     * @return Collection<int, array{type: string, occurred_at: \Carbon\CarbonInterface, title: string, meta: array<string, mixed>}>
     */
    public function getCustomerTimeline(Customer $customer, ?int $limit = 100): Collection;
}
