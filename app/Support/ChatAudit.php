<?php

namespace App\Support;

use App\Models\ChatAudit as ChatAuditRow;
use App\Models\ChatHealthEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ChatAudit
{
    public static function record(?User $actor, string $action, ?string $targetType = null, ?int $targetId = null, ?int $channelId = null, array $meta = []): void
    {
        if (! Schema::hasTable('chat_audits')) {
            return;
        }

        try {
            ChatAuditRow::query()->create([
                'actor_id' => $actor?->id,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'channel_id' => $channelId,
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable) {
        }
    }

    public static function health(string $type, ?User $user = null, ?int $channelId = null, array $meta = []): void
    {
        if (! Schema::hasTable('chat_health_events')) {
            return;
        }

        try {
            ChatHealthEvent::query()->create([
                'type' => $type,
                'user_id' => $user?->id,
                'channel_id' => $channelId,
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable) {
        }
    }
}
