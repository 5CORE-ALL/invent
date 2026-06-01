<?php

namespace App\Listeners\Crm;

use App\Events\Crm\CrmCommunicationLogged;
use App\Events\Crm\CrmFollowUpCreated;
use App\Events\Crm\CrmFollowUpStatusChanged;
use App\Events\Crm\CrmFollowUpUpdated;
use App\Events\Crm\ShopifyOrderImported;
use App\Models\Crm\CommunicationLog;
use App\Models\Crm\CrmActivityLog;
use App\Models\Crm\FollowUp;
use App\Models\Crm\ShopifyOrder;
use App\Models\User;

class CrmActivitySubscriber
{
    public function handleShopifyOrderImported(ShopifyOrderImported $event): void
    {
        $order = $event->order;

        CrmActivityLog::query()->create([
            'action' => 'shopify_order.imported',
            'description' => $event->wasRecentlyCreated
                ? 'Shopify order created from sync'
                : 'Shopify order updated from sync',
            'subject_type' => ShopifyOrder::class,
            'subject_id' => (string) $order->getKey(),
            'properties' => [
                'shopify_order_id' => $order->shopify_order_id,
                'shopify_customer_id' => $order->shopify_customer_id,
                'was_new' => $event->wasRecentlyCreated,
            ],
            'created_at' => now(),
        ]);
    }

    public function handleFollowUpCreated(CrmFollowUpCreated $event): void
    {
        $f = $event->followUp;

        CrmActivityLog::query()->create([
            'action' => 'follow_up.created',
            'description' => $f->title,
            'subject_type' => FollowUp::class,
            'subject_id' => (string) $f->getKey(),
            'causer_type' => User::class,
            'causer_id' => (string) $event->actor->getKey(),
            'properties' => array_merge(
                [
                    'customer_id' => $f->customer_id,
                    'status' => $f->status,
                    'priority' => $f->priority,
                    'follow_up_type' => $f->follow_up_type,
                ],
                $event->context
            ),
            'created_at' => now(),
        ]);
    }

    public function handleFollowUpUpdated(CrmFollowUpUpdated $event): void
    {
        $f = $event->followUp;

        CrmActivityLog::query()->create([
            'action' => 'follow_up.updated',
            'description' => 'Follow-up updated: '.$f->title,
            'subject_type' => FollowUp::class,
            'subject_id' => (string) $f->getKey(),
            'causer_type' => User::class,
            'causer_id' => (string) $event->actor->getKey(),
            'properties' => [
                'changes' => $event->changes,
            ],
            'created_at' => now(),
        ]);
    }

    public function handleFollowUpStatusChanged(CrmFollowUpStatusChanged $event): void
    {
        $f = $event->followUp;

        CrmActivityLog::query()->create([
            'action' => 'follow_up.status_changed',
            'description' => sprintf(
                'Status %s → %s (%s)',
                $event->oldStatus ?? '—',
                $event->newStatus,
                $f->title
            ),
            'subject_type' => FollowUp::class,
            'subject_id' => (string) $f->getKey(),
            'causer_type' => User::class,
            'causer_id' => (string) $event->actor->getKey(),
            'properties' => [
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
            ],
            'created_at' => now(),
        ]);
    }

    public function handleCommunicationLogged(CrmCommunicationLogged $event): void
    {
        $log = $event->communicationLog;

        CrmActivityLog::query()->create([
            'action' => 'communication.logged',
            'description' => ucfirst($log->type).' — '.mb_substr($log->message, 0, 120),
            'subject_type' => CommunicationLog::class,
            'subject_id' => (string) $log->getKey(),
            'causer_type' => User::class,
            'causer_id' => (string) $event->actor->getKey(),
            'properties' => [
                'customer_id' => $log->customer_id,
                'follow_up_id' => $log->follow_up_id,
                'type' => $log->type,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ShopifyOrderImported::class => 'handleShopifyOrderImported',
            CrmFollowUpCreated::class => 'handleFollowUpCreated',
            CrmFollowUpUpdated::class => 'handleFollowUpUpdated',
            CrmFollowUpStatusChanged::class => 'handleFollowUpStatusChanged',
            CrmCommunicationLogged::class => 'handleCommunicationLogged',
        ];
    }
}
