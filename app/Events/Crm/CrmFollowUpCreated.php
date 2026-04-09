<?php

namespace App\Events\Crm;

use App\Models\Crm\FollowUp;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmFollowUpCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public FollowUp $followUp,
        public User $actor,
        public array $context = []
    ) {
    }
}
