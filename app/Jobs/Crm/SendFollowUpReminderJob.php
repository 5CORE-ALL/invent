<?php

namespace App\Jobs\Crm;

use App\Models\Crm\FollowUp;
use App\Notifications\Crm\FollowUpReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendFollowUpReminderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public int $followUpId
    ) {
    }

    public function uniqueId(): string
    {
        return 'follow-up-reminder:'.$this->followUpId;
    }

    public function handle(): void
    {
        $followUp = FollowUp::query()
            ->with(['customer', 'assignedUser'])
            ->find($this->followUpId);

        if ($followUp === null) {
            return;
        }

        if ($followUp->reminder_notified_at !== null) {
            return;
        }

        if ($followUp->reminder_at === null || $followUp->reminder_at->isFuture()) {
            return;
        }

        if ($followUp->status !== FollowUp::STATUS_PENDING) {
            return;
        }

        $assignee = $followUp->assignedUser;
        if ($assignee === null) {
            return;
        }

        $assignee->notify(new FollowUpReminderNotification($followUp));

        $followUp->forceFill(['reminder_notified_at' => now()])->save();
    }
}
