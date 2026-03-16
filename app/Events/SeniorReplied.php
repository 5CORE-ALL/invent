<?php

namespace App\Events;

use App\Models\AiEscalation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeniorReplied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public int $escalationId;
    public string $question;
    public string $reply;
    public string $timestamp;

    public function __construct(AiEscalation $escalation)
    {
        $this->userId = (int) $escalation->user_id;
        $this->escalationId = (int) $escalation->id;
        $this->question = $escalation->original_question ?? '';
        $this->reply = $escalation->senior_reply ?? '';
        $this->timestamp = $escalation->answered_at
            ? $escalation->answered_at->toIso8601String()
            : now()->toIso8601String();
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'SeniorReplied';
    }

    public function broadcastWith(): array
    {
        return [
            'escalation_id' => $this->escalationId,
            'question' => $this->question,
            'reply' => $this->reply,
            'timestamp' => $this->timestamp,
        ];
    }
}
