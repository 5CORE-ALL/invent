<?php

namespace App\Events\Crm;

use App\Models\Crm\FollowUp;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmFollowUpStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FollowUp $followUp,
        public User $actor,
        public ?string $oldStatus,
        public string $newStatus
    ) {
    }
}
